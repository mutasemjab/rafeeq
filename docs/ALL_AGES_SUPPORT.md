# All ages support implementation

The backend now supports owned person profiles for children, adolescents, adults and older adults alongside the existing child APIs. Conversations can select a person profile, use source-bound person memories, and plan questions against the supplied bilingual gateway and 39 draft pathways. Two additional **authored intake** pathways cover hearing and reported Down syndrome support needs. These additions are not supplied clinical protocols.

This is the first backend implementation milestone. The registry is **draft routing content**, not approved treatment evidence. No draft yes/no branch automatically executes a therapy. The mobile person-selection interface, ephemeral conversation mode, standalone person-document library, person-linked appointment flow, and clinical content review are still outstanding. Existing conversation attachments work with person conversations through their conversation ownership and consent checks. No production migration, deployment, or store release has been performed.

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

This persistent-profile API requires storage consent. A rejected storage choice returns 422 without creating a profile; it does **not** silently create a temporary session. Temporary conversations require a separate retention-aware implementation. Deleting the profile is the implemented way to remove its stored person data. Revoking AI processing consent preserves owned history for viewing but blocks new processing. Existing child data linked through `legacy_child_id` remains controlled by the existing child APIs and is not deleted when the additional person profile is deleted.

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

Person evidence retrieval requires an approved document with explicit age scope. Numeric age bounds filter applicable documents. Unscoped legacy documents are excluded from person retrieval. With unknown or corrected-but-untyped age, only explicitly `audience=all_ages` documents with no age bounds are eligible; no child protocol is inferred valid for an adult. Age fields must still be reviewed accurately by knowledge administrators. Authoritative web retrieval remains the existing evidence fallback, with model and answer quality checks enforcing age and source matching.

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

Tests cover profile ownership, all age groups, separate consent choices, deletion/account cleanup, source-bound memory corrections, first-person safety cues, age-filtered evidence, the real two-turn chat graph with a fake provider, and revocation between acceptance and queued execution. The routing matrix exercises all 41 catalogue routes over four age groups and two languages. These are technical regression tests; they are not live provider testing or clinical validation.

## Deployment and remaining work

Before deploying this code, back up the database and test the new `2026_10_08_000001_add_person_profiles.php` migration in staging. Deploy compatible files, apply migrations, refresh configuration and restart queue workers using the existing deployment process. The old child API remains available; no automatic child-profile backfill is performed. A client may explicitly create an owned legacy child link.

Next product work includes the actual mobile person-profile UI and its consent flow, ephemeral conversations and expiry across messages/queues/logs, standalone person documents, and person-linked appointments. Clinical reviewers must verify all supplied and authored questions, resolve draft transition ambiguity, and build an age-matched evidence coverage inventory before treating the full product as release-ready. A full release also needs the live model scenario evaluation, privacy review for the operating countries and post-deployment checks.
