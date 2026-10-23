# Conversation fixes and supplied guidance

The mobile client and API changes belong to the same release. Deploy the API before distributing the updated app: the client uses message cancellation, case-document retry, structured reply feedback, and `GET /api/v1/conversations/{id}/summary-draft`. Restart queue workers after updating the backend. These additions reuse existing message metadata, case state, and memory records; no new database migration is required.

## Source mapping

The versioned bundle in `resources/ai/conversation/manifest.json` records the supplied archive/file names and SHA-256 digests. The imported rules describe conversation behavior, not clinical evidence. The source specification's original status is retained; importing it is not clinical validation.

| Input | Implementation |
| --- | --- |
| Arabic and English dialogue trees | `ConversationGuidanceService` route identifiers, action descriptions, and bilingual parity checks |
| Conversation enhancements E01–E05 | Explicit family goal, separately sourced facts, relevant questions, constraints, and reported outcomes in the planner and case state |
| Gateway additions G01–G05 | Concern-first conversation, multiple support domains, optional intake, existing safety/evidence gates, and versioned provenance |
| Blank Mahmoud case-study form | 57 adapted optional bilingual field definitions; blank options never become facts; sensitive history is not mandatory |
| Product features F03/F04 | Reply-specific correction requests and editable, previewed summaries with user-initiated sharing |

Rebuild the deterministic bundle from the supplied files:

```sh
python3 scripts/import-conversation-guidance.py /path/to/downloads resources/ai/conversation
```

## Behavior and contracts

- Existing conversations retain their saved case even when `child_id` is omitted. A failed case-context load remains retryable instead of producing an answer without that case.
- Case answers retain their user-message source and distinguish `reported`, `denied`, `unknown`, `declined`, and `conflicting`. New explicit corrections supersede older answers. Adults do not receive the childhood intake catalogue.
- The planner can extract several explicit fields from one message, reuses already known fields, and asks one useful question at a time. An indispensable gap can require clarification beyond two turns; question count does not establish completeness.
- `implementation_barrier` means the step was not tried; it is not a failed outcome. `stop_plan` clears continued follow-up. No automatic reminder or specialist contact is created.
- A chat request may contain `feedback: {message_id, reason, comment}`. Reasons are `misunderstood`, `too_long`, `repetitive`, or `impractical`; the comment is optional and limited to 1,000 characters. The target must be an assistant reply in the same owned conversation. The source comment and target are retained through retry and message-history restoration.
- Safety triage receives the current comment and selected original reply, including replies outside recent history. A style request cannot by itself establish that an earlier risk resolved. Repair continues through the existing planning, evidence, and answer-quality stages. Feedback does not upload analytics or retrain a model.
- A summary is composed on demand from owned conversation records. It distinguishes dated family statements, the reported goal, assistant proposals, reported attempts/outcomes, unknown information, and the open question. The mobile editor shows a preview before copying or invoking the native share sheet; only the edited text is exported. The draft is not automatically sent or added to a permanent shared document.
- The summary uses a bounded recent message window (100 messages); it is a conversation draft, not a complete clinical history. It does not add profile identity fields, but user-authored text can contain names, which the preview asks the family to review.
- Stopping a turn preserves the user's text. Cancelled or superseded workers cannot persist a late assistant reply. Upload-only messages, inline follow-up questions, file processing/retry controls, and specialist search are supported by the mobile fixes.

## Verification

`php artisan test` covers the full backend suite. Focused regression scenarios are in `FeedbackFixesTest`, `ConversationGuidanceFeaturesTest`, `ChildChatOrchestrationTest`, `ChatTurnPlannerServiceTest`, and `SafetyTriageServiceTest`. They cover ownership, cancellation races, source provenance, unknown/declined answers, old-reply repair, urgent feedback comments, idempotent retries, and summary isolation.

The Flutter tests cover sending and stopping, upload-only messages, restoring failed requests, case documents, specialist search, feedback dialogs, and edit–preview–share behavior. Run `flutter test` and `flutter analyze --no-fatal-infos`; native plugin integration is checked with `flutter build ios --simulator --debug --no-codesign`.

Automated tests use synthetic data and mocked model responses. They verify application behavior and guard wiring; they do not establish live-model response quality or clinical review.
