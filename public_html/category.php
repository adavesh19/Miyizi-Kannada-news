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

// Article slices — homepage-style layout
$lead          = $articles[0] ?? null;
$sliderItems   = pick_items($articles, 0, 5);
$liveItems     = pick_items($articles, 0, 10);
$trending      = pick_items($articles, 0, 12);
$gridArticles  = array_slice($articles, 10, 40); // Main bento grid
$moreArticles  = array_slice($articles, 50, 30); // Dense row


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

    <!-- Hero Grid (Homepage Style) -->
    <section class="ref-hero-grid">
        <div class="ref-hero-main">
            <div class="hero-slider" id="heroSlider">
                <?php if (empty($sliderItems)): ?>
                    <div class="empty-state">
                        <h1>ಸುದ್ದಿ ಫೀಡ್ ಸಿದ್ಧವಾಗಿದೆ</h1>
                        <p>RSS refresh ನಂತರ ಸುದ್ದಿ ಕಾಣಿಸುತ್ತದೆ.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sliderItems as $index => $article): ?>
                        <a class="hero-tile<?= $index === 0 ? ' active' : '' ?>" href="<?= e(article_url($article)) ?>">
                            <img src="<?= e(article_image($article)) ?>" alt="" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                            <div class="hero-tile__overlay"></div>
                            <div class="hero-tile__content">
                                <span class="hero-tile__cat"><?= e($article['category_label'] ?? $label) ?></span>
                                <span class="hero-tile__title"><?= e(excerpt_text((string) ($article['title'] ?? ''), 110)) ?></span>
                                <div class="hero-tile__meta">
                                    <span><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
                                    <span><?= e((string) ($article['source'] ?? 'MIYIZE')) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <div class="slider-dots">
                        <?php foreach ($sliderItems as $index => $_): ?>
                            <button class="slider-dot<?= $index === 0 ? ' active' : '' ?>" aria-label="Slide <?= $index + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <aside class="ref-live-panel">
            <div class="section-title">
                <h2>Live Updates</h2>
                <a href="/feed.xml">RSS</a>
            </div>
            <div class="ref-live-list">
                <?php foreach ($liveItems as $article): ?>
                    <a class="live-item" href="<?= e(article_url($article)) ?>">
                        <time><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></time>
                        <span><?= e(excerpt_text((string) ($article['title'] ?? ''), 88)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <aside class="ref-tools-panel">
            <div class="ref-tool-box">
                <h2>Newsletter & WhatsApp</h2>
                <p>Get fast alerts on your phone and inbox.</p>
                <a class="button" href="/contact.php">Subscribe</a>
            </div>
            <div class="ref-ad-box">
                <small>SPONSOR</small>
                <strong>300 x 250</strong>
            </div>
        </aside>
    </section>

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
                $titleLen = mb_strlen((string) ($art['title'] ?? ''));
                $isWide   = ($idx % 9 === 0) || $titleLen > 58;
            ?>
            <a class="cp-card<?= $isWide ? ' cp-card--wide' : '' ?>"
               href="<?= e(article_url($art)) ?>">
                <div class="cp-card__img">
                    <img src="<?= e(article_image($art)) ?>" alt="" loading="lazy"
                         onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                    <span class="cp-card__cat"><?= e((string) ($art['category_label'] ?? $label)) ?></span>
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
                    <span class="cp-more-cat"><?= e((string) ($item['category_label'] ?? $label)) ?></span>
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
<script>
    (function(){
        const slider = document.getElementById('heroSlider');
        if(!slider) return;
        const slides = slider.querySelectorAll('.hero-tile');
        const dots = slider.querySelectorAll('.slider-dot');
        if(!slides.length || !dots.length) return;
        let current = 0;
        function show(index){
            slides.forEach((s, i) => s.classList.toggle('active', i === index));
            dots.forEach((d, i) => d.classList.toggle('active', i === index));
        }
        function next(){
            current = (current + 1) % slides.length;
            show(current);
        }
        let timer = setInterval(next, 5000);
        dots.forEach((dot, i) => dot.addEventListener('click', () => {
            clearInterval(timer);
            current = i;
            show(current);
            timer = setInterval(next, 5000);
        }));
    })();
</script>

<?php render_footer(); ?>
