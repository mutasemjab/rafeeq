<?php

// Synthetic, isolated live evaluation. Never points Laravel at the configured application database.
$options = getopt('', ['live', 'bundle:', 'output:', 'baseline:', 'prior-qa:']);
if (! isset($options['live'], $options['bundle'], $options['output'], $options['baseline'])) {
    fwrite(STDERR, "Usage: php -d memory_limit=2048M scripts/benchmark-reply-latency.php --live --bundle=/path/index.ndjson.gz --output=/path/report.json --baseline=/path/prior-routing-report.json \n");
    exit(2);
}
$bundle = realpath($options['bundle']);
if (! $bundle || ! is_file($bundle)) { fwrite(STDERR, "Bundle not found.\n"); exit(2); }
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('APP_ENV=testing');
putenv('CACHE_DRIVER=array');
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use App\Models\Conversation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\ChatTurnService;
use App\Services\AI\ChildChatService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

// Keep reference/coverage evidence in the private local QA report so failures
// can be compared with the exact passages. This never records credentials.
class LatencyBenchmarkOpenAiProvider extends \App\Services\AI\Providers\OpenAiProvider
{
    public int $requests = 0;
    public bool $budgetStopped = false;
    public float $priorSpend = 0;

    protected function beforeRequest(string $operation, string $model): void
    {
        $spent = $this->priorSpend + (float) DB::table('ai_request_usages')->sum('estimated_cost_usd');
        $unknown = DB::table('ai_request_usages')->whereNull('estimated_cost_usd')->exists();
        // Strict request cap; the USD threshold is checked between calls, not an invoice cap.
        if ($this->budgetStopped || $this->requests >= 20 || $spent >= 0.50 || $unknown) {
            $this->budgetStopped = true;
            throw new RuntimeException('Live evaluation stopped at its request/cost/unknown-usage limit.');
        }
        $this->requests++;
    }

    public array $coverageChecks = [];

    public array $answerCalls = [];

    public function answer(array $messages, array $options = []): array
    {
        $start = microtime(true);
        $result = parent::answer($messages, $options);
        $this->answerCalls[] = ['seconds'=>round(microtime(true)-$start,3),'options'=>$options,'messages'=>$messages,'result'=>$result];
        return $result;
    }

    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        $result = parent::chatJson($messages, $schema, $options);
        if (str_starts_with($options['schema_name'] ?? '', 'rafeeq_knowledge_coverage') || str_starts_with($options['schema_name'] ?? '', 'rafeeq_answer_quality')) {
            $this->coverageChecks[] = ['schema' => $options['schema_name'], 'input' => json_decode($messages[1]['content'], true), 'messages' => $messages, 'response_schema' => $schema, 'options' => $options, 'result' => $result];
        }
        return $result;
    }
}

$output = $options['output'];
if (! is_dir(dirname($output))) mkdir(dirname($output), 0755, true);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null, 'logging.default' => 'single',
    'logging.channels.single.path' => $output.'.log', 'cache.default' => 'array', 'ai_costs.evaluation' => true,
    'ai.adaptive_personalized_routing' => true, 'ai.reference_faithful_drafting' => true]);
DB::purge('sqlite');
if (Artisan::call('migrate', ['--force' => true]) !== 0
    || Artisan::call('knowledge:import-index', ['bundle' => $bundle]) !== 0) {
    fwrite(STDERR, Artisan::output()); exit(1);
}
Bus::fake();

$provider = new LatencyBenchmarkOpenAiProvider(app(\App\Services\AI\OpenAiConfigResolver::class));
$app->instance(\App\Services\AI\Contracts\LlmProviderInterface::class, $provider);
$user = User::factory()->create(['ai_consent_accepted_at' => now()]);
$plan = Plan::factory()->create(['type' => 'pro', 'is_active' => true, 'ai_messages_per_day' => null]);
Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
    'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
$questions = [
    'book_parenting' => 'طفلي عمره سنتين، بيقدر يقعد بهدوء على الحمام دقيقتين وبيفهم وينفذ تعليمة هات الكرة بدون إشارة. بدي أدربه يترك الحفاظة، من وين أبدأ؟',

];
$cases = [];
foreach ($questions as $topic => $message) {
    foreach (['optimized' => true] as $variant => $optimize) {
        $cases[$topic.'_'.$variant] = compact('topic', 'message', 'variant', 'optimize');
    }
}
$partition = bin2hex(random_bytes(8));
$report = ['synthetic' => true, 'database' => 'isolated in-memory SQLite', 'production_verified' => false,
    'bundle_sha256' => hash_file('sha256', $bundle), 'started_at' => now()->toIso8601String(),
    'timing_scope' => 'accept + processing; excludes library import, mobile network and queue waiting', 'cases' => []];
$prior = json_decode(file_get_contents($options['baseline']), true, 512, JSON_THROW_ON_ERROR);
$baseline = $prior['cases']['book_parenting_optimized'] ?? null;
if (($prior['bundle_sha256'] ?? '') !== $report['bundle_sha256'] || !$baseline
    || ($baseline['question'] ?? '') !== $questions['book_parenting']
    || ($baseline['status'] ?? '') !== 'completed'
    || in_array(false, $baseline['technical_checks'] ?? [false], true)) {
    throw new RuntimeException('Baseline must be the verified adaptive-routing case against the identical library.');
}
$baseline['reused_baseline'] = true;
$report['cases']['book_parenting_baseline'] = $baseline;
$report['baseline_source'] = ['report' => realpath($options['baseline']), 'sha256' => hash_file('sha256', $options['baseline']),
    'case' => 'book_parenting_optimized', 'baseline_spend_excluded_from_new_qa_total' => true];
if (isset($options['prior-qa'])) {
    $priorQa = json_decode(file_get_contents($options['prior-qa']), true, 512, JSON_THROW_ON_ERROR);
    if (($priorQa['bundle_sha256'] ?? '') !== $report['bundle_sha256']
        || !isset($priorQa['total_requests'], $priorQa['total_estimated_usd'])) {
        throw new RuntimeException('Prior QA must have complete cumulative accounting and the identical index.');
    }
    $provider->requests = (int) $priorQa['total_requests'];
    $provider->priorSpend = (float) $priorQa['total_estimated_usd'];
    $report['prior_qa'] = ['report' => realpath($options['prior-qa']), 'requests' => $provider->requests, 'estimated_usd' => $provider->priorSpend];
}
$expectedCases = 2;
$failed = false;
foreach ($cases as $name => $case) {
    $requestsBefore = $provider->requests;
    config(['ai.adaptive_personalized_routing' => $case['optimize'], 'ai_costs.optimize_prompt_cache' => true, 'ai_costs.cache_partition' => 'latency-eval-'.$partition.'-'.$case['variant']]);
    $provider->coverageChecks = [];
    $provider->answerCalls = [];
    $conversation = ($case['continue_previous'] ?? false) ? $conversation : Conversation::factory()->create(['user_id' => $user->id]);
    $started = microtime(true);
    $turn = null;
    try {
        [$turn] = app(ChatTurnService::class)->accept($conversation, $user, $name, $case['message'],
            $case['language'] ?? 'ar', null, $case['extra_thinking'] ?? false);
        app(ChatTurnService::class)->process($turn, app(ChildChatService::class));
        $turn->refresh();
        $reply = $turn->assistantMessage;
        $record = ['status' => $turn->status, 'seconds' => round(microtime(true) - $started, 3),
            'question' => $case['message'], 'response_type' => data_get($reply?->metadata, 'response_type'),
            'mode' => data_get($reply?->metadata, 'response_mode'), 'answer' => $reply?->content,
            'model' => $reply?->model_name, 'sources' => $reply?->sources,
            'evidence' => data_get($reply?->metadata, 'evidence'),
            'turn_plan' => data_get($reply?->metadata, 'turn_plan'),
            'quality' => data_get($reply?->metadata, 'answer_quality'), 'error' => $turn->error];
        $failed = $failed || $turn->status !== 'completed';
    } catch (Throwable $error) {
        $record = ['status' => 'exception', 'seconds' => round(microtime(true) - $started, 3), 'error_class' => $error::class];
        $failed = true;
    }
    $record['coverage_checks'] = $provider->coverageChecks;
    $record['answer_calls'] = $provider->answerCalls;
    $evidence = $record['evidence'] ?? [];
    $record['technical_checks'] = [
        'answered' => ($record['status'] ?? '') === 'completed' && ($record['response_type'] ?? '') === 'answer',
        'draft_guidance_applied' => str_contains($record['answer_calls'][0]['messages'][0]['content'] ?? '', 'REFERENCE-FAITHFUL FIRST DRAFT:'),
        'primary_review_retained' => ($record['quality']['model'] ?? null) === config('ai.answer_quality_model'),
        'semantically_reviewed' => ($record['quality']['reviewed'] ?? false) === true && ($record['quality']['passed'] ?? false) === true,
        'route_preserved' => ($evidence['internal_answer_sufficient'] ?? false) === true && ($evidence['used_web_search'] ?? null) === false,
    ];
    $usage = $turn ? DB::table('ai_request_usages')->where('user_message_id', $turn->user_message_id)->get() : collect();
    $record['cost'] = ['estimated_usd' => round((float) $usage->sum('estimated_cost_usd'), 8),
        'requests' => $usage->count(), 'unpriced_requests' => $usage->whereNull('estimated_cost_usd')->count(),
        'input_tokens' => $usage->sum('input_tokens'), 'cached_tokens' => $usage->sum('cached_input_tokens'),
        'write_tokens' => $usage->sum('cache_write_tokens'), 'output_tokens' => $usage->sum('output_tokens')];
    $record['usage'] = $usage->toArray();
    $record['technical_checks']['all_usage_priced'] = $record['cost']['unpriced_requests'] === 0;
    $record['technical_checks']['all_requests_recorded'] = $usage->count() === $provider->requests - $requestsBefore;
    $failed = $failed || in_array(false, $record['technical_checks'], true);
    $report['cases'][$name] = $record;
    file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    chmod($output, 0600);
    echo json_encode(['case' => $name, 'cost' => $record['cost'], 'seconds' => $record['seconds'], 'status' => $record['status'],
        'response_type' => $record['response_type'] ?? null, 'mode' => $record['mode'] ?? null, 'checks' => $record['technical_checks']], JSON_UNESCAPED_UNICODE)."\n";
    if ($failed || $provider->budgetStopped) break;
}
$report['request_limit'] = 20;
$report['cost_stop_threshold_usd'] = 0.50;
$report['cost_scope'] = 'All foreground provider stages including embeddings, reads/writes and hosted searches. Estimates from provider usage, not invoices; maintenance excluded by Bus::fake. Different cache partitions isolate the two variants; both use the existing cache optimization. Primary semantic review is retained. Only one new full application question is run per invocation; optional prior-qa carries all earlier latency-experiment requests and spend into the cumulative limit. Baseline is reused from the previous verified model-routing test and excluded from new QA spend. Cold prompt-cache partition. All primary review, repair and verification calls are retained and metered.';
$report['total_estimated_usd'] = $provider->priorSpend + (float) DB::table('ai_request_usages')->sum('estimated_cost_usd');
$report['total_requests'] = $provider->requests;
$report['all_checks_passed'] = !$failed && count($report['cases']) === $expectedCases;
file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
chmod($output, 0600);
exit($report['all_checks_passed'] ? 0 : 1);
