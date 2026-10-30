<?php

// Synthetic, isolated live evaluation. Never points Laravel at the configured application database.
$options = getopt('', ['live', 'bundle:', 'output:', 'case::']);
if (! isset($options['live'], $options['bundle'], $options['output'])) {
    fwrite(STDERR, "Usage: php -d memory_limit=2048M scripts/benchmark-chat-local.php --live --bundle=/path/index.ndjson.gz --output=/path/report.json [--case=communication]\n");
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

$output = $options['output'];
if (! is_dir(dirname($output))) mkdir(dirname($output), 0755, true);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null, 'logging.default' => 'single',
    'logging.channels.single.path' => $output.'.log', 'cache.default' => 'array']);
DB::purge('sqlite');
if (Artisan::call('migrate', ['--force' => true]) !== 0
    || Artisan::call('knowledge:import-index', ['bundle' => $bundle]) !== 0) {
    fwrite(STDERR, Artisan::output()); exit(1);
}
Bus::fake();
$user = User::factory()->create(['ai_consent_accepted_at' => now()]);
$plan = Plan::factory()->create(['type' => 'pro', 'is_active' => true, 'ai_messages_per_day' => null]);
Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
    'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
$cases = [
    'greeting_ar' => ['message' => 'السلام عليكم'],
    'greeting_en' => ['message' => 'Hello!', 'language' => 'en'],
    'communication' => ['message' => 'ما هي الأسس الأربعة التي يقوم عليها التواصل الفعال؟'],
    'speech_systems' => ['message' => 'ما هي الأنظمة الثلاثة التي تتضمنها عملية الكلام؟'],
    'voice_box' => ['message' => 'كيف تنتج الحنجرة الصوت وما دور الحبال الصوتية؟'],
    'stuttering_stages' => ['message' => 'ما المراحل الثلاث للتأتأة من حيث الشدة؟'],
    'language_components' => ['message' => 'ما هي مكونات اللغة: الشكل والمحتوى والاستخدام؟'],
    'emergency' => ['message' => 'ابني لا يستطيع التنفس الآن'],
    'unrelated' => ['message' => 'اكتبلي كود لعبة سباق سيارات'],
    'deep_communication' => ['message' => 'ما هي الأسس الأربعة التي يقوم عليها التواصل الفعال؟', 'extra_thinking' => true],
    'holdout_relevance' => ['message' => 'في التواصل الفعال، إيه المقصود بمبدأ الصلة؟'],
    'holdout_voice' => ['message' => 'الصوت بيتكون إزاي قبل ما نحوله لكلام؟'],
    'holdout_language' => ['message' => 'ممكن مثال يوضح الفرق بين شكل اللغة ومحتواها واستخدامها؟'],
];
if (isset($options['case'])) {
    $selected = (array) $options['case'];
    $cases = array_intersect_key($cases, array_flip($selected));
    if ($cases === []) { fwrite(STDERR, "Unknown case.\n"); exit(2); }
}
$report = ['synthetic' => true, 'database' => 'isolated in-memory SQLite', 'production_verified' => false,
    'bundle_sha256' => hash_file('sha256', $bundle), 'started_at' => now()->toIso8601String(),
    'timing_scope' => 'accept + processing; excludes library import, mobile network and queue waiting', 'cases' => []];
$failed = false;
foreach ($cases as $name => $case) {
    $conversation = Conversation::factory()->create(['user_id' => $user->id]);
    $started = microtime(true);
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
            'quality' => data_get($reply?->metadata, 'answer_quality'), 'error' => $turn->error];
        $failed = $failed || $turn->status !== 'completed';
    } catch (Throwable $error) {
        $record = ['status' => 'exception', 'seconds' => round(microtime(true) - $started, 3), 'error_class' => $error::class];
        $failed = true;
    }
    $report['cases'][$name] = $record;
    file_put_contents($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    echo json_encode(['case' => $name, 'seconds' => $record['seconds'], 'status' => $record['status'],
        'response_type' => $record['response_type'] ?? null, 'mode' => $record['mode'] ?? null], JSON_UNESCAPED_UNICODE)."\n";
}
exit($failed ? 1 : 0);
