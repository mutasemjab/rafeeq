# Rafiq — Caregiver AI Support Platform

A Laravel 9 backend for **Rafiq**, an AI-powered application supporting caregivers of children with special needs. Features an RAG-based AI chat system, specialist booking, subscription plans, and a full admin panel.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 9 (PHP 8.0+) |
| Database | MySQL 8 |
| Auth (API) | Laravel Passport (`user-api` guard) |
| Auth (Admin) | Laravel custom `admin` guard |
| AI / LLM | OpenAI GPT-4 via `openai-php/laravel` |
| Embeddings | OpenAI `text-embedding-ada-002` (1536 dims) |
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
AI_CHAT_MODEL=gpt-4o
AI_EMBEDDING_MODEL=text-embedding-ada-002

# OpenAI
OPENAI_API_KEY=sk-...

# Optional: Gemini / Anthropic
GEMINI_API_KEY=
ANTHROPIC_API_KEY=

# Social Login
GOOGLE_CLIENT_IDS=
APPLE_CLIENT_IDS=

# Optional: Web Search (Brave)
WEB_SEARCH_ENABLED=false
BRAVE_API_KEY=
```

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

`POST /auth/social` accepts `provider` (`google` or `apple`), `id_token`, optional `first_name`, `last_name`, and optional `preferred_language`. The backend verifies the provider token, links an existing account when possible, or creates a new user with a free plan and stores the provider link in `social_accounts`.

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
| GET | `/subscription` | Current subscription |
| GET | `/subscription/history` | All subscriptions |

### Notifications
| Method | Endpoint | Description |
|---|---|---|
| GET | `/notifications` | List notifications |
| POST | `/notifications/{id}/read` | Mark as read |
| POST | `/notifications/read-all` | Mark all as read |

---

## RAG Chat Architecture

```
User message
    │
    ▼
[ChatAttachmentSearchService]  ← embeds query → cosine search over chat attachment chunks
    │                                              (scoped to user_id + conversation_id)
    ▼
[KnowledgeSearchService]       ← embeds query → cosine search over knowledge base chunks
    │
    ▼
[ChildContextService]          ← loads child profile + extracted memories
    │
    ▼
[LLM (OpenAI GPT-4o)]         ← system prompt + child context + sources + history
    │
    ▼
Assistant message (with sources[])
    │
    ├── Every 5 msgs  → [UpdateChildMemoryJob]     (extract new facts about child)
    └── Every 10 msgs → [SummarizeConversationJob] (compress history into summary)
```

**Daily limits (Free plan):** 5 AI messages/day — enforced in `ChildChatController` before the service call.

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
| `ProcessKnowledgeDocumentJob` | Admin upload | Extract → chunk → embed knowledge doc |
| `ProcessChatAttachmentJob` | User upload | Extract → chunk → embed chat attachment |
| `UpdateChildMemoryJob` | Every 5 msgs | LLM extracts child facts from conversation |
| `SummarizeConversationJob` | Every 10 msgs | Compresses history into `conversations.summary` |

```bash
php artisan queue:work --tries=3 --timeout=120
```

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
