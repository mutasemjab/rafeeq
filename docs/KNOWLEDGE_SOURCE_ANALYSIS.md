# Rafiq source-library analysis

Analyzed source: `/Users/tajawal/Downloads/rafiq files`

Analysis date: 2026-07-19

## Summary

| Metric | Count |
|---|---:|
| Files scanned | 1,496 |
| Metadata and temporary files | 479 |
| Real content candidates | 1,017 |
| Exact duplicate copies | 307 |
| Empty content files | 1 |
| Unique content files to process | 709 |
| Total folder size | about 4.8 GB |

SHA-256 comparison found that nearly one third of the real candidates duplicate content elsewhere in the library. The importer keeps the first canonical copy and records its relative source path, avoiding duplicate OpenAI cost and duplicate retrieval results.

## Unique files by type

| Type | Unique files | Processing path |
|---|---:|---|
| PDF | 524 | `pdftotext`, bounded parser, Arabic/English OCR, then cached vision fallback |
| PPTX | 107 | Direct slide/notes XML extraction; LibreOffice/PDF/OCR/vision fallback |
| DOCX | 29 | Direct low-memory OpenXML extraction; `textutil` fallback |
| PPT | 21 | LibreOffice conversion to PPTX |
| JPG | 17 | Tesseract Arabic/English OCR, then cached vision description |
| MP4 | 4 | ffmpeg segmentation plus OpenAI transcription |
| DOC | 4 | antiword/catdoc/textutil/LibreOffice fallback chain |
| XLS | 2 | LibreOffice conversion to XLSX, then row extraction |
| FLV | 1 | ffmpeg segmentation plus OpenAI transcription |

The 478 ignored files are `.DS_Store`, `Thumbs.db`, AppleDouble `._*` sidecars, and Office lock files beginning with `~$`; these contain filesystem metadata, not knowledge content. One zero-byte DOCX is also skipped because it contains no data to extract or embed.

## Completed ingestion result

- 708 documents processed successfully into 14,817 searchable chunks.
- Every chunk is embedded with `text-embedding-3-large` at 1,536 dimensions; model-aware validation found zero mixed or malformed vectors.
- The corrected deployment bundle is 123,787,507 bytes (about 118.05 MB) and passed a full clean-database import test.
- Bundle SHA-256: `6143bd2f5674a6a2d51a173d89cc4a939e089ece9c4cdda6b0f171263f599170`.
- One file is excluded: `Ai app/التقييم النطقي واللغوي/افعال رسوم متحركة.ppt`. It is password-protected, and neither its text nor images can be decrypted without the password. Remove its password and rerun the normal ingestion command to include it in a regenerated bundle.

## Environment findings

Available and verified after setup: PHP ZIP/DOM/mbstring/PDO SQLite, `pdftotext`, Ghostscript, Tesseract with Arabic and English data, macOS `textutil`, LibreOffice, and `ffmpeg`. The setup installed the initially missing LibreOffice and `ffmpeg`, so all formats present in this source folder now have a local extraction path.

`antiword` and `catdoc` are optional legacy Word fallbacks and remain unavailable. Existing `.doc` files are covered by `textutil` and LibreOffice. The ingestion preflight checks the actual shell/server environment again before processing.

## Quality and deployment decisions

- The original project command only scanned one storage directory level and supported five formats. It could not ingest this absolute recursive folder.
- The original PDF parser could load large PDFs into PHP memory. The new extractor prioritizes external streaming tools and limits the in-process parser to small PDFs.
- Embeddings were previously requested one chunk at a time and previous chunks were deleted before success. The new job batches requests, stores the embedding model with every vector, and resumes from each stored valid chunk. Model-only upgrades reuse existing chunk text without rerunning extraction or vision.
- The original job merged all pages and assigned the first page to every chunk. New chunks retain their starting and ending page hints.
- The local runner uses its own SQLite database and exports a database-independent compressed bundle, so production MySQL credentials and server runtime limits are not involved in the expensive build.
