# Rafiq — Caregiver AI Support Platform

A Laravel 9 backend for **Rafiq**, an AI-powered application supporting caregivers of children with special needs. Features an RAG-based AI chat system, specialist booking, subscription plans, and a full admin panel.

The first all-ages backend milestone adds owned person profiles, consent-aware conversations and memories, and bilingual draft support-pathway routing alongside the existing child APIs. See [All ages support implementation](docs/ALL_AGES_SUPPORT.md) for the API contract, verification, migration and remaining release work. Draft pathways are question/navigation data, not approved treatment evidence.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 9 (PHP 8.0+) |
| Database | MySQL 8 |
| Auth (API) | Laravel Passport (`user-api` guard) |
| Auth (Admin) | Laravel custom `admin` guard |
| AI / LLM | OpenAI `gpt-5.6-sol` with a strict `gpt-5.6-luna` Rafiq-domain gate |
| Embeddings | OpenAI `text-embedding-3-large` shortened to 1536 dims |
| Vector Search | PHP cosine similarity over MySQL `LONGTEXT` |
| PDF Parsing | `smalot/pdfparser` |
| DOCX Parsing | `phpoffice/phpword` |
| Queues | Laravel Queues (database driver) |
| Admin UI | Bootstrap 5, Font Awesome 6 (RTL/LTR) |

---

## Requirements

- PHP >= 8.0 with extensions: `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `xml`, `zip`
- MySQL >= 8.0
- Composer 2
- OpenAI API key

---

## Installation

```bash
# 1. Clone and install dependencies
git clone <repo-url> rafeeq
cd rafeeq
composer install

# 2. Copy environment file
cp .env.example .env
php artisan key:generate

# 3. Configure your .env (see Environment Variables section)

# 4. Run migrations and seeders
php artisan migrate
php artisan db:seed

# 5. Install Passport keys
php artisan passport:install

# 6. Create storage symlink
php artisan storage:link

# 7. Start queue worker (separate terminal)
php artisan queue:work
```

---

## Updating production

Back up the database, deploy the application files, and run
`bash scripts/post-deploy.sh` from the project directory. This applies pending
migrations, refreshes cached environment settings, and restarts queue workers.
Deploying PHP files alone is insufficient: chat requires the `chat_turns` table
and the `messages.reply_to_message_id` migration. Check
`php artisan migrate:status` after deployment.

For Hostinger, keep the existing default-queue worker for document processing.
Add a Custom cron job every minute with this command (adjust the project path):

```bash
/bin/bash /home/USER/domains/DOMAIN/public_html/scripts/run-chat-worker.sh
```

Once that cron job is installed, set `AI_CHAT_QUEUE=chat`,
`AI_CHAT_QUEUE_TIMEOUT=120`, and `QUEUE_RETRY_AFTER=480` in production `.env`,
then run the post-deployment script. The dedicated worker waits for new chat
jobs, uses a lock to avoid duplicate workers, and renews periodically within
the hosting process limit. Inspect `storage/logs/chat-worker.log` and
`php artisan queue:failed` when troubleshooting. Without this worker, leave
`AI_CHAT_QUEUE=default`.

## Environment Variables

Add the following to your `.env`:

```env
# App
APP_URL=http://localhost/rafeeq/public

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rafeeq
DB_USERNAME=root
DB_PASSWORD=

# AI Provider
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
AI_MAX_SOURCE_CONTEXT_CHARS=3600
AI_CONSENT_VERSION=1.0
AI_PROVIDER_NAME=OpenAI
PRIVACY_POLICY_URL=

# OpenAI
OPENAI_API_KEY=sk-...

# Optional: Gemini / Anthropic
GEMINI_API_KEY=
ANTHROPIC_API_KEY=

# Social Login
GOOGLE_CLIENT_IDS=
APPLE_CLIENT_IDS=
APPLE_BUNDLE_ID=

# Optional: Web Search (Brave)
WEB_SEARCH_ENABLED=false
BRAVE_API_KEY=
```

For Apple Sign In on native iOS, the backend must accept the bundle identifier
from the token `aud` claim. For this app that value is `com.rafiq.user`, so set
either `APPLE_CLIENT_IDS=com.rafiq.user` or `APPLE_BUNDLE_ID=com.rafiq.user`
on the server before testing Apple login.

---

## Database Schema (22 tables)

| Table | Purpose |
|---|---|
| `users` | App users (caregivers) |
| `social_accounts` | OAuth provider links |
| `password_otps` | OTP for password reset |
| `user_devices` | Push notification tokens |
| `plans` | Subscription plan definitions |
| `subscriptions` | User subscriptions |
| `payments` | Polymorphic payment records |
| `children` | Child profiles (soft delete) |
| `child_documents` | Uploaded child documents |
| `conversations` | AI chat sessions (soft delete) |
| `messages` | Individual chat messages with sources JSON |
| `child_memories` | LLM-extracted long-term child facts |
| `knowledge_documents` | Admin-uploaded global knowledge base |
| `knowledge_document_chunks` | Embedded chunks (LONGTEXT embeddings) |
| `chat_attachments` | Per-conversation file attachments |
| `chat_attachment_chunks` | Embedded chunks for attachments |
| `specialists` | Specialist profiles |
| `specialist_availabilities` | Weekly availability slots |
| `appointments` | Booked sessions |
| `specialist_reviews` | One review per completed appointment |
| `rafiq_notifications` | In-app notifications |
| `admin_activity_logs` | Admin audit trail |

---

## API Reference

**Base URL:** `{APP_URL}/api/v1`

**Authentication:** `Authorization: Bearer {token}` (Passport access token)

### Auth
| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | — | Register + auto-assign free plan |
| POST | `/auth/login` | — | Login, returns token |
| POST | `/auth/social` | — | Google / Apple login with ID token |
| GET | `/auth/me` | ✓ | Current user + subscription |
| PUT | `/auth/profile` | ✓ | Update profile |
| POST | `/auth/logout` | ✓ | Revoke token |
| GET | `/user/ai-consent` | ✓ | Get AI consent status |
| POST | `/user/ai-consent` | ✓ | Save or clear AI consent |
| DELETE | `/user/account` | ✓ | Permanently delete the authenticated account |

`POST /auth/social` accepts `provider` (`google` or `apple`), `id_token`, optional `first_name`, `last_name`, and optional `preferred_language`. The backend verifies the provider token, links an existing account when possible, or creates a new user with a free plan and stores the provider link in `social_accounts`.

`GET /user/ai-consent` returns the current consent state as `{ success, data: { hasAiConsent, acceptedAt, version } }`.

`POST /user/ai-consent` expects `{ "hasAiConsent": true|false, "version": "1.0" }`. When `hasAiConsent` is `true`, the API stores the acceptance timestamp and version. When it is `false`, the API clears previously stored consent.

`DELETE /user/account` deletes the authenticated user's account and associated personal data inside a database transaction, removes stored user-uploaded files, deletes OAuth/device/session artifacts when present, and invalidates existing API tokens. Mobile clients should clear local storage and force re-login after a successful deletion response.

### Children
| Method | Endpoint | Description |
|---|---|---|
| GET | `/children` | List own children |
| POST | `/children` | Create child |
| GET | `/children/{id}` | View child |
| PUT | `/children/{id}` | Update child |
| DELETE | `/children/{id}` | Delete child |
| GET | `/children/{id}/documents` | List documents |
| POST | `/children/{id}/documents` | Upload document |
| GET | `/children/{id}/memories` | List memories |

### Chat
| Method | Endpoint | Description |
|---|---|---|
| GET | `/conversations` | List conversations |
| POST | `/conversations` | Start conversation |
| GET | `/conversations/{id}` | Get with messages |
| DELETE | `/conversations/{id}` | Delete |
| POST | `/conversations/{id}/chat` | **Send message (RAG AI reply)** |

### Attachments
| Method | Endpoint | Description |
|---|---|---|
| POST | `/attachments` | Upload file to conversation |
| GET | `/conversations/{id}/attachments` | List attachments |
| DELETE | `/attachments/{id}` | Delete attachment |

### Specialists & Appointments
| Method | Endpoint | Description |
|---|---|---|
| GET | `/specialists` | Browse (filter: language, specialization) |
| GET | `/specialists/{id}` | View specialist |
| GET | `/specialists/{id}/availabilities` | Weekly slots |
| GET | `/specialists/{id}/reviews` | Published reviews |
| POST | `/appointments` | Book appointment |
| GET | `/appointments` | List own appointments |
| GET | `/appointments/{id}` | View appointment |
| PUT/PATCH | `/appointments/{id}` | Edit own upcoming appointment |
| POST | `/appointments/{id}/cancel` | Cancel |
| POST | `/reviews` | Submit review (completed only) |

`POST /appointments` supports an optional `payment_method`. Use `card` for the normal flow. For testing only, `pay_for_later` can be enabled with `PAY_FOR_LATER_ENABLED=true`; when selected, the API creates a pending test payment record and confirms the appointment immediately.

`PUT/PATCH /appointments/{id}` lets the appointment owner update `child_id`, `appointment_type`, `scheduled_date`, `start_time`, `end_time`, `timezone`, and `notes` while the appointment is still `pending_payment`, `confirmed`, or `upcoming`. The specialist and payment method stay unchanged.

### Plans & Subscriptions
| Method | Endpoint | Description |
|---|---|---|
| GET | `/plans` | Public plan list |
| GET | `/settings` | Public mobile app settings |
| GET | `/subscription` | Current subscription |
| GET | `/subscription/history` | All subscriptions |

`GET /settings` returns payment availability flags for the mobile app. The response includes `payments.mobile_enabled`, `payments.pay_for_later_enabled`, and `payments.available_methods`, so the app can hide or show payment UI based on dashboard-controlled settings.

The same settings response now includes `privacy`, which summarizes what data is collected, why it is used, which third-party AI provider is used, the current AI consent version, and that AI features stay blocked until consent is accepted.

### Notifications
| Method | Endpoint | Description |
|---|---|---|
| GET | `/notifications` | List notifications |
| POST | `/notifications/{id}/read` | Mark as read |
| POST | `/notifications/read-all` | Mark all as read |

---

## RAG Chat Architecture

```text
Accept and persist the user message (consent, ownership, quota, request ID)
  → Safety triage / social reply / subject boundary
  → Person context, case documents, conversation state and turn planning
  → Search conversation attachments and approved internal references
  → Assess whether the actual book passages cover the question
      ├─ Sufficient → answer from those passages; no web search
      └─ Incomplete → deeper internal search:
           alternative queries + wider semantic candidates + normalized text
           search across eligible chunks + adjacent passages
           → reassess coverage
               ├─ Sufficient → answer from the library
               ├─ Missing personal prerequisite → one focused clarification
               └─ Missing reference knowledge → web may fill the identified gaps
  → Generate a faithful, cited explanation
  → Semantic quality review (including fast general answers)
  → Persist answer, source attribution and evidence metadata
  → Return completed reply; dispatch eligible memory/summary maintenance
```

Internal references are the primary answer authority. Similarity scores rank
retrieval candidates; they no longer authorize web fallback. A high-risk flag,
planner web hint or definition match cannot bypass passage coverage assessment.
Explicit requests for current information are checked against the dates/content
of the internal evidence. Partial library support is retained when web evidence
fills a gap. An assessment outage fails the turn instead of silently using web.

The coverage classifier must select existing, numbered excerpts from retrieved
passages; those IDs resolve directly to the original text, including OCR artifacts. The answer reviewer checks their meaning, not only citation membership;
rewritten answers retain the existing independent verification. Case attachments
remain private, conversation-scoped reported information, not automatic clinical
authority. Approval, deletion, processing and age-scope filters apply to both
semantic and deeper text retrieval.

Personalized chat also searches approved books whose age/audience metadata has
not been recorded. Missing metadata does not make a book universally applicable:
the passage assessor and reviewer check the actual population, prerequisites and
limits, while explicit age exclusions remain enforced. Personalized coverage uses
the configured quality model with low reasoning effort and carries unresolved
gaps into the second assessment. This avoids asking for a program's target skill
as though it were a prerequisite.
Personalized fast answers also use at least low reasoning, with extra token
capacity for that reasoning. When the books cover the answer, drafting uses the
configured primary answer model to reduce unsupported additions that would need
a rewrite and a second verification. Their review still uses the configured
quality model, with independent verification of every revision. The concise
response limit, search coverage and evidence checks remain unchanged.
General factual answers keep the faster settings.
A passage-backed question about an unknown personal ability can be returned after
both searches; internet research cannot establish that ability. This clarification
is recorded in conversation state so the next reply can build on the observation.

When the library has a verified gap and the answer cites web sources, the
independent reviewer uses its own required hosted web lookup to verify those
pages. Hosted citations can contain only a URL, so a separate text-only reviewer
must not treat the missing excerpt as missing evidence or approve it from model
memory. The structured review uses Responses `text.format`, validates the JSON
schema, and requires a completed web tool call; provider failures do not fall
back to an unsearched review. Library-supported answers keep web access disabled
during review. Web-assisted review adds a lookup to the fallback path only.
Hosted answers are prompted to prefer readable official HTML/full-text pages so
the independent reviewer can reopen the exact citation; an unreadable PDF or a
search snippet alone is not treated as verified evidence.

AI costs are recorded per provider request in `ai_request_usages`, linked to the
user's question and attempt. This includes coverage, drafting, review, revision
verification, embeddings and question-triggered background maintenance. Reports
contain counters and identifiers, never question text, book text or API keys.
Account/conversation/message deletion removes the identifying foreign-key links;
anonymous cost counters remain. Synthetic QA is tagged separately.
Standalone document vision/transcription ingestion is outside this per-question
report; it is separate from chat retrieval and must still be reconciled with the
provider invoice when calculating total project spend.

`php artisan ai:cost-report --days=7 --by=question --json` reports each question;
use `--by=stage` or `--by=day` to compare stages or dates. Evaluation requests are
excluded unless `--include-evaluations` is supplied. Costs are USD estimates from
provider usage and the versioned rates in `config/ai_costs.php`, not invoices.
Cached reads, 30m/12h writes, output (including reasoning), service tier and web
search actions are counted without double charging. Unknown models, unavailable
usage and ambiguous tool counts remain unpriced; reports show an incomplete
subtotal, never a falsely complete zero. Historical costs cannot be backfilled
from response duration or the old final-answer-only token fields.

`AI_OPTIMIZE_PROMPT_CACHE=true` caches the unchanged instruction prefix explicitly
for 30 minutes on supported models, avoiding cache-write charges on the changing
question/reference suffix. All models, passage limits, search passes and review
checks remain unchanged. Raw Chat Completions usage is read directly because the
installed SDK discards cache accounting details. Requests select the standard
service tier. Set the flag to `false` for a measured baseline or rollback.

Automated tests block unmocked HTTP calls. The optional, paid
`scripts/benchmark-ai-cost.php --live --bundle=... --output=...` runs only two
synthetic questions in two variants, against an isolated imported index. It caps
requests at 32, stops on failed checks or unknown usage, and stops before another
request once estimated spend reaches $1.50 (a between-request threshold, not an
invoice ceiling). Separate cache partitions isolate the variants. It does not
run automatically during deployment and never changes the production database.

Retrieval avoids repeated work without reducing search coverage: semantic
scoring still visits every eligible vector, but loads full passages and document
metadata only for the retained candidates. Stable score ties retain their prior
ordering. The deeper lexical search normalizes each passage once for all query
variants and retains the same candidates; all text chunks and adjacent passages
remain eligible. Query embeddings are reused only within the current turn, so
only new alternative query strings require another embedding request. Coverage
assessment, the two internal passes, context limits and independent answer review
remain required. Each query reserves its own strongest semantic matches even when
candidates overlap, so one expansion cannot dominate the whole result set.
Search logs include elapsed time and scanned counts.

Configuration: `AI_KNOWLEDGE_COVERAGE_MODEL` defaults to `AI_CHAT_MODEL`;
`AI_PERSONALIZED_KNOWLEDGE_COVERAGE_MODEL` defaults to the quality/answer model.
`AI_MAX_SOURCE_CONTEXT_CHARS` defaults to 3600 (bounded to 6000 per passage).
The former `AI_FAST_GENERAL_CITATION_CHECK_ONLY` and
`AI_*WEB_SEARCH_INTERNAL_CONFIDENCE_THRESHOLD` settings no longer affect routing.
Web enablement flags still apply. No database migration or re-embedding is needed;
normal configuration-cache refresh and queue-worker restart are required on deployment.

This adds a coverage call and semantic review, plus another search/assessment on
incomplete evidence. It favors reference fidelity over the earlier fastest path.
Retrieval is bounded and model-reviewed; it does not prove an answer is absent
from every book. Unprocessed files, poor extraction or missing age applicability
still require library curation. Production latency and live answer quality must be
measured separately from the isolated regression tests.

Daily allowances are enforced by `ChatTurnService` using `ChatUsageService`.

---

## Subscription Plans

| Feature | Free | Pro |
|---|---|---|
| AI messages/day | 5 | Unlimited |
| Children | 1 | Unlimited |
| Documents/child | 3 | Unlimited |
| Specialist access | — | ✓ |
| Voice mode | — | ✓ |
| Progress reports | — | ✓ |

Seed plans: `php artisan db:seed --class=PlanSeeder`

---

## Admin Panel

**URL:** `{APP_URL}/admin`

| Section | URL | Features |
|---|---|---|
| Dashboard | `/admin/` | Stats + recent users + appointments shortcut |
| Users | `/admin/users` | Full CRUD |
| Children | `/admin/children` | View, soft-delete, restore |
| Specialists | `/admin/specialists` | Full CRUD + avatar |
| Appointments | `/admin/appointments` | View + status update |
| Plans | `/admin/plans` | Full CRUD |
| Subscriptions | `/admin/subscriptions` | View + status update |
| Knowledge Base | `/admin/knowledge` | Upload + reprocess |
| Activity Log | `/admin/activity` | Read-only audit trail |

---

## Knowledge Base Ingestion

Upload documents via admin panel or via CLI:

```bash
# Ingest all files in storage/app/knowledge/
php artisan knowledge:ingest

# Ingest with category, synchronously
php artisan knowledge:ingest --category=autism --sync

# Custom path
php artisan knowledge:ingest my/custom/path
```

Supported formats: **PDF, DOCX, TXT**

---

## Queue Jobs

| Job | Trigger | Purpose |
|---|---|---|
| `ProcessChatTurnJob` | Chat submission | Generate and persist the reply on `AI_CHAT_QUEUE` |
| `ProcessKnowledgeDocumentJob` | Admin upload | Extract → chunk → embed knowledge doc |
| `ProcessChatAttachmentJob` | User upload | Extract → chunk → embed chat attachment |
| `UpdateChildMemoryJob` | Every 5 msgs | LLM extracts child facts from conversation |
| `SummarizeConversationJob` | Every 10 msgs | Compresses history into `conversations.summary` |

```bash
php artisan queue:work --tries=3 --timeout=420
```

The mobile app submits chat turns asynchronously. With `QUEUE_CONNECTION=database`
or `redis`, keep a worker running under your process supervisor and restart it
after deploying PHP changes (`php artisan queue:restart`). Without a running
worker, accepting a message does not generate an answer. `ProcessChatTurnJob`
has its own 420-second timeout; the queue visibility/retry period must exceed it
(the default is 480 seconds). Long document extraction jobs should have separate
worker capacity so they do not block interactive replies.

Unstarted chat turns now return `QUEUE_UNAVAILABLE` after
`AI_CHAT_QUEUE_TIMEOUT` seconds (default 60). Retry retains the original message
ID and quota reservation. A late job from an older attempt cannot process the
new attempt. Polling can recover an answer persisted before a worker crash.

To investigate a slow turn, check its `status`, `stage`, and `updated_at`, then
the `ai.provider.request` timing events (`duration_ms`) in the server log. A
working `/settings` endpoint alone does not verify a running chat worker or
the external AI provider. Validate an authenticated conversation after deploying
both the API and mobile changes.

## Regression checks

```bash
php vendor/bin/phpunit
```

The backend tests use an isolated in-memory SQLite database and fake/mocked AI
providers. Coverage includes authentication, ownership, consent, appointments,
attachments, child profile date/age round trips, asynchronous chat recovery,
missing workers, idempotent retries, and API rate-limit isolation. They do not
measure production AI latency. In the mobile checkout run `flutter test` and
`flutter analyze --no-fatal-infos`, then build each target platform. The mobile
regressions cover lost acknowledgements, transient polling failures, malformed
replies, PHP-compatible multipart updates, and date-based age display.

---

## Postman Collection

Import `postman_collection.json` from the project root into Postman.

1. Create a Postman environment with `base_url` = `http://localhost/rafeeq/public`
2. Run **Register** or **Login** — `{{token}}` is saved automatically via test scripts
3. All 38 requests across 13 folders are ready to use

---

## Security

- All resource endpoints protected by **Laravel Policies** — users can only access their own data
- Chat attachments strictly scoped to `user_id` AND `conversation_id` — never cross user boundaries
- Admin panel uses a separate `admin` guard — API tokens cannot reach admin routes
- Passwords hashed with `bcrypt`
- File uploads validated by MIME type and max size

---

## Project Structure

```
app/
├── Console/Commands/       IngestKnowledgeCommand
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          7 admin controllers (Users, Children, Specialists,
│   │   │                   Plans, Appointments, Knowledge, Subscriptions, Activity)
│   │   └── Api/            14 API controllers
│   ├── Requests/Api/       9 form request classes
│   └── Resources/          13 JSON API resource classes
├── Jobs/                   4 queue jobs
├── Models/                 20+ Eloquent models
├── Policies/               6 authorization policies
├── Providers/
│   ├── AppServiceProvider  DI bindings (LLM, Vector, WebSearch)
│   └── AuthServiceProvider Policy map
├── Repositories/           MysqlVectorSearchRepository
└── Services/
    ├── AI/                 LlmProviderInterface, OpenAiProvider, FakeLlmProvider
    │                       AiProviderManager, ChildChatService, ChildContextService
    ├── Documents/          DocumentTextExtractor, TextChunker
    └── Search/             KnowledgeSearchService, ChatAttachmentSearchService
                            BraveSearchProvider
database/
├── factories/              8 model factories
├── migrations/             22 migration files
└── seeders/                DatabaseSeeder → PlanSeeder, PermissionSeeder
resources/views/
├── admin/                  13 Blade views across 7 sections
└── layouts/admin.blade.php Bootstrap 5 RTL/LTR layout
routes/
├── api.php                 REST API v1 (38 routes)
└── admin.php               Admin web routes
postman_collection.json     38 requests, 13 folders
```
