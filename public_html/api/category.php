<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

maybe_refresh_news();

$slug = (string) ($_GET['category'] ?? $_GET['slug'] ?? 'latest');
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 24)));
$allCategories = categories();
if (!isset($allCategories[$slug])) {
    http_response_code(404);
    $slug = 'latest';
}

$label = category_label($slug);
$articles = array_map(static function (array $article): array {
    $url = article_url($article);

    return [
        'id' => $article['slug'] ?? '',
        'title' => $article['title'] ?? '',
        'slug' => $article['slug'] ?? '',
        'url' => $url,
        'absolute_url' => site_path($url),
        'category' => $article['category_label'] ?? '',
        'category_slug' => $article['category'] ?? 'latest',
        'image' => article_image($article),
        'source' => $article['source'] ?? '',
        'source_url' => $article['source_url'] ?? '',
        'summary' => excerpt_text((string) ($article['summary'] ?? $article['full_content'] ?? ''), 220),
        'published_at' => $article['published_at'] ?? '',
        'updated_at' => $article['updated_at'] ?? $article['published_at'] ?? '',
        'time_label' => format_kn_date((string) ($article['published_at'] ?? '')),
        'tags' => is_array($article['tags'] ?? null) ? $article['tags'] : [],
    ];
}, articles_for_category($slug, $limit));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=45');

echo json_encode([
    'state' => live_state(),
    'category' => [
        'slug' => $slug,
        'label' => $label,
        'page_url' => category_url($slug),
        'rss_url' => $slug === 'latest' ? '/feed.xml' : '/rss/' . rawurlencode($slug) . '.xml',
        'api_url' => $slug === 'latest' ? '/api/latest.php' : '/api/category/' . rawurlencode($slug) . '.json',
    ],
    'count' => count($articles),
    'articles' => $articles,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

