<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= e(MIYIZE_SITE_URL . '/') ?></loc>
        <changefreq>hourly</changefreq>
        <priority>1.0</priority>
    </url>
    <?php foreach (categories() as $slug => $category): ?>
        <?php if ($slug === 'latest') { continue; } ?>
        <url>
            <loc><?= e(site_path(category_url($slug))) ?></loc>
            <changefreq>hourly</changefreq>
            <priority>0.8</priority>
        </url>
    <?php endforeach; ?>
    <?php foreach (load_articles(180) as $article): ?>
        <url>
            <loc><?= e(site_path(article_url($article))) ?></loc>
            <lastmod><?= e((string) ($article['updated_at'] ?? $article['published_at'] ?? gmdate(DATE_ATOM))) ?></lastmod>
            <changefreq>daily</changefreq>
            <priority>0.7</priority>
        </url>
    <?php endforeach; ?>
</urlset>

