<?php
declare(strict_types=1);

// Check authentication
$cronSecret = getenv('CRON_SECRET') ?: '';
$reqSecret  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$isAuthenticated = ($cronSecret && $reqSecret === 'Bearer ' . $cronSecret) || PHP_SAPI === 'cli';

if (!$isAuthenticated) {
    // Enforce 30 seconds rate limit based on state.json
    $statePath = dirname(__DIR__) . '/data/state.json';
    if (file_exists($statePath)) {
        $state = json_decode((string) file_get_contents($statePath), true);
        $last = isset($state['last_refresh_at']) ? strtotime((string) $state['last_refresh_at']) : 0;
        if (time() - $last < 30) {
            http_response_code(429);
            header('Content-Type: application/json');
            exit(json_encode([
                'status'  => 'ignored',
                'message' => 'Rate limit: last refresh was less than 30 seconds ago.',
                'last_refresh' => date('c', $last),
            ]));
        }
    }
}

header('Content-Type: application/json');

$agentPath = dirname(__DIR__, 2) . '/agent.php';
if (!file_exists($agentPath)) {
    http_response_code(500);
    exit(json_encode(['error' => 'Agent not found']));
}

// Run agent
ob_start();
$start = microtime(true);
require $agentPath;
$output  = ob_get_clean();
$elapsed = round(microtime(true) - $start, 2);

echo json_encode([
    'status'  => 'ok',
    'message' => trim($output),
    'elapsed' => $elapsed . 's',
    'ran_at'  => date('c'),
]);
