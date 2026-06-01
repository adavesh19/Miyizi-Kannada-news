<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$slug = (string) ($_GET['category'] ?? $_GET['slug'] ?? 'latest');
$allCategories = categories();
if (!isset($allCategories[$slug])) {
    $slug = 'latest';
}

$label = category_label($slug);
$articles = articles_for_category($slug, 60);
$channelLink = $slug === 'latest' ? '/' : category_url($slug);
$feedTitle = MIYIZE_SITE_NAME . ' - ' . $label;
$feedDescription = $slug === 'latest'
    ? MIYIZE_SITE_TAGLINE
    : $label . ' Kannada news RSS feed from ' . MIYIZE_SITE_NAME;

header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
    <title><?= e($feedTitle) ?></title>
    <link><?= e(site_path($channelLink)) ?></link>
    <description><?= e($feedDescription) ?></description>
    <language>kn-IN</language>
    <lastBuildDate><?= e(date(DATE_RSS)) ?></lastBuildDate>
    <?php foreach ($articles as $article): ?>
        <item>
            <title><?= e((string) ($article['title'] ?? '')) ?></title>
            <link><?= e(site_path(article_url($article))) ?></link>
            <guid isPermaLink="true"><?= e(site_path(article_url($article))) ?></guid>
            <pubDate><?= e(date(DATE_RSS, strtotime((string) ($article['published_at'] ?? 'now')) ?: time())) ?></pubDate>
            <category><?= e((string) ($article['category_label'] ?? $label)) ?></category>
            <description><?= e(excerpt_text((string) ($article['summary'] ?? ''), 260)) ?></description>
        </item>
    <?php endforeach; ?>
</channel>
</rss>
