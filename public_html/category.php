<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$slug     = (string) ($_GET['category'] ?? $_GET['slug'] ?? 'latest');
$allCats  = categories();
if (!isset($allCats[$slug])) { http_response_code(404); $slug = 'latest'; }
$label    = category_label($slug);
$catUrl   = category_url($slug);
$articles = articles_for_category($slug, 64);

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

// Split articles
$lead       = $articles[0] ?? null;
$sideItems  = array_slice($articles, 1, 6);
$trending   = array_slice($articles, 0, 10);
$mainGrid   = array_slice($articles, 7, 30);
$moreItems  = array_slice($articles, 37, 24);

page_head(
    $label . ' ಸುದ್ದಿ – ತಾಜಾ ಕನ್ನಡ ಅಪ್ಡೇಟ್',
    $label . ' ವಿಭಾಗದ ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ, ಲೈವ್ ಅಪ್ಡೇಟ್ ಮತ್ತು ಸಂಪೂರ್ಣ ವರದಿ. ' . MIYIZE_SITE_NAME . ' ನಲ್ಲಿ ಓದಿ.',
    $catUrl
);
render_header($slug);
?>
<script type="application/ld+json"><?= json_encode($extraSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<main id="main">
<div class="container cat-page">

    <!-- Breadcrumb -->
    <nav class="cat-breadcrumb" aria-label="ಬ್ರೆಡ್‌ಕ್ರಂಬ್">
        <a href="/">Home</a><span aria-hidden="true">›</span><span><?= e($label) ?></span>
    </nav>

    <!-- Top ad -->
    <?php render_ad_slot('wide'); ?>

    <!-- Category pill heading -->
    <div class="cat-heading-row">
        <div class="cat-heading-pill">
            <span class="cat-live-dot" aria-hidden="true"></span>
            <h1><?= e($label) ?> ಸುದ್ದಿ</h1>
        </div>
        <p class="cat-heading-sub">ತಾಜಾ ಸುದ್ದಿ · ಲೈವ್ ಅಪ್ಡೇಟ್ · ವಿಶ್ಲೇಷಣೆ</p>
    </div>

    <!-- Ticker -->
    <?php render_ticker(load_articles(8)); ?>

    <!-- ── HERO SECTION ───────────────────────────────────────────────── -->
    <?php if ($lead): ?>
    <section class="cat-hero-section">

        <!-- Lead hero card (3D) -->
        <a class="cat-hero-card" href="<?= e(article_url($lead)) ?>" aria-label="<?= e((string) ($lead['title'] ?? '')) ?>">
            <div class="cat-hero-card__img-wrap">
                <img src="<?= e(article_image($lead)) ?>" alt=""
                     loading="eager"
                     onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                <div class="cat-hero-card__overlay"></div>
                <div class="cat-hero-card__shine"></div>
            </div>
            <div class="cat-hero-card__content">
                <span class="cat-hero-badge"><?= e($label) ?></span>
                <span class="cat-hero-title"><?= e(excerpt_text((string) ($lead['title'] ?? ''), 130)) ?></span>
                <div class="cat-hero-meta">
                    <span><?= e(format_kn_date((string) ($lead['published_at'] ?? ''))) ?></span>
                    <span>·</span>
                    <span><?= e((string) ($lead['source'] ?? 'MIYIZE')) ?></span>
                </div>
            </div>
        </a>

        <!-- Side stack (6 articles) -->
        <div class="cat-side-stack">
            <?php foreach ($sideItems as $item): ?>
            <a class="cat-side-card" href="<?= e(article_url($item)) ?>">
                <div class="cat-side-img">
                    <img src="<?= e(article_image($item)) ?>" alt="" loading="lazy"
                         onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                </div>
                <div class="cat-side-body">
                    <span class="cat-side-time"><?= e(format_kn_date((string) ($item['published_at'] ?? ''))) ?></span>
                    <h2><?= e(excerpt_text((string) ($item['title'] ?? ''), 82)) ?></h2>
                    <span class="cat-side-src"><?= e((string) ($item['source'] ?? '')) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Trending panel -->
        <aside class="cat-trending-panel">
            <div class="cat-panel-head">
                <span class="cat-live-dot" aria-hidden="true"></span>
                <h2>🔥 Trending</h2>
            </div>
            <div class="cat-trend-list">
                <?php foreach ($trending as $idx => $t): ?>
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
    <div class="cat-sponsor-bar">
        <span class="cat-sponsor-bar__tag">SPONSORED</span>
        <?php render_ad_slot('wide'); ?>
    </div>

    <!-- ── MAIN BENTO GRID ────────────────────────────────────────────── -->
    <?php if (!empty($mainGrid)): ?>
    <section class="cat-grid-section">
        <div class="cat-grid-head">
            <h2>ಎಲ್ಲಾ <?= e($label) ?> ಸುದ್ದಿ</h2>
        </div>
        <div class="cat-bento-grid" id="catBentoGrid">
            <?php foreach ($mainGrid as $idx => $art): ?>
            <a class="cat-bento-card" href="<?= e(article_url($art)) ?>"
               data-title-len="<?= mb_strlen((string) ($art['title'] ?? '')) ?>">
                <div class="cat-bento-img">
                    <img src="<?= e(article_image($art)) ?>" alt="" loading="lazy"
                         onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                    <span class="cat-bento-cat"><?= e((string) ($art['category_label'] ?? $label)) ?></span>
                </div>
                <div class="cat-bento-body">
                    <h3><?= e(excerpt_text((string) ($art['title'] ?? ''), 90)) ?></h3>
                    <div class="cat-bento-meta">
                        <span><?= e(format_kn_date((string) ($art['published_at'] ?? ''))) ?></span>
                        <span><?= e((string) ($art['source'] ?? '')) ?></span>
                    </div>
                </div>
            </a>
            <?php if (($idx + 1) % 8 === 0): ?>
            <!-- Auto-inserted sponsor every 8 articles -->
            <div class="cat-bento-ad" aria-label="Advertisement">
                <span class="cat-bento-ad__tag">AD</span>
                <?php render_ad_slot('article'); ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Bottom ad -->
    <?php render_ad_slot('wide'); ?>

    <!-- ── MORE STORIES ───────────────────────────────────────────────── -->
    <?php if (!empty($moreItems)): ?>
    <section class="cat-more-section">
        <div class="cat-grid-head">
            <h2>ಇನ್ನಷ್ಟು <?= e($label) ?> ಸುದ್ದಿ</h2>
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
<!-- Smart grid JS: auto-adjust card span based on title length -->
<script>
(function(){
    var cards = document.querySelectorAll('#catBentoGrid .cat-bento-card');
    cards.forEach(function(card){
        var len = parseInt(card.getAttribute('data-title-len') || '0', 10);
        if(len > 60) card.classList.add('cat-bento-card--wide');
    });
})();
</script>
<?php render_footer(); ?>
