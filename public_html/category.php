<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$slug    = (string) ($_GET['category'] ?? $_GET['slug'] ?? 'latest');
$allCats = categories();
if (!isset($allCats[$slug])) { http_response_code(404); $slug = 'latest'; }
$label    = category_label($slug);
$catUrl   = category_url($slug);
$articles = articles_for_category($slug, 80);

// If there are too few articles for this category, fill up with general news to keep layout full and premium
if (count($articles) < 12) {
    $general = articles_for_category('latest', 80);
    $seenSlugs = array_flip(array_column($articles, 'slug'));
    $fallback = [];
    foreach ($general as $a) {
        if (!isset($seenSlugs[$a['slug']])) {
            $fallback[] = $a;
        }
    }
    $articles = array_slice(array_merge($articles, $fallback), 0, 60);
}

// Structured data
$schemaItems = [];
foreach (array_slice($articles, 0, 10) as $i => $a) {
    $schemaItems[] = ['@type' => 'ListItem', 'position' => $i + 1,
        'url' => site_path(article_url($a)), 'name' => (string) ($a['title'] ?? '')];
}
$extraSchema = ['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_path('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => site_path($catUrl)],
    ]],
    ['@type' => 'ItemList', 'name' => $label . ' ಸುದ್ದಿ',
     'numberOfItems' => count($schemaItems), 'itemListElement' => $schemaItems],
]];

// Article slices — clean layout (no hero banner)
$trending      = pick_items($articles, 0, 12);
$gridArticles  = array_slice($articles, 0, 48); // Main bento grid starts from index 0
$moreArticles  = array_slice($articles, 48, 32); // Dense row



page_head(
    $label . ' ಸುದ್ದಿ – ತಾಜಾ ಕನ್ನಡ ಅಪ್ಡೇಟ್',
    $label . ' ವಿಭಾಗದ ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ ಮತ್ತು ಲೈವ್ ಅಪ್ಡೇಟ್. ' . MIYIZE_SITE_NAME . ' ನಲ್ಲಿ ಓದಿ.',
    $catUrl
);
render_header($slug);
?>
<script type="application/ld+json"><?= json_encode($extraSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<main id="main">
<div class="container cp-wrap">

    <!-- Breadcrumb -->
    <nav class="cat-breadcrumb" aria-label="breadcrumb">
        <a href="/">Home</a><span>›</span><span><?= e($label) ?></span>
    </nav>

    <!-- Top leaderboard ad -->
    <div class="cp-ad-leaderboard"><?php render_ad_slot('wide'); ?></div>

    <!-- Category heading pill -->
    <div class="cp-heading">
        <div class="cp-heading__pill">
            <span class="cat-live-dot"></span>
            <h1><?= e($label) ?> ಸುದ್ದಿ</h1>
        </div>
        <span class="cp-heading__sub">ತಾಜಾ · ಲೈವ್ · ಸಂಪೂರ್ಣ ವರದಿ</span>
    </div>

    <!-- Ticker -->
    <?php render_ticker(load_articles(10)); ?>


    <!-- Trending strip (scrollable pills) -->
    <div class="cp-trending-strip">
        <span class="cp-trend-label">🔥 Trending</span>
        <div class="cp-trend-pills">
            <?php foreach ($trending as $idx => $t): ?>
            <a class="cp-trend-pill" href="<?= e(article_url($t)) ?>">
                <span class="cp-trend-pill__num"><?= $idx + 1 ?></span>
                <span><?= e(excerpt_text((string) ($t['title'] ?? ''), 60)) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mid-page wide ad -->
    <div class="cp-ad-mid"><?php render_ad_slot('wide'); ?></div>

    <!-- ── BENTO GRID (like homepage) ───────────────────────────────── -->
    <?php if (!empty($gridArticles)): ?>
    <section class="cp-grid-section">
        <div class="cp-grid-head">
            <h2>ಎಲ್ಲಾ <?= e($label) ?> ಸುದ್ದಿ</h2>
            <span class="cp-grid-count"><?= count($articles) ?>+ ಸುದ್ದಿಗಳು</span>
        </div>

        <!-- Smart auto grid with inline ads every 9th -->
        <div class="cp-auto-grid" id="cpAutoGrid">
            <?php foreach ($gridArticles as $idx => $art):
                $isWide   = ($idx % 9 === 0);
            ?>
            <a class="cp-card<?= $isWide ? ' cp-card--wide' : '' ?>"
               href="<?= e(article_url($art)) ?>">
                <div class="cp-card__img">
                    <img src="<?= e(article_image($art)) ?>" alt="" loading="lazy"
                         onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                    <span class="cp-card__cat"><?= e($label) ?></span>
                    <div class="cp-card__shine"></div>
                </div>
                <div class="cp-card__body">
                    <h3><?= e(excerpt_text((string) ($art['title'] ?? ''), $isWide ? 100 : 72)) ?></h3>
                    <div class="cp-card__meta">
                        <span><?= e(format_kn_date((string) ($art['published_at'] ?? ''))) ?></span>
                        <span><?= e((string) ($art['source'] ?? '')) ?></span>
                    </div>
                </div>
            </a>

            <?php if (($idx + 1) % 9 === 0): ?>
            <!-- Auto sponsor every 9 cards -->
            <div class="cp-grid-ad">
                <span class="cp-ad-tag">AD</span>
                <?php render_ad_slot('article'); ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Bottom wide ad -->
    <div class="cp-ad-bottom"><?php render_ad_slot('wide'); ?></div>

    <!-- ── MORE STORIES DENSE ────────────────────────────────────────── -->
    <?php if (!empty($moreArticles)): ?>
    <section class="cp-more-section">
        <div class="cp-grid-head">
            <h2>ಇನ್ನಷ್ಟು ಸುದ್ದಿ</h2>
            <a href="/">All →</a>
        </div>
        <div class="cp-more-grid">
            <?php foreach ($moreArticles as $item): ?>
            <a class="cp-more-card" href="<?= e(article_url($item)) ?>">
                <img src="<?= e(article_image($item)) ?>" alt="" loading="lazy"
                     onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                <div class="cp-more-card__body">
                    <span class="cp-more-cat"><?= e($label) ?></span>
                    <h3><?= e(excerpt_text((string) ($item['title'] ?? ''), 65)) ?></h3>
                    <small><?= e(format_kn_date((string) ($item['published_at'] ?? ''))) ?></small>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer ad -->
    <div class="cp-ad-footer"><?php render_ad_slot('wide'); ?></div>

</div>
</main>

<?php render_footer(); ?>
