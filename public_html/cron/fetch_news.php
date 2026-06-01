<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    $token = getenv('MIYIZE_CRON_TOKEN') ?: '';
    if ($token === '' || ($_GET['token'] ?? '') !== $token) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$state = run_news_refresh();

if (PHP_SAPI === 'cli') {
    echo 'MIYIZE refresh complete: ' . json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

