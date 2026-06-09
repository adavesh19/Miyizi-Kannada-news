<?php
declare(strict_types=1);

// ── Strict CRON_SECRET authentication ─────────────────────────────────────────
// Vercel Cron sends: Authorization: Bearer {CRON_SECRET}
// Direct browser calls are REJECTED with 401.
$cronSecret = getenv('CRON_SECRET') ?: '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$isCronAuth = $cronSecret !== '' && $authHeader === 'Bearer ' . $cronSecret;
$isCli      = PHP_SAPI === 'cli';

if (!$isCronAuth && !$isCli) {
    http_response_code(401);
    header('Content-Type: application/json');
    exit(json_encode([
        'error'   => 'Unauthorized',
        'message' => 'This endpoint requires a valid CRON_SECRET Authorization header.',
    ]));
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
