# Rafiq Intelligent Child Assistant

## Runtime flow

Every message follows this ordered pipeline:

1. Save the caregiver message and load bounded recent history.
2. Run deterministic and model-assisted safety triage.
3. Reject requests outside child development, education, therapy, behavior, communication, and caregiver support.
4. Build the selected child's profile, active durable memories, available case-document excerpts, and recent longitudinal summaries. Check attachment processing status before planning.
5. Plan the current intent as one of: answer, ask one focused clarification, or explain the need for specialist assessment while offering appropriate support. A reported existing diagnosis is context, not a new diagnosis request. A nonurgent referral continues through answer generation; it does not return a fixed refusal.
6. Persist explicit, high-confidence caregiver facts as child memory candidates.
7. When the turn needs clinical/educational evidence or web verification, generate retrieval queries and search conversation attachments plus the approved knowledge base. Pure conversation, app support, or an attributed summary of already-readable case content can skip this work. Explaining clinical implications or recommending interventions still requires authoritative evidence.
8. Re-rank results using semantic similarity, lexical overlap, topic/problem metadata, language, and child age.
9. Decide whether authoritative web search is needed. Web results are restricted to configured domains.
10. Apply the evidence gate. High-impact child guidance is not generated without retrieved evidence. When evidence is unavailable, a referral response may still acknowledge reported facts, explain the assistant's limits, and help organize an appointment; it must not invent treatment claims or a home plan.
11. Send the turn plan, child context, and retrieved evidence to the final answer model through the OpenAI Responses API.
12. Run the final answer through a structured specialist-quality gate that approves, revises, or rejects unsupported content. The reviewer sees case-document context and bounded recent user/assistant history, preserves valid citations, and cannot add new source labels or URLs. Prior assistant replies establish what was said in the conversation, not clinical authority or verified facts about the child.
13. Keep only sources actually cited by the final answer in the user-visible source list.
14. If the planner identifies a useful follow-up, generate it with at most one model request. Drop empty, repeated, or decision-free questions; simplify explicit compound questions locally. Persist the case state and return visible text and structured follow-up data.
15. On later turns, re-plan the case using the answer, the new observation, and the saved child state.

The user-visible pattern is therefore:

`message -> safety -> scope -> child context -> clarification or analysis -> RAG/web -> answer -> next question -> follow-up`

## Safety behavior

| Situation | System action |
| --- | --- |
| Immediate danger or emergency cue | Stop the normal pipeline and direct the caregiver to local emergency services. |
| Urgent regression or concern requiring prompt review | Recommend timely professional assessment; do not offer a home-only plan. |
| Missing case-changing information | Ask exactly one high-value clarification question. |
| Existing reported diagnosis with a request for practical support | Use the supplied facts and give supported help or one necessary clarification; do not refer solely because a diagnosis or disability is mentioned. |
| Frustration after repeated referrals | Acknowledge the specific problem and address the current request. Retain safety and evidence requirements for any clinical advice. |
| Insufficient approved evidence | State that evidence is insufficient instead of guessing. |
| Possible diagnosis | Explain indicators and assessment options without diagnosing the child. |
| Routine supported guidance | Give one prioritized first step and define what result to observe. |

A report that a child does not respond to their name or a verbal instruction is sent to contextual safety classification rather than being equated automatically with unconsciousness. Independent danger signals still trigger immediate escalation, and classifier failures with safety cues still fail safely. Historical, negated, and hypothetical cues go through contextual classification rather than a keyword-only emergency response. Independent present danger retains the fast path, and Arabic self-injury/bleeding cues are included.

## Child memory and case state

Durable memories are stored only when they are explicitly reported by the caregiver and meet the configured confidence threshold. Each memory has a stable key, fact status, source message, evidence excerpt, and update history. Inferences and assistant-generated statements are not saved as facts.

Conversation state stores current facts by canonical key, provenance, superseded values, scoped questions, clarification count, and bounded progress records. A shared case brief is passed to planning, generation, review and follow-up. Explicit caregiver corrections take precedence over older profile/summary values; extracted memory evidence must match the actual owned user message. Earlier consolidation jobs cannot overwrite a newer correction. When a reported age/birth-date fact overrides the profile, retrieval conservatively omits the old profile age hard filter rather than parsing free text or excluding relevant sources. Prior assistant recommendations remain labelled as recommendations, never as caregiver-reported facts or proof of an agreed/attempted treatment. Follow-up distinguishes information available now from a later observation; a new observation round can remeasure a changing behavior without repeating fixed intake questions.

Case-document context is a bounded object containing `trust`, `attachments`, and `child_documents`. Each group includes at most five files and 6,000 characters of excerpts, with file status and explicit content availability. Preview reads stay within the consenting user's current conversation and selected child. Beginning and ending excerpts improve coverage of report conclusions; partial previews must not be described as a full document review.

Child-document extraction runs asynchronously after AI consent, rechecks consent in the job, and stores bounded extracted text without generating embeddings. Durable queues receive jobs normally; synchronous queues defer extraction until after the response. Both private child documents and chat attachments bypass the shared knowledge extraction cache. File deletion removes any exact content-hash legacy cache still matchable to its original; unidentified extracts from missing originals need a separate retention review. Uploads and metadata alone are not proof of readable content. A pending or failed extraction is reported as such when relevant, while the assistant can still use available profile and conversation facts.

Nonessential memory extraction and summary jobs are dispatched after the response when the configured queue is synchronous. Production should use an asynchronous queue worker so these jobs do not occupy web workers after delivery.

The API exposes:

- `message.response_type`: `answer`, `clarification`, `specialist_referral`, `urgent_escalation`, or `insufficient_evidence`.
- `message.suggested_questions`: normally zero or one question directed to the caregiver. Tapping it focuses the answer composer; never copy this question into a user message.
- `message.follow_up`: question purpose and whether observation time is needed.
- `message.evidence`: internal/web counts and whether hosted web search ran.
- `message.case_state`: the state snapshot used for the response.
- `conversation.next_question`: the current pending question.

When present, the question is also appended to the assistant's visible answer, so older clients still display it. A conversation does not need a question on every turn.

## Reliable chat delivery and mobile UX

The mobile client supplies a stable `client_message_id` and `async: true` to `POST /api/v1/conversations/{conversation}/chat`. The server reserves one user message and returns `202` with a `turn` object. `GET /api/v1/conversations/{conversation}/chat/turns/{client_message_id}` returns the same object, including the reviewed `message` when complete. Stages describe actual work (`queued`, `checking_context`, `searching_sources`, `preparing_reply`, `reviewing_reply`, `persisting_reply`); they do not expose internal reasoning. Legacy synchronous clients remain supported.

Retries must reuse the original ID, text and language. A completed turn replays the stored response. A failed turn retries without inserting another user message. Different content for the same ID is rejected. Only one turn can run in a conversation; database uniqueness also permits only one assistant reply per user message. Worker attempts are versioned so an expired job cannot overwrite a newer retry. A persisted response is recoverable even when the worker stops before marking the turn complete. A failed older turn cannot be retried after newer user messages: `409 CHAT_TURN_SUPERSEDED` prompts an explicit new message instead. A queued turn can be cancelled with `DELETE` on its status URL; already-running generation cannot be cancelled by this endpoint.

`GET /api/v1/chat/usage` returns the account-wide daily `used`, nullable `limit` and `remaining`, and `resets_at`. The interface displays these server values rather than counting the current conversation. Message resources include `client_message_id`, `user_message_id`, `delivery_status`, and the original request `language` for recovery.

The Flutter client keeps failed turns visible with retry/edit, renders the returned reply before background history refresh, restores unfinished requests from account-scoped platform secure storage, and clears that storage on logout. Follow-up actions invite an answer without reversing speaker roles. The selected child or general-chat context is visible. Attachment cards expose processing, readiness, retry and removal; opening a file refreshes its expiring URL. Voice recognition produces an editable draft and requires explicit confirmation before sending. Timestamps use device local time; loading/errors are localized; routine referral presentation is distinct from urgent escalation. Conversation history is sorted by recent activity and can load older pages.

Production should use a durable queue (`QUEUE_CONNECTION=database` or Redis) and supervised workers, not `sync`. The sync fallback returns after-response work but still occupies a PHP worker; PHP's single-threaded development server cannot serve progress polls while doing that work. Queue `retry_after` must exceed the job timeout (defaults: 480s and 420s). A crashed turn with no recoverable response becomes retryable after a stale lease (at least 900s); normal progress renews that lease. Secondary memory/summary work is scheduled only after the reply is published and avoids nested termination callbacks.

## Knowledge governance

Knowledge documents can be classified by category, topics, problem types, minimum/maximum age, audience, language, evidence level, publisher, source URL, publication/review dates, and approval state. Retrieval excludes unapproved documents and applies age/language filters where available.

Recommended evidence hierarchy:

1. Approved internal clinical or professional guidance directly matching the case.
2. Relevant user-provided documents, for facts about the child rather than automatic clinical authority.
3. Current authoritative web sources from the allowlist.
4. Stable model knowledge only to explain or connect retrieved evidence, never as the sole authority for diagnosis, treatment, or a child-specific recommendation.

## Production configuration

Set at minimum:

```dotenv
OPENAI_API_KEY=...
AI_PROVIDER=openai
AI_CHAT_MODEL=gpt-5.6-luna
AI_ANSWER_MODEL=gpt-6-astra
AI_ANSWER_REASONING_EFFORT=medium
AI_ANSWER_MAX_OUTPUT_TOKENS=1400
AI_ANSWER_QUALITY_GATE_ENABLED=true
AI_ANSWER_QUALITY_MODEL=gpt-6-astra
AI_ANSWER_QUALITY_REASONING_EFFORT=low
AI_REQUIRE_RETRIEVED_EVIDENCE=true
AI_OPENAI_WEB_SEARCH_ENABLED=true
AI_OPENAI_RESPONSES_FAIL_OPEN=true
AI_WEB_SEARCH_INTERNAL_CONFIDENCE_THRESHOLD=0.68
AI_OPENAI_WEB_SEARCH_ALLOWED_DOMAINS=who.int,cdc.gov,nih.gov,pubmed.ncbi.nlm.nih.gov,medlineplus.gov,nhs.uk,nice.org.uk,aap.org,healthychildren.org,asha.org,aota.org,apta.org,unicef.org,autism.org.uk
AI_MEMORY_MINIMUM_CONFIDENCE=0.78
QUEUE_CONNECTION=database
QUEUE_RETRY_AFTER=480
```

`AI_CHAT_MODEL` is used for bounded structured tasks such as classification and planning. `AI_ANSWER_MODEL` is used for the caregiver-facing answer through the Responses API.

## Private files and processing

Chat attachments and child documents now upload to the `private` disk (`storage/app/private`). The storage migration adds disk/legacy-copy tracking and allows the child-document `processing` state. Existing rows retain `public` until the integrity-checked backfill copies them. Do not expose the private directory or the old child-file paths through the web server.

Resources return five-minute signed `file_url` links for browser opening and authenticated `download_url` routes. Child documents retain the `file_path` response key as a temporary link for client compatibility. Signed links are bearer links: do not log or persist them; refresh the attachment/document list when a link expires. Both download methods check ownership and active parent records. Attachment sources sent to the model retain `attachment_id` but omit download URLs; old public links should not be reused by clients.

The attachment API supports `POST /api/v1/attachments/{attachment}/retry` for an owned failed attachment, with current AI consent. It returns the attachment object with status `uploaded`; repeated retries of a queued/processing/processed item return 409. Statuses are `uploaded`, `processing`, `processed`, and `failed`. `processing_error` contains a safe user-facing message rather than provider errors or filesystem details. Upload and retry dispatch to the configured queue; synchronous queues and queue-outage fallback run after the response, without OCR inside the upload handler. A durable worker remains preferable in production.

Workers recheck consent and active parents before extraction and persistence, discard output when consent is withdrawn or the file is deleted, and bypass shared extraction caches. Attachment deletion removes bytes and derived chunks; child-document deletion removes bytes and extracted metadata. Account deletion also cleans private files and retained legacy copies, including soft-deleted records.

The project-root `.htaccess` denies direct access to private storage, extraction temporary/cache directories, legacy `storage/chat-attachments`, and `storage/children/{id}/documents` paths (including `storage/app/public` and `storage/public` variants). Apache must honor these rules. For Nginx, add equivalent denial rules **before releasing this change**, ensuring no earlier `^~ /storage/` location bypasses them:

```nginx
location ^~ /storage/app/private { deny all; }
location ^~ /storage/app/knowledge-tmp { deny all; }
location ^~ /storage/app/knowledge-extraction-cache { deny all; }
location ~* ^/storage/(?:app/public/|public/)?(?:chat-attachments|children/[^/]+/documents)(?:/|$) { deny all; }
```

Verify an actual previously public child-file URL returns 403/404 and an authorized temporary download succeeds. If public files were exposed through a CDN, another document root, or a different alias, deny/purge those paths there as well. The application cannot revoke a static URL served outside it. Keep originals until the copy and deployment are verified; backfill does not delete production originals.

## Deployment

```bash
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
# A process supervisor must keep workers running, e.g.:
# php artisan queue:work --timeout=420 --tries=1
php artisan private-files:backfill --dry-run
php artisan private-files:backfill
php artisan knowledge:ingest /absolute/path/to/sources --category=general --process
php artisan child-documents:process
php artisan ai:evaluate-child-assistant --live
```

Review every source's taxonomy and approval status in the admin knowledge area. A source should not be approved merely because extraction succeeded.

Apply and verify the static-file denial rules before backfill/release. `private-files:backfill` copies active legacy files, verifies SHA-256, switches the database disk only after verification, and marks retained originals for eventual deletion with their owner record. It is idempotent and reports copy failures without exposing paths or content. A dry run validates/counts eligible files without modifying them.

`child-documents:process` backfills existing uploaded or missing-text child documents only for consenting users. Use `--id=N` for one document or `--retry-failed` when retrying corrected extraction failures. The private-file storage update does require the included migration; extracted document text itself requires no separate schema change.

## Verification

Run the automated suite and the scenario evaluator before release:

```bash
vendor/bin/phpunit
php artisan ai:evaluate-child-assistant --json
php artisan ai:evaluate-child-assistant --live --json
php artisan ai:evaluate-answer-quality --live --json
```

The offline evaluator covers deterministic emergency behavior. Live mode evaluates model-dependent clarification, domain, non-diagnosis, behavior ABC, and follow-up cases. The answer-quality evaluator generates complete answers and grades specificity, grounding, practicality, professional tone, calibration, and citations. Add anonymized real failure cases to both evaluation datasets before changing prompts or models.

Monitor at least: escalation rate, unsupported-answer rate, retrieval hit rate, web-search rate, repeated-question rate, memory correction rate, source coverage, response latency, model failures, and caregiver feedback. Do not log raw child content in analytics.

Provider calls emit `ai.provider.request_completed` with operation, model, schema name, duration in milliseconds, and transport success. Schema names distinguish planning, quality review, and follow-up costs; the event excludes prompts, responses, child details, and exception text. Use production percentiles to verify latency improvements: removing an unnecessary call does not establish a particular response-time guarantee, and offline tests do not measure provider latency.

## Earlier verification snapshot — 2026-10-02 (before the UX review fixes)

- Full automated suite: 222 tests, 994 assertions passed.
- Configured-provider scenario evaluation: 15/15 passed after correcting sparse speech age clarification. A subsequent English clarification language regression was corrected and its targeted live recheck returned “How old is your child?”.
- Live answer generation and quality review: 6/6 synthetic cases passed, including reported autism support, frustration after referral, and a contextual diagnostic-limit response. The frustration case was rechecked successfully after giving the reviewer recent conversation history. These are model-assisted evaluations, not clinical validation.
- Answer generation plus review took roughly 24–59 seconds in the six-case run; one request included a connection timeout and fallback. The later frustration recheck took about 22 seconds. These timings exclude the full API/retrieval/mobile path and are not production latency guarantees.
- Offline safety evaluation passed all 3 deterministic cases; the 12 model-dependent cases are intentionally skipped without `--live`.
- Changes are verified locally; deployment, queue worker restart, configuration cache refresh, and opted-in legacy document backfill remain deployment steps.

## UX repair verification — 2026-10-02

Flutter automated suite: **71 tests passed** in staging and again in the original Flutter project after delivery; analysis of all 31 changed Dart files reported no issues. Coverage includes initial create failure and reopen, lost POST responses, background refresh failure, same-ID retry, completed draft cleanup, child/account isolation, keyboard layout with attachments, and voice draft confirmation/persistence. The 31 reviewed files were copied to the supplied Flutter project after hash checks, preserving existing source changes and making backups.

Backend automated suite: **300 tests, 1,338 assertions passed**. This includes actual async acceptance → real orchestration with a fake provider → completed status → replay, plus crash recovery, stale jobs, consent changes, ownership, private storage, corrections and observation follow-up.

The initial live planning/safety run passed 15/15 cases. Four additional contextual safety cases passed (historical resolved danger, negation, hypothetical education, current uncontrolled bleeding), bringing coverage to 19 scenarios. Eight answer-quality scenarios passed automated checks; manual inspection found an unsupported monitoring timeline/target and an overloaded observation request. Generation and review instructions were tightened and the two affected cases were rechecked successfully, with their final answers inspected. Modified answers now undergo an independent verification pass; unavailable review is not labelled as approval. These synthetic evaluations are not a clinical validation or a production latency benchmark.

The original eight-case answer/review run took approximately 8–45 seconds, excluding API retrieval and mobile delivery. The two targeted final rechecks took about 12 and 21 seconds. Streaming progress and immediate display remove avoidable UI waiting; provider response time remains variable.

Both database migrations must be applied before release. Rebuild and distribute the mobile app after deploying the compatible backend. No production deployment, database migration, legacy backfill, CDN change, or device-store release was performed during local implementation. Previously orphaned private extraction caches without an existing source file need a separate retention cleanup; cache/static paths are blocked, and deleting a known file removes its exact matching cache.
