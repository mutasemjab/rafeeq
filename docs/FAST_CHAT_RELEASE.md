# Fast chat and premium extra thinking

**Deployment update, 30 October 2026:** this release and the MySQL progress-renewal fix are deployed to `https://rafeequae.com`. See [the production verification report](QA_FAST_CHAT_DEPLOYMENT_2026_10_30_AR.md) for live timings, the additional follow-up test, backup details and remaining content/performance limits. The 29 October measurements below describe the earlier local validation.

The backend now defaults to a lightweight answer model with no reasoning effort. `extra_thinking=true` selects the configured primary model and reasoning budget, subject to an active, started, unexpired Pro subscription on an active plan. The backend checks entitlement both when accepting a new/retried turn and before a queued turn runs. Completed replays remain idempotent. Omitted/false flags preserve legacy request hashes.

Standalone greetings and thanks use deterministic replies after safety triage. A first-turn greeting completes inline even for asynchronous requests, without a queue worker, model request, or maintenance job. Mixed greeting/question messages follow the normal pipeline. Later social turns retain contextual safety checks.

For standalone, low-risk educational questions in a fresh conversation, domain classification also supplies the retrieval plan. The fast answer checks citation membership without a second semantic review call; metadata identifies this as `citation_validation`, with `reviewed=false`. This is a deliberate latency/quality tradeoff, not a claim of independently verified entailment. Personalized advice, sensitive/high-risk topics, correction feedback, established case state, and deep mode retain the fuller workflow and independent answer review. Disable this optimization with `AI_FAST_GENERAL_CITATION_CHECK_ONLY=false`.

## Knowledge retrieval

- Normalized float32 vectors avoid repeatedly transferring/parsing large JSON embeddings. New ingestion/re-embedding/import writes them automatically. Unpacked rows still use the original JSON search path.
- `knowledge:optimize-search` backfills existing vectors, without API calls, source modifications, or re-embedding. It checks the original embedding before updating to avoid overwriting concurrent changes.
- Export/import preserves age scope, approval, language, topics, evidence level and provenance. Legacy bundles do not erase metadata already reviewed on the server.
- General educational questions can retrieve approved legacy references without age metadata. Personalized recommendations retain age-scope restrictions; no bulk `all_ages` assignment is made.
- Arabic normalization and title matching improve reranking. Each query retains a small evidence share, so translated expansions cannot remove every result for the original Arabic question.
- The default semantic candidate threshold is 0.35. The fast-mode web-fallback threshold for general education is 0.40; personalized/deep/current-information requests keep the stricter existing policy.
- `knowledge:status --json` reports packed vectors and documents with/without age scope. Missing scope remains a content-curation requirement for individualized recommendations.

## Deployment

Back up the application database first. Deploy the migration and PHP files together, then:

```sh
php artisan migrate --force
php artisan knowledge:optimize-search
php artisan config:cache
php artisan queue:restart
php artisan knowledge:status --json
```

`scripts/post-deploy.sh` runs the first four steps. The optimization command is resumable and skips packed vectors unless `--force` is used. Its duration depends on the corpus/database; run it before benchmarking user latency.

Recommended settings are in `.env.example`. Keep `AI_ANSWER_*` for deep/careful requests, and use:

```dotenv
AI_FAST_ANSWER_MODEL=gpt-5.6-luna
AI_FAST_ANSWER_REASONING_EFFORT=none
AI_FAST_ANSWER_MAX_OUTPUT_TOKENS=800
AI_FAST_REVIEW_MODEL=gpt-5.6-luna
AI_FAST_REVIEW_REASONING_EFFORT=none
AI_FAST_GENERAL_CITATION_CHECK_ONLY=true
AI_DOCUMENT_SIMILARITY_THRESHOLD=0.35
AI_FAST_WEB_SEARCH_INTERNAL_CONFIDENCE_THRESHOLD=0.40
```

Existing `.env` values take precedence over changed defaults. Keep the dedicated chat worker running as described in README; PHP processing measurements exclude queue waiting. During the initial 29 October validation, the local configured MySQL was unavailable, so tests used the actual library bundle in isolated SQLite with real API calls. Production deployment and external HTTP validation were subsequently completed on 30 October; physical mobile-device latency remains unverified.

## Repeatable validation

```sh
php vendor/bin/phpunit
php -d memory_limit=2048M scripts/benchmark-chat-local.php --live \
  --bundle=storage/app/knowledge_exports/rafeeq-knowledge.ndjson.gz \
  --output=storage/app/knowledge_reports/fast-chat-live.json
```

The benchmark imports the bundle into a disposable in-memory database, uses synthetic conversations and subscriptions, and makes paid API requests only with `--live`. It never connects to the configured application database. It includes greetings, factual questions, an emergency, an unrelated request, deep mode, and three additional colloquial questions. Success status alone is not an accuracy grade: inspect answers and their cited evidence.

The mobile implementation prompt is in `docs/MOBILE_FAST_CHAT_PROMPT_AR.md`.

## Measured verification — 29 October 2026

All **395 automated tests passed (8,920 assertions)**. This includes free/expired/future/cancelled subscription denial, entitlement rechecking in the worker, immediate greetings without model calls or a worker, immutable request modes, mixed greeting/questions, citation fallback, high-risk/deep review, packed-vector equivalence and metadata round trips. A real failure during Cairo's repeated DST hour was also fixed: loaded temporary-conversation expiry checks now use the same stored wall time as the existing SQL expiration scope.

Live tests used the actual bundle (708 documents, 14,817 chunks), synthetic conversations and real API requests. The numbers below include backend acceptance and processing; they exclude library import, mobile/network overhead and queue waiting. They are observations from a small sample, not a latency SLA or a clinical accuracy estimate.

| Check | Before | After |
| --- | ---: | ---: |
| Arabic standalone greeting | 22.520 s | 0.023 s, deterministic |
| Four principles of effective communication | 27.361 s | 8.325 s, cites document 632 |
| Latest five fast educational answers | — | 8.270–10.144 s; median 9.561 s |
| Extra-thinking communication answer | — | 19.239 s, primary model and full review |

The communication answer now identifies quantity, quality, relevance and manner from the supplied library. A multilingual retrieval regression was corrected and rechecked: the Arabic stuttering question now retrieves the intended Arabic source rather than allowing translated-query results to crowd it out. Three additional colloquial questions also returned answers with matching library citations.

The 12-question retrieval diagnostic found the expected document in the top eight results for **10/12**, compared with **4/12** before tuning when age scope was disabled for diagnosis. The original strict age-scope path returned none for this legacy corpus. These are expected-document hits, not an 83% answer-accuracy score. Two cases (`new_skill_prompts`, `echolalia`) still missed their expected document and need source/extraction review. Unscoped references remain excluded from personalized guidance until their age applicability is reviewed.

The first packed-search run included a 114.003-second outlier. Two subsequent complete passes (24 searches, cached query embeddings, no API calls) ranged from **1.062 to 1.381 seconds** and both retained 10/12 expected-document hits. The outlier did not reproduce; its cause is unconfirmed. This is not a concurrent-load test, and production MySQL/worker performance still requires verification.

Raw evidence is saved under `storage/app/knowledge_reports/`:

- `fast-chat-baseline-2026-10-29.json`
- `fast-chat-live-2026-10-29.json` (first live pass, before the final multilingual selection fix)
- `fast-chat-verification-2026-10-29.json` (five final answers, citations and timing)
- `fast-chat-retrieval-after-2026-10-29.json`
- `fast-chat-retrieval-recheck-2026-10-29.json` (two repeated retrieval passes)

Earlier social-answer records have a configured model name in metadata even though the deterministic path made no model call; the final implementation now records `model_name=null` for social replies.

Technical background: [OpenAI latency optimization](https://developers.openai.com/api/docs/guides/latency-optimization) recommends fewer calls, smaller models, shorter outputs, and deterministic handling of constrained responses. Actual latency here is measured rather than assumed from model size.
