<?php
/**
 * ONE-TIME CACHE CLEAR UTILITY
 * Upload to your server root, visit it once in the browser, then DELETE it immediately.
 * URL: https://rafeequae.com/clear-cache.php
 */

// Basic security: only allow from known IPs or with a secret token
$secret = 'rafeeq-clear-2024';
if (($_GET['token'] ?? '') !== $secret) {
    http_response_code(403);
    die('Forbidden. Add ?token=' . $secret . ' to the URL.');
}

define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$results = [];

foreach (['route:clear', 'config:clear', 'cache:clear', 'view:clear'] as $command) {
    $exit = $kernel->call($command);
    $results[$command] = $exit === 0 ? '✅ OK' : '❌ Failed (exit ' . $exit . ')';
}

echo '<pre style="font-family:monospace;font-size:14px;padding:20px">';
echo "Cache Clear Results\n";
echo "===================\n\n";
foreach ($results as $cmd => $result) {
    echo "php artisan $cmd  →  $result\n";
}
echo "\n⚠️  DELETE this file from the server now!\n";
echo '</pre>';
