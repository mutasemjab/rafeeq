# All ages support implementation

The backend now supports owned person profiles for children, adolescents, adults and older adults alongside the existing child APIs. Conversations can select a person profile, use source-bound person memories, and plan questions against the supplied bilingual gateway and 39 draft pathways. Two additional **authored intake** pathways cover hearing and reported Down syndrome support needs. These additions are not supplied clinical protocols.

The backend and Flutter client now implement person selection, profile editing, separate consent choices, temporary conversations, private person documents, and person-linked appointments. The backend migrations and queue configuration were deployed and verified on https://rafeequae.com on 2026-10-08. Android 1.1.0+5 was built locally. Flutter source upload remains blocked by GitHub repository access; no store release was performed. The registry remains **draft routing content**, not approved treatment evidence. No draft yes/no branch automatically executes a therapy.

## API contract

All routes below require the existing Passport `user-api` authentication. Sending chat also requires account-level AI consent through `/api/v1/user/ai-consent`, as before.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/v1/person-profiles` | Paginated profiles owned by the authenticated user |
| POST | `/api/v1/person-profiles` | Create a persistent person profile with explicit permission and separate processing/storage choices |
| GET | `/api/v1/person-profiles/{id}` | Read an owned profile |
| PUT or PATCH | `/api/v1/person-profiles/{id}` | Update profile details; ownership, relationship and child link cannot be reassigned |
| POST | `/api/v1/person-profiles/{id}/consent` | Revoke or explicitly restore person-level AI processing consent |
| GET | `/api/v1/person-profiles/{id}/memories` | Paginated active, source-bound memories for this person |
| DELETE | `/api/v1/person-profiles/{id}` | Permanently remove the person, person conversations including soft-deleted ones, messages, turns, memories and their conversation attachments |
| GET | `/api/v1/support-pathways` | Read the bilingual routing catalogue with origin and draft status |

Profile creation accepts:

```json
{
  "display_name": "Optional display name",
  "relationship": "self",
  "age_months": 480,
  "preferred_language": "ar",
  "has_permission": true,
  "has_persistence_consent": true,
  "has_ai_consent": true,
  "consent_version": "1.0"
}
```

`relationship` is `self` or `caregiver`. `has_permission` is an explicit attestation, not proof that every country's consent requirements have been met. That review remains a release requirement. Names are optional. Age may be unknown; an optional `birth_date` in `YYYY-MM-DD` takes precedence over `age_months`. The UI age group boundaries are under 13, 13–17, 18–64 and 65 or older; they are routing categories, not clinical diagnostic thresholds or jurisdiction-specific consent rules.

`reported_diagnosis` and `diagnosis_source` (`self_report`, `caregiver_report`, `professional_report`) preserve attribution. They do not confirm a diagnosis. Optional `communication_preferences` supports the person's preferred way of communicating.

This persistent-profile API requires storage consent. A rejected storage choice returns 422 without creating a profile; it does **not** silently create a temporary session. Temporary conversations use the separate retention-aware API described below. Deleting the profile is the implemented way to remove its stored person data. Revoking AI processing consent preserves owned history for viewing but blocks new processing. Existing child data linked through `legacy_child_id` remains controlled by the existing child APIs and is not deleted when the additional person profile is deleted.

To create a conversation:

```json
{
  "person_profile_id": 12,
  "title": "Optional title",
  "source": "text"
}
```

Send this to the existing `POST /api/v1/conversations`. Do not send `child_id` together with `person_profile_id`. For an owned legacy child link, the server fills the existing `child_id` for compatibility. Otherwise it remains null. `ConversationResource` adds `person_profile_id`; existing chat and turn endpoints keep their URLs and response shapes.

Restore person AI consent with `has_ai_consent: true`, `has_permission: true` and `consent_version`. Revoke with `has_ai_consent: false`. Permission checks occur before saving a new chat turn, when queued work starts, at progress boundaries including reply persistence, and before/after background extraction or summarisation. They cannot retract data already sent during a provider call.

## Routing and observation state

`resources/ai/support_pathways.json` preserves the source filenames, SHA-256 hashes, bilingual nodes, draft branch text and reference links. The application only sends the routing catalogue and relevant **questions** to the planner. Draft home support and transition prose never enter the treatment evidence context.

`SupportPathwayEngine` prioritises the current problem over older routes and lets several routes coexist. The planner may select up to four routes per turn. It receives unanswered candidate questions, and known profile age/consent questions are omitted. Answer selection validates IDs on the server. Gateway G11, G13 and H remain internal decisions and cannot be selected as literal clarification questions.

The planning schema adds:

- `pathway_ids`: known exploration route IDs, not diagnostic outputs.
- `question_node_id`: an eligible unanswered question or null for a free-form clarification.
- `node_answers`: up to six latest-user observations with a question ID, status, value and an exact quote from the latest message.

The answer states are `yes`, `no`, `reported`, `unknown`, `declined` and `conflicting`. Unasked nodes have no entry. Unknown and declined answers are not converted to no or automatically asked again. A later explicit correction supersedes the answer, preserves bounded correction history and marks prior support decisions for review. State records source message IDs. Quote matching and schema checks establish provenance constraints; they do not prove a model's clinical interpretation is correct.

Conversation state exposes `pathway_state` through the existing response metadata. Asking one focused question remains the default. The broader narrative question history and observation rounds remain available to prevent repeated paraphrases. No question checklist must be completed before general educational support.

`AI_MAX_RETRIEVAL_QUERIES` independently bounds evidence queries (default 4). The one-question conversation policy does not reduce retrieval to one source query or discard multilingual queries for several reported needs.

The planner continues to use the existing provider JSON schema interface. The implementation follows [OpenAI Structured Outputs documentation](https://developers.openai.com/api/docs/guides/structured-outputs) and adds application checks for IDs, bounded arrays, evidence quotes and eligible nodes. Structured output does not replace those semantic checks.

## Person memory and evidence

Person memories are separate from `child_memories`. The existing source-validation and correction logic is reused, with an owned user message from a conversation belonging to the same person required for persistence. Assistant output cannot create a person fact. Person consent and account consent are checked before writing. Delayed older messages cannot overwrite newer corrections. The case context only loads that person's memories and prior conversations.

Legacy stable keys such as `child.age` remain canonical internally for compatibility; `person.age` aliases map to the same field **inside that person's memory store**. The key does not move information between people or imply the subject is a child.

`AI_REQUIRE_AGE_SCOPED_EVIDENCE=true` requires an approved document with explicit age scope across chat retrieval, including legacy child conversations. Numeric age bounds filter applicable documents. Unscoped legacy documents are excluded from person retrieval. With unknown or corrected-but-untyped age, only explicitly `audience=all_ages` documents with no age bounds are eligible; no child protocol is inferred valid for an adult. Age fields must still be reviewed accurately by knowledge administrators. Authoritative web retrieval remains the existing evidence fallback, with model and answer quality checks enforcing age and source matching.

## Source regeneration and verification

Regenerate the supplied draft package without a model call or production database:

```bash
python3 scripts/import-support-pathways.py \
  /absolute/path/diagnosis_decision_trees_Arabic_English.zip \
  /absolute/path/assessment_gateway_Arabic_English.docx \
  resources/ai/support_pathways.json

php artisan support:validate
php artisan support:validate --json
php vendor/bin/phpunit
```

The importer uses only Python's standard library and treats source Word content as data. The registry validates IDs, bilingual text and draft status; it does not approve clinical transitions or sources. `support:validate` explicitly reports `release_ready=false` and the outstanding review/product items.

Tests cover profile ownership, all age groups, separate consent choices, deletion/account cleanup, source-bound memory corrections, first-person safety cues, age-filtered evidence, the real two-turn chat graph with a fake provider, and revocation between acceptance and queued execution. The routing matrix exercises all 41 catalogue routes over four age groups and two languages. These are technical regression tests. Separate live synthetic provider scenarios and deployment checks are recorded in `QA_RELEASE_REPORT_2026_10_08_AR.md`; neither establishes clinical validation.

## Temporary conversations, documents and appointments

`POST /api/v1/conversations` accepts `is_temporary=true` and an optional `temporary_subject` with age, relationship, communication preferences, attributed reported diagnosis and explicit permission/AI consent. It cannot also select a persistent person or child. It creates no person profile. Persistent storage consent is not required for this temporary subject; the UI explains the one-hour server retention before creation. Temporary conversations are hidden from history. Their drafts are not persisted by Flutter. They cannot write durable person/child memories or summaries.

`expires_at` immediately prevents chat, polling, attachment access and signed downloads at expiry. `php artisan conversations:purge-expired` physically deletes expired conversations, messages, turns, files and chunks. The chat worker script invokes this command every minute, before acquiring its worker lock. A separate short-lived daily usage counter preserves the free quota after content deletion; it stores no message text and is removed after two days. Expiry is not a promise to retract data already transmitted to an AI provider. Infrastructure logs and provider retention remain governed by their respective policies.

Person document endpoints under `/api/v1/person-profiles/{id}/documents` support GET/list, POST/upload, DELETE/{document}, and POST/{document}/retry. Ownership applies throughout. Files live on private storage, use five-minute signed URLs, and have bounded extracted context. Uploading without AI consent does not start extraction. Restoring consent schedules pending documents; jobs recheck consent before and after extraction and avoid reprocessing an already completed document. Person/account deletion removes their files and caches. A reported diagnosis in a document remains attributed, not independently confirmed.

Appointment create/update accepts an owned `person_profile_id`. Simultaneous non-null person and child IDs are rejected. Selecting one clears the other; explicit null supports clearing the subject. Flutter carries the selected person through booking, payment and appointment editing.

Changing an age invalidates stale age facts, summaries and affected conversation decisions, and cancels queued/processing turns with `CASE_CONTEXT_CHANGED`. Prior affected replies stay visibly marked as superseded history. Old queued messages cannot overwrite the corrected profile age.

## Latency and review controls

The final answer review also produces the optional follow-up, eliminating a separate sequential model request on the normal path. Revised answers still undergo independent verification. A revision with an unknown citation gets at most one constrained citation repair, then the same exact URL/source checks and verifier. Another invalid citation, a rejected repair or a failed verification cannot be delivered as a verified answer.

`AI_ROUTINE_ANSWER_MODEL` may choose a faster configured answer model for routine turns. High-risk, medication, complex psychiatric, referral and post-referral turns keep the primary answer model. `AI_SKIP_EMPTY_RETRIEVAL_EMBEDDINGS=true` skips embedding calls only when no eligible internal documents or processed conversation attachments exist; it does not skip the internal eligibility check, web evidence requirement or answer review. Logs record provider duration and model, not retrieved evidence excerpts.

## Deployment and remaining review

A database, code and environment backup was created outside the web root before migrations. Three all-ages migrations ran successfully. Deployment uses `scripts/post-deploy.sh` to migrate, cache config and restart queues. The existing Hostinger cron starts both the default document worker and dedicated chat worker. Production `QUEUE_RETRY_AFTER=1020` exceeds the existing default worker timeout of 900 seconds.

The old child API remains available; no automatic child-profile backfill is performed. A client may explicitly create an owned legacy child link. Preserve unrelated server folders during deployment.

`support:validate` continues to report `release_ready=false` for clinical pathway review, age-matched evidence coverage and clinical example review. At deployment, the 709 processed knowledge documents had no reviewed age scope, so these legacy documents were excluded from clinical chat evidence. Authoritative hosted web sources remain the fallback. Do not automatically approve documents or label age ranges simply to increase retrieval coverage.

Live QA uses disposable synthetic accounts and deletes only those accounts. It checks response completion, multi-turn behavior, timing, consent and private document processing. Clinical specialists must review the supplied/authored pathways and examples before treating the assistant as clinically validated. The Flutter repository access blocker and platform build limits are documented in the release report.
