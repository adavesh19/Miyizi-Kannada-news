<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$slug     = (string) ($_GET['category'] ?? $_GET['slug'] ?? 'latest');
$allCats  = categories();
if (!isset($allCats[$slug])) {
    http_response_code(404);
    $slug = 'latest';
}
$label    = category_label($slug);
$catUrl   = category_url($slug);
$articles = articles_for_category($slug, 60);

// ── structured data ────────────────────────────────────────────────────────────
$schemaItems = [];
foreach (array_slice($articles, 0, 10) as $i => $a) {
    $schemaItems[] = [
        '@type'    => 'ListItem',
        'position' => $i + 1,
        'url'      => site_path(article_url($a)),
        'name'     => (string) ($a['title'] ?? ''),
    ];
}
$breadcrumbs = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_path('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => site_path($catUrl)],
            ],
        ],
        [
            '@type'           => 'ItemList',
            'name'            => $label . ' ಸುದ್ದಿ',
            'numberOfItems'   => count($schemaItems),
            'itemListElement' => $schemaItems,
        ],
    ],
];

// ── split articles ─────────────────────────────────────────────────────────────
$lead        = $articles[0]                    ?? null;
$hero2       = $articles[1]                    ?? null;
$hero3       = $articles[2]                    ?? null;
$sideItems   = array_slice($articles, 1, 8);
$mainGrid    = array_slice($articles, 3, 24);
$trendItems  = array_slice($articles, 0, 10);
$moreItems   = array_slice($articles, 27, 30);

page_head(
    $label . ' ಸುದ್ದಿ – ತಾಜಾ ಮತ್ತು ಮುಖ್ಯ ಕನ್ನಡ ಅಪ್ಡೇಟ್',
    $label . ' ವಿಭಾಗದ ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ, ಲೈವ್ ಅಪ್ಡೇಟ್ ಮತ್ತು ಸಂಪೂರ್ಣ ವರದಿ. ' . MIYIZE_SITE_NAME . ' ನಲ್ಲಿ ಓದಿ.',
    $catUrl
);
render_header($slug);
?>
<!-- Extra JSON-LD for category page -->
<script type="application/ld+json"><?= json_encode($breadcrumbs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<main id="main">
    <div class="container cat-page">

        <!-- Breadcrumb bar -->
        <nav class="cat-breadcrumb" aria-label="ಬ್ರೆಡ್‌ಕ್ರಂಬ್">
            <a href="/">Home</a>
            <span aria-hidden="true">›</span>
            <span><?= e($label) ?></span>
        </nav>

        <!-- Ad strip top -->
        <?php render_ad_slot('wide'); ?>

        <!-- Category hero headline -->
        <div class="cat-heading-row">
            <div class="cat-heading-pill">
                <span class="cat-live-dot" aria-hidden="true"></span>
                <h1><?= e($label) ?> ಸುದ್ದಿ</h1>
            </div>
            <p class="cat-heading-sub">ತಾಜಾ ಸುದ್ದಿ, ಲೈವ್ ಅಪ್ಡೇಟ್ ಮತ್ತು ವಿಶ್ಲೇಷಣೆ</p>
        </div>

        <!-- Ticker -->
        <?php render_ticker(load_articles(8)); ?>

        <!-- Hero + Side layout -->
        <?php if ($lead): ?>
        <section class="cat-hero-grid">
            <!-- Main lead hero -->
            <a class="cat-hero-main" href="<?= e(article_url($lead)) ?>">
                <img src="<?= e(article_image($lead)) ?>" alt="" loading="eager"
                     onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                <div class="cat-hero-overlay"></div>
                <div class="cat-hero-content">
                    <span class="cat-hero-badge"><?= e($label) ?></span>
                    <span class="cat-hero-title"><?= e(excerpt_text((string) ($lead['title'] ?? ''), 120)) ?></span>
                    <div class="cat-hero-meta">
                        <span><?= e(format_kn_date((string) ($lead['published_at'] ?? ''))) ?></span>
                        <span><?= e((string) ($lead['source'] ?? 'MIYIZE')) ?></span>
                    </div>
                </div>
            </a>

            <!-- Side story stack -->
            <div class="cat-side-stack">
                <?php foreach ($sideItems as $item): ?>
                <a class="cat-side-card" href="<?= e(article_url($item)) ?>">
                    <img src="<?= e(article_image($item)) ?>" alt="" loading="lazy"
                         onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                    <div class="cat-side-body">
                        <span class="cat-side-time"><?= e(format_kn_date((string) ($item['published_at'] ?? ''))) ?></span>
                        <h2><?= e(excerpt_text((string) ($item['title'] ?? ''), 80)) ?></h2>
                        <span class="cat-side-src"><?= e((string) ($item['source'] ?? '')) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Trending sidebar -->
            <aside class="cat-trending-panel">
                <div class="cat-panel-head">
                    <h2>🔥 Trending</h2>
                </div>
                <div class="cat-trend-list">
                    <?php foreach ($trendItems as $idx => $t): ?>
                    <a class="cat-trend-item" href="<?= e(article_url($t)) ?>">
                        <span class="cat-trend-num"><?= $idx + 1 ?></span>
                        <span class="cat-trend-text"><?= e(excerpt_text((string) ($t['title'] ?? ''), 72)) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>
        <?php endif; ?>

        <!-- Sponsor strip -->
        <div class="sponsor-strip">
            <div class="sponsor-strip__label">SPONSORED</div>
            <div class="sponsor-strip__slots">
                <?php render_ad_slot('wide'); ?>
            </div>
        </div>

        <!-- Main grid of articles -->
        <?php if (!empty($mainGrid)): ?>
        <section class="cat-grid-section">
            <div class="cat-grid-head">
                <h2>ಎಲ್ಲಾ <?= e($label) ?> ಸುದ್ದಿ</h2>
            </div>
            <div class="cat-bento-grid">
                <?php foreach ($mainGrid as $idx => $article): ?>
                <a class="cat-bento-card<?= $idx % 7 === 0 ? ' cat-bento-card--wide' : '' ?>"
                   href="<?= e(article_url($article)) ?>">
                    <div class="cat-bento-img">
                        <img src="<?= e(article_image($article)) ?>" alt="" loading="lazy"
                             onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                        <span class="cat-bento-cat"><?= e((string) ($article['category_label'] ?? $label)) ?></span>
                    </div>
                    <div class="cat-bento-body">
                        <h3><?= e(excerpt_text((string) ($article['title'] ?? ''), $idx % 7 === 0 ? 110 : 76)) ?></h3>
                        <div class="cat-bento-meta">
                            <span><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
                            <span><?= e((string) ($article['source'] ?? '')) ?></span>
                        </div>
                    </div>
                </a>
                <?php if ($idx === 5): ?>
                    <!-- In-grid sponsor card -->
                    <div class="cat-sponsor-card" aria-label="Advertisement">
                        <span class="cat-sponsor-label">AD</span>
                        <?php render_ad_slot('article'); ?>
                    </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Bottom ad strip -->
        <?php render_ad_slot('wide'); ?>

        <!-- More stories dense row -->
        <?php if (!empty($moreItems)): ?>
        <section class="cat-more-section">
            <div class="cat-grid-head">
                <h2>ಇನ್ನಷ್ಟು ಸುದ್ದಿ</h2>
                <a href="/">All News →</a>
            </div>
            <div class="cat-more-grid">
                <?php foreach ($moreItems as $item): ?>
                <a class="cat-more-card" href="<?= e(article_url($item)) ?>">
                    <img src="<?= e(article_image($item)) ?>" alt="" loading="lazy"
                         onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                    <span class="cat-more-cat"><?= e((string) ($item['category_label'] ?? $label)) ?></span>
                    <h3><?= e(excerpt_text((string) ($item['title'] ?? ''), 66)) ?></h3>
                    <small><?= e(format_kn_date((string) ($item['published_at'] ?? ''))) ?></small>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>
</main>
<?php render_footer(); ?>
