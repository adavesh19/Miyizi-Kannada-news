<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

maybe_refresh_news();

$items = [];
foreach (categories() as $slug => $category) {
    $label = category_label((string) $slug);
    $pageUrl = $slug === 'latest' ? '/' : category_url((string) $slug);
    $rssUrl = $slug === 'latest' ? '/feed.xml' : '/rss/' . rawurlencode((string) $slug) . '.xml';
    $apiUrl = $slug === 'latest' ? '/api/latest.php' : '/api/category/' . rawurlencode((string) $slug) . '.json';

    $items[] = [
        'slug' => (string) $slug,
        'label' => $label,
        'page_url' => site_path($pageUrl),
        'rss_url' => site_path($rssUrl),
        'api_url' => site_path($apiUrl),
        'count' => count(articles_for_category((string) $slug, 0)),
    ];
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');

echo json_encode([
    'site' => MIYIZE_SITE_NAME,
    'generated_at' => date(DATE_ATOM),
    'categories' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

