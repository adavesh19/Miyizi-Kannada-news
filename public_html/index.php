<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$items = load_articles(96);


$lead = $items[0] ?? null;
$sliderItems = pick_items($items, 0, 5);
$liveItems = pick_items($items, 0, 10);
$trendingItems = pick_items($items, 0, 12);
$tazaItems = pick_items($items, 1, 12);
$moreGridItems = pick_items($items, 12, 48);
$denseGridItems = pick_items($items, 24, 54);

$karnatakaBaseItems = articles_for_category('karnataka', 16);
if (count($karnatakaBaseItems) >= 10) {
    $karnatakaItems = array_slice($karnatakaBaseItems, 0, 16);
} else {
    $karnatakaItems = array_slice(array_merge($karnatakaBaseItems, pick_items($items, 4, 16)), 0, 16);
}

$sectionSlugs = ['india', 'world', 'business', 'sports', 'cinema', 'technology', 'fact-check'];

page_head(MIYIZE_SITE_NAME, MIYIZE_SITE_TAGLINE, '/');
render_header('latest');
?>
<!-- WebSite / Sitelinks searchbox schema -->
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => MIYIZE_SITE_NAME,
    'url'      => MIYIZE_SITE_URL,
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => site_path('/search.php') . '?q={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<main id="main">
    <div class="container ref-home">
        <!-- Hero Grid -->
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
                                    <span class="hero-tile__cat"><?= e($article['category_label'] ?? 'ತಾಜา ಸುದ್ದಿ') ?></span>
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
                <div class="ref-ad-box" style="padding:0; overflow:hidden;">
                    <?php render_ad_slot('article'); ?>
                </div>
            </aside>
        </section>


        <!-- Second Grid -->
        <section class="ref-second-grid">
            <!-- Taza News -->
            <div class="ref-block">
                <div class="section-title">
                    <h2>ತಾಜಾ ಸುದ್ದಿ</h2>
                    <a href="/">All News</a>
                </div>
                <div class="ref-mini-grid">
                    <?php foreach ($tazaItems as $article): ?>
                        <a class="ref-mini-card" href="<?= e(article_url($article)) ?>">
                            <img src="<?= e(article_image($article)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                            <h3><?= e(excerpt_text((string) ($article['title'] ?? ''), 74)) ?></h3>
                            <span><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Karnataka News -->
            <div class="ref-block">
                <div class="section-title">
                    <h2>ಕರ್ನಾಟಕ</h2>
                    <a href="<?= e(category_url('karnataka')) ?>">All</a>
                </div>
                <div class="ref-karnataka-wrap">
                    <?php
                    $kLead = $karnatakaItems[0] ?? null;
                    $kList = array_slice($karnatakaItems, 1, 12);
                    ?>
                    <?php if ($kLead): ?>
                        <a class="ref-karnataka-image" href="<?= e(article_url($kLead)) ?>">
                            <img src="<?= e(article_image($kLead)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                        </a>
                        <h3><a href="<?= e(article_url($kLead)) ?>"><?= e(excerpt_text((string) ($kLead['title'] ?? ''), 90)) ?></a></h3>
                        <span><?= e(format_kn_date((string) ($kLead['published_at'] ?? ''))) ?></span>
                        <div class="ref-k-list">
                            <?php foreach ($kList as $article): ?>
                                <a class="ref-k-mini" href="<?= e(article_url($article)) ?>">
                                    <img src="<?= e(article_image($article)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                                    <span><?= e(excerpt_text((string) ($article['title'] ?? ''), 66)) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No Karnataka stories yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trending -->
            <div class="ref-block">
                <div class="section-title">
                    <h2>Trending</h2>
                </div>
                <div class="ref-trending-list">
                    <?php foreach ($trendingItems as $index => $article): ?>
                        <a class="compact-item" href="<?= e(article_url($article)) ?>">
                            <span class="trend-num"><?= $index + 1 ?></span>
                            <span class="compact-text"><?= e(excerpt_text((string) ($article['title'] ?? ''), 72)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>


        <!-- Category Columns Row -->
        <section class="ref-category-row">
            <?php foreach ($sectionSlugs as $slug): ?>
                <?php
                $label = categories()[$slug]['label'] ?? $slug;
                $list = articles_for_category($slug, 4);
                if (empty($list)) { continue; }
                $first = $list[0];
                $rest = array_slice($list, 1);
                ?>
                <section class="ref-category-col">
                    <div class="ref-col-head">
                        <h2><?= e($label) ?></h2>
                        <a href="<?= e(category_url($slug)) ?>">More</a>
                    </div>
                    <a class="ref-col-image" href="<?= e(article_url($first)) ?>">
                        <img src="<?= e(article_image($first)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                    </a>
                    <h3><a href="<?= e(article_url($first)) ?>"><?= e(excerpt_text((string) ($first['title'] ?? ''), 72)) ?></a></h3>
                    <ul>
                        <?php foreach ($rest as $article): ?>
                            <li><a href="<?= e(article_url($article)) ?>"><?= e(excerpt_text((string) ($article['title'] ?? ''), 66)) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </section>

        <!-- Topic Sections Wall -->
        <section class="ref-topic-wall">
            <div class="section-title">
                <h2>Category Grids</h2>
                <a href="/sitemap.xml">All Sections</a>
            </div>
            <div class="ref-topic-wall-grid">
                <?php foreach ($sectionSlugs as $sectionIndex => $slug): ?>
                    <?php
                    $label = categories()[$slug]['label'] ?? $slug;
                    $list = articles_for_category($slug, 8);
                    if (count($list) >= 5) {
                        $sectionItems = array_slice($list, 0, 8);
                    } else {
                        $sectionItems = array_slice(array_merge($list, pick_items($items, $sectionIndex * 6, 8)), 0, 8);
                    }
                    ?>
                    <section class="ref-topic-section">
                        <div class="ref-col-head">
                            <h2><?= e($label) ?></h2>
                            <a href="<?= e(category_url($slug)) ?>">More</a>
                        </div>
                        <div class="ref-topic-grid">
                            <?php foreach ($sectionItems as $article): ?>
                                <a class="ref-topic-card" href="<?= e(article_url($article)) ?>">
                                    <img src="<?= e(article_image($article)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                                    <h3><?= e(excerpt_text((string) ($article['title'] ?? ''), 64)) ?></h3>
                                    <span><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Sponsor block before dense grid -->
        <div class="hp-sponsor-block">
            <div class="hp-sponsor-block__inner">
                <div class="hp-sponsor-block__cta">
                    <h2>📱 WhatsApp ಸುದ್ದಿ ಅಲರ್ಟ್</h2>
                    <p>ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ ನೇರ ನಿಮ್ಮ ಫೋನಿಗೆ – ಉಚಿತ!</p>
                    <a class="button" href="/contact.php">Subscribe →</a>
                </div>
                <div class="hp-sponsor-block__ad">
                    <span class="hp-sponsor-block__adlabel">SPONSOR</span>
                    <?php render_ad_slot('article'); ?>
                </div>

            </div>
        </div>

        <!-- All News Dense Grid -->
        <section class="ref-dense-section">
            <div class="section-title">
                <h2>All News Grid</h2>
                <a href="/feed.xml">Live Feed</a>
            </div>
            <div class="ref-news-grid">
                <?php foreach ($denseGridItems as $index => $article): ?>
                    <a class="ref-news-cell<?= $index % 9 === 0 ? ' is-wide' : '' ?>" href="<?= e(article_url($article)) ?>">
                        <img src="<?= e(article_image($article)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                        <span><?= e($article['category_label'] ?? 'News') ?></span>
                        <h3><?= e(excerpt_text((string) ($article['title'] ?? ''), $index % 9 === 0 ? 96 : 68)) ?></h3>
                        <small><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></small>
                    </a>
                    <?php if (($index + 1) % 9 === 0): ?>
                        <div class="cp-grid-ad">
                            <span class="cp-ad-tag">SPONSORED</span>
                            <?php render_ad_slot('article'); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- More Stories Grid -->
        <section class="ref-more-section">
            <div class="section-title">
                <h2>More Stories</h2>
                <a href="/sitemap.xml">Sitemap</a>
            </div>
            <div class="ref-more-grid">
                <?php foreach ($moreGridItems as $index => $article): ?>
                    <a class="ref-more-card" href="<?= e(article_url($article)) ?>">
                        <img src="<?= e(article_image($article)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                        <strong><?= e($article['category_label'] ?? 'News') ?></strong>
                        <h3><?= e(excerpt_text((string) ($article['title'] ?? ''), 74)) ?></h3>
                        <span><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
                    </a>
                    <?php if (($index + 1) % 8 === 0): ?>
                        <div class="cp-grid-ad">
                            <span class="cp-ad-tag">ADVERTISEMENT</span>
                            <?php render_ad_slot('article'); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Footer sponsor strip -->
        <?php render_ad_slot('wide'); ?>
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
