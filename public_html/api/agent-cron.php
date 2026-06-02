<?php
/**
 * MIYIZE Auto News Agent – Vercel Cron Endpoint
 * Triggered by Vercel Cron every 30 minutes.
 * Add to vercel.json:
 *   "crons": [{"path": "/api/agent-cron.php", "schedule": "*/30 * * * *"}]
 */
declare(strict_types=1);

// Only allow Vercel cron or local CLI
$cronSecret = getenv('CRON_SECRET') ?: '';
$reqSecret  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($cronSecret && $reqSecret !== 'Bearer ' . $cronSecret) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
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
