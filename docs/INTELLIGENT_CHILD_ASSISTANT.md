# Rafiq Intelligent Child Assistant

## Runtime flow

Every message follows this ordered pipeline:

1. Save the caregiver message and load bounded recent history.
2. Run deterministic and model-assisted safety triage.
3. Reject requests outside child development, education, therapy, behavior, communication, and caregiver support.
4. Build the selected child's profile, active durable memories, and recent longitudinal summaries.
5. Plan the turn as one of: answer, ask one focused clarification, or refer to a specialist.
6. Persist explicit, high-confidence caregiver facts as child memory candidates.
7. Generate retrieval queries and search conversation attachments plus the approved knowledge base.
8. Re-rank results using semantic similarity, lexical overlap, topic/problem metadata, language, and child age.
9. Decide whether authoritative web search is needed. Web results are restricted to configured domains.
10. Apply the evidence gate. High-impact child guidance is not generated without retrieved evidence.
11. Send the turn plan, child context, and retrieved evidence to the final answer model through the OpenAI Responses API.
12. Generate one useful next question, persist the case state, and return both visible text and structured follow-up data.
13. On later turns, re-plan the case using the answer, the new observation, and the saved child state.

The user-visible pattern is therefore:

`message -> safety -> scope -> child context -> clarification or analysis -> RAG/web -> answer -> next question -> follow-up`

## Safety behavior

| Situation | System action |
| --- | --- |
| Immediate danger or emergency cue | Stop the normal pipeline and direct the caregiver to local emergency services. |
| Urgent regression or concern requiring prompt review | Recommend timely professional assessment; do not offer a home-only plan. |
| Missing case-changing information | Ask exactly one high-value clarification question. |
| Insufficient approved evidence | State that evidence is insufficient instead of guessing. |
| Possible diagnosis | Explain indicators and assessment options without diagnosing the child. |
| Routine supported guidance | Give one prioritized first step and define what result to observe. |

## Child memory and case state

Durable memories are stored only when they are explicitly reported by the caregiver and meet the configured confidence threshold. Each memory has a stable key, fact status, source message, evidence excerpt, and update history. Inferences and assistant-generated statements are not saved as facts.

Conversation state stores the active domain, risk level, missing information, last action, outcome plan, and next question. The next turn receives this state so answered questions are not repeated.

The API exposes:

- `message.response_type`: `answer`, `clarification`, `specialist_referral`, `urgent_escalation`, or `insufficient_evidence`.
- `message.suggested_questions`: normally zero or one next question for a tappable UI chip.
- `message.follow_up`: question purpose and whether observation time is needed.
- `message.evidence`: internal/web counts and whether hosted web search ran.
- `message.case_state`: the state snapshot used for the response.
- `conversation.next_question`: the current pending question.

The question is also appended to the assistant's visible answer, so older clients still display it.

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
AI_ANSWER_REASONING_EFFORT=low
AI_REQUIRE_RETRIEVED_EVIDENCE=true
AI_OPENAI_WEB_SEARCH_ENABLED=true
AI_OPENAI_RESPONSES_FAIL_OPEN=true
AI_OPENAI_WEB_SEARCH_ALLOWED_DOMAINS=who.int,cdc.gov,nih.gov,medlineplus.gov,nhs.uk,aap.org,healthychildren.org,asha.org,unicef.org,autism.org.uk
AI_MEMORY_MINIMUM_CONFIDENCE=0.78
```

`AI_CHAT_MODEL` is used for bounded structured tasks such as classification and planning. `AI_ANSWER_MODEL` is used for the caregiver-facing answer through the Responses API.

## Deployment

```bash
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
php artisan knowledge:ingest /absolute/path/to/sources --category=general --process
php artisan ai:evaluate-child-assistant --live
```

Review every source's taxonomy and approval status in the admin knowledge area. A source should not be approved merely because extraction succeeded.

## Verification

Run the automated suite and the scenario evaluator before release:

```bash
vendor/bin/phpunit
php artisan ai:evaluate-child-assistant --json
php artisan ai:evaluate-child-assistant --live --json
```

The offline evaluator covers deterministic emergency behavior. Live mode evaluates model-dependent clarification, domain, non-diagnosis, behavior ABC, and follow-up cases. Add anonymized real failure cases to `resources/ai/evals/child_assistant_cases.json` before changing prompts or models.

Monitor at least: escalation rate, unsupported-answer rate, retrieval hit rate, web-search rate, repeated-question rate, memory correction rate, source coverage, response latency, model failures, and caregiver feedback. Do not log raw child content in analytics.
