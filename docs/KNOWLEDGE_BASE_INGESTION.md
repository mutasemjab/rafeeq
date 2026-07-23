# Knowledge base ingestion and deployment

This project can build embeddings locally from a recursive folder, resume failed runs, export a compressed database-independent bundle, and import that bundle into the production MySQL database without calling OpenAI again.

## What the pipeline does

1. Recursively scans the source folder.
2. Removes `.DS_Store`, `Thumbs.db`, AppleDouble `._*`, and Office `~$*` files.
3. Computes SHA-256 hashes and embeds only one copy of identical content.
4. Extracts text from PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, text, HTML, images, audio, and video.
5. Uses Arabic and English OCR first, then a cached vision description for visual-only pages or picture cards.
6. Creates page-aware, overlapping, size-bounded chunks.
7. Sends chunks to the embeddings API in batches and saves each completed batch immediately.
8. Resumes from already stored chunks if a file or API request fails.
9. Exports documents, chunks, metadata, and vectors to a streaming gzip NDJSON bundle.

## Required local tools

On macOS with Homebrew:

```bash
brew install poppler ghostscript tesseract tesseract-lang ffmpeg
brew install --cask libreoffice
```

On Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y poppler-utils ghostscript tesseract-ocr tesseract-ocr-ara tesseract-ocr-eng ffmpeg libreoffice
```

The PHP extensions `zip`, `mbstring`, `dom`, `fileinfo`, `pdo_sqlite`, and the production database PDO driver must be enabled.

## Configuration

Set these values locally and on the server. The embedding model and dimensions must be identical in both environments.

```dotenv
OPENAI_API_KEY=your-key
AI_PROVIDER=openai
AI_EMBEDDING_PROVIDER=openai
AI_CHAT_MODEL=gpt-5.6-luna
AI_CHAT_REASONING_EFFORT=none
AI_CHAT_MAX_COMPLETION_TOKENS=900
OPENAI_REQUEST_TIMEOUT=45
AI_EMBEDDING_MODEL=text-embedding-3-large
AI_EMBEDDING_DIMENSIONS=1536
AI_DOMAIN_GUARD_ENABLED=true
AI_DOMAIN_GUARD_MODEL=gpt-5.6-luna
AI_DOMAIN_GUARD_REASONING_EFFORT=none
AI_DOMAIN_GUARD_MAX_COMPLETION_TOKENS=320
AI_DOMAIN_GUARD_CONFIDENCE=0.85
AI_MAX_QUESTIONS_PER_MESSAGE=4
AI_MAX_SOURCE_CONTEXT_CHARS=1800
AI_EMBEDDING_BATCH_SIZE=64
AI_EMBEDDING_REQUEST_TIMEOUT=180
AI_DOCUMENT_CHUNK_WORDS=420
AI_DOCUMENT_CHUNK_OVERLAP_WORDS=60
AI_OCR_LANGUAGES=ara+eng
AI_TRANSCRIPTION_MODEL=gpt-4o-mini-transcribe
AI_DOCUMENT_VISION_MODEL=gpt-5.6-luna
AI_DOCUMENT_VISION_DETAIL=high
AI_DOCUMENT_VISION_FILL_SPARSE_PAGES=true
AI_DOCUMENT_SPARSE_PAGE_CHARACTERS=80
AI_VIDEO_FRAME_INTERVAL_SECONDS=60
AI_VIDEO_MAX_FRAMES=12
AI_DOCUMENT_EXTRACTION_CACHE=true
```

Never commit or upload the local `.env` file.

## Analyze without API calls

```bash
cd /Users/tajawal/Downloads/rafeeq
php artisan knowledge:ingest "/Users/tajawal/Downloads/rafiq files" \
  --dry-run \
  --report=storage/app/knowledge_reports/rafiq-files.json
```

This scans and hashes the source but does not write to the database, copy files, or call OpenAI.

## Start the full local embedding run

Foreground:

```bash
cd /Users/tajawal/Downloads/rafeeq
./scripts/run-knowledge-ingest.sh "/Users/tajawal/Downloads/rafiq files"
```

Detached/background:

```bash
cd /Users/tajawal/Downloads/rafeeq
nohup ./scripts/run-knowledge-ingest.sh "/Users/tajawal/Downloads/rafiq files" \
  > storage/logs/knowledge-ingest-launch.log 2>&1 &
```

The runner creates `database/knowledge.sqlite`, so it never writes the local batch into the production database configured in `.env`. It hard-links source files when possible, uses a 2 GB PHP memory limit, prevents macOS sleep, and creates this deployable output when all files finish:

```text
storage/app/knowledge_exports/rafeeq-knowledge.ndjson.gz
storage/app/knowledge_exports/rafeeq-knowledge.ndjson.gz.sha256
```

Do not add `--force` when resuming. The runner includes `--reembed`, which refreshes only chunks whose stored model or dimensions differ from current configuration and uses their existing text without repeating extraction, OCR, transcription, or vision work. Documents already on the current model are skipped. Sparse PDF pages are described visually, and videos with no transcribable speech fall back to representative frame descriptions.

If one source is password-protected or irreparably damaged, the runner still exports every successfully processed document and exits with a failure status so the excluded source remains visible in `knowledge:status`. Remove the source password or repair the file, then run the same command again to add it on the next export.

## Monitor or resume

```bash
cd /Users/tajawal/Downloads/rafeeq
./scripts/knowledge-status.sh
tail -f storage/logs/knowledge-local-ingest.log
```

If the run stops, launch the same `run-knowledge-ingest.sh` command again. Use `--force` only when source extraction or chunking must intentionally be rebuilt. An embedding-model change needs only the automatic `--reembed` path.

## Deploy the prepared knowledge base

Deploy this code first, set the server to the same embedding model and dimensions, and run migrations:

```bash
php artisan migrate --force
```

Upload the `.ndjson.gz` bundle and its `.sha256` file. Verify the upload:

```bash
cd /path/to/uploaded/files
sha256sum -c rafeeq-knowledge.ndjson.gz.sha256
```

On macOS, use `shasum -a 256 -c` instead of `sha256sum -c`.

Import the already-generated vectors:

```bash
cd /path/to/rafeeq
php artisan knowledge:import-index /path/to/rafeeq-knowledge.ndjson.gz
php artisan knowledge:status --category=rafeeq-library
php artisan chat-attachments:reembed
```

The knowledge import makes no OpenAI calls. Imported documents are marked `index_only` because the bundle contains searchable text and vectors, not the original multi-gigabyte source files. Search and AI citations work. The final command refreshes older user-uploaded chat attachments with the configured embedding model; use `--queue` on a production server with active queue workers.

## Direct server-side ingestion (optional)

If the original source folder is already on a capable server, the same importer can process it directly:

```bash
php -d memory_limit=2048M artisan knowledge:ingest /srv/knowledge \
  --category=rafeeq-library \
  --process \
  --report=storage/app/knowledge_reports/server-source.json
```

Local build plus bundle import is preferred for shared hosting because OCR, LibreOffice conversion, media transcription, and embedding can run for many hours.
