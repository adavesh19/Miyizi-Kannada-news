<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function page_head(string $title, string $description, string $canonicalPath = '/', ?array $article = null): void
{
    $canonical = site_path($canonicalPath);
    $image = $article ? article_image($article) : MIYIZE_FALLBACK_IMAGE;
    $imageUrl = str_starts_with($image, 'http') ? $image : site_path($image);
    if ($article && !empty($article['slug'])) {
        $imageUrl = site_path('/api/social-image.php?slug=' . urlencode($article['slug']));
    }
    $pageTitle = $title === MIYIZE_SITE_NAME ? $title : $title . ' | ' . MIYIZE_SITE_NAME;
    $activeSlug = 'latest';
    if (preg_match('~^/category/([^/]+)~', $canonicalPath, $matches) === 1 && isset(categories()[$matches[1]])) {
        $activeSlug = $matches[1];
    } elseif ($article && isset($article['category']) && isset(categories()[(string) $article['category']])) {
        $activeSlug = (string) $article['category'];
    }
    $activeRss = $activeSlug === 'latest' ? '/feed.xml' : '/rss/' . rawurlencode($activeSlug) . '.xml';
    $activeApi = $activeSlug === 'latest' ? '/api/latest.php' : '/api/category/' . rawurlencode($activeSlug) . '.json';
    $keywords = $article && !empty($article['tags']) && is_array($article['tags'])
        ? implode(', ', array_map('strval', $article['tags']))
        : 'Kannada News, Karnataka News, India News, MIYIZE Kannada News';
    ?>
<!doctype html>
<html lang="<?= e(MIYIZE_SITE_LANGUAGE) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="keywords" content="<?= e($keywords) ?>">
    <?php if ($article && !empty($article['tags']) && is_array($article['tags'])): ?>
    <meta name="news_keywords" content="<?= e(implode(', ', array_slice(array_map('strval', $article['tags']), 0, 10))) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    <meta name="referrer" content="no-referrer-when-downgrade">
    <meta property="og:site_name" content="<?= e(MIYIZE_SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="<?= $article ? 'article' : 'website' ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($imageUrl) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="kn_IN">
    <?php if ($article): ?>
    <meta property="article:published_time" content="<?= e((string) ($article['published_at'] ?? gmdate(DATE_ATOM))) ?>">
    <meta property="article:modified_time" content="<?= e((string) ($article['updated_at'] ?? ($article['published_at'] ?? gmdate(DATE_ATOM)))) ?>">
    <meta property="article:author" content="<?= e((string) ($article['source'] ?? MIYIZE_SITE_NAME)) ?>">
    <meta property="article:publisher" content="<?= e(MIYIZE_SITE_URL) ?>">
    <meta property="article:section" content="<?= e((string) ($article['category_label'] ?? 'ತಾಜಾ ಸುದ್ದಿ')) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($imageUrl) ?>">
    <link rel="alternate" type="application/rss+xml" title="<?= e(MIYIZE_SITE_NAME) ?>" href="<?= e(site_path('/feed.xml')) ?>">
    <link rel="alternate" type="application/rss+xml" title="<?= e($pageTitle) ?> RSS" href="<?= e(site_path($activeRss)) ?>">
    <link rel="alternate" type="application/json" title="<?= e($pageTitle) ?> API" href="<?= e(site_path($activeApi)) ?>">
    <link rel="alternate" type="application/json" title="<?= e(MIYIZE_SITE_NAME) ?> Categories API" href="<?= e(site_path('/api/categories.php')) ?>">
    <link rel="preload" href="/assets/css/styles.css" as="style">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <?php if (MIYIZE_ADSENSE_CLIENT !== ''): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= e(MIYIZE_ADSENSE_CLIENT) ?>" crossorigin="anonymous"></script>
    <?php endif; ?>
    <?php if (defined('MIYIZE_GOOGLE_ANALYTICS_ID') && MIYIZE_GOOGLE_ANALYTICS_ID !== ''): ?>
        <!-- Google Analytics (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(MIYIZE_GOOGLE_ANALYTICS_ID) ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?= e(MIYIZE_GOOGLE_ANALYTICS_ID) ?>');
        </script>
    <?php endif; ?>
    <script type="application/ld+json"><?= json_encode(site_schema($article, $canonical, $imageUrl), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <!-- Google Reader Revenue Manager -->
    <script async type="application/javascript" src="https://news.google.com/swg/js/v1/swg-basic.js"></script>
    <script>
      (self.SWG_BASIC = self.SWG_BASIC || []).push( basicSubscriptions => {
        basicSubscriptions.init({
          type: "NewsArticle",
          isPartOfType: ["Product"],
          isPartOfProductId: "CAow4ubGDA:openaccess",
          clientOptions: { theme: "light", lang: "kn" },
        });
      });
    </script>
</head>
<body>
    <div class="gutter-ad gutter-ad--left">
        <?php render_ad_slot('gutter'); ?>
    </div>
    <div class="gutter-ad gutter-ad--right">
        <?php render_ad_slot('gutter'); ?>
    </div>
    <?php
}

function site_schema(?array $article, string $canonical, string $imageUrl): array
{
    $publisher = [
        '@type' => 'Organization',
        'name' => MIYIZE_SITE_NAME,
        'url' => MIYIZE_SITE_URL,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => site_path('/assets/images/newsroom-fallback.png'),
        ],
    ];

    if ($article) {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article['title'] ?? '',
            'description' => excerpt_text((string) ($article['summary'] ?? ''), 220),
            'datePublished' => $article['published_at'] ?? gmdate(DATE_ATOM),
            'dateModified' => $article['updated_at'] ?? ($article['published_at'] ?? gmdate(DATE_ATOM)),
            'mainEntityOfPage' => $canonical,
            'image' => [$imageUrl],
            'author' => [
                '@type' => 'Organization',
                'name' => $article['source'] ?? MIYIZE_SITE_NAME,
            ],
            'publisher' => $publisher,
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'NewsMediaOrganization',
        'name' => MIYIZE_SITE_NAME,
        'url' => MIYIZE_SITE_URL,
        'publishingPrinciples' => site_path('/about.php'),
        'sameAs' => [],
    ];
}

function render_header(string $active = ''): void
{
    $state = live_state();
    $last = $state['last_refresh_at'] ?? null;
    ?>
    <a class="skip-link" href="#main">ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ</a>
    <header class="site-header" data-live-header>
        <div class="topline">
            <div class="container topline__inner">
                <span class="live-dot" aria-hidden="true"></span>
                <span>ಲೈವ್ ಅಪ್ಡೇಟ್</span>
                <span class="topline__time" data-last-refresh="<?= e((string) $last) ?>">ಕೊನೆಯ ನವೀಕರಣ: <?= e(format_kn_date((string) $last)) ?></span>
                <a class="topline__action" href="/contact.php">WhatsApp ನಲ್ಲಿ ಅನುಸರಿಸಿ</a>
                <a class="topline__action" href="/feed.xml">RSS</a>
                <span class="topline__date"><?= e(date('d M Y')) ?></span>
            </div>
        </div>
        <div class="container masthead">
            <a class="brand" href="/" aria-label="<?= e(MIYIZE_SITE_NAME) ?>">
                <span class="brand__mark">M</span>
                <span>
                    <strong>MIYIZE</strong>
                    <em>Kannada News</em>
                </span>
            </a>
            <form class="search" method="get" action="/search.php" role="search">
                <label class="sr-only" for="q">ಸುದ್ದಿ ಹುಡುಕಿ</label>
                <input id="q" name="q" type="search" placeholder="ಸುದ್ದಿ ಹುಡುಕಿ..." autocomplete="off">
                <button type="submit" aria-label="ಹುಡುಕಿ">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m1.1-5.15a7.25 7.25 0 1 1-14.5 0 7.25 7.25 0 0 1 14.5 0Z"/></svg>
                </button>
            </form>
            <aside class="masthead-ad" aria-label="Sponsor">
                <span>SPONSOR</span>
                <strong>ವಿಜ್ಞಾಪನೆ ಜಾಗ</strong>
                <em>728 x 90</em>
            </aside>
        </div>
        <nav class="category-nav" aria-label="ಮುಖ್ಯ ವಿಭಾಗಗಳು">
            <div class="container category-nav__scroll">
                <?php foreach (categories() as $slug => $category): ?>
                    <a class="<?= $active === $slug ? 'is-active' : '' ?>" href="<?= e($slug === 'latest' ? '/' : category_url($slug)) ?>">
                        <?= e((string) $category['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
    </header>
    <?php
}

function render_footer(): void
{
    $state = live_state();
    $last  = $state['last_refresh_at'] ?? null;
    ?>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <a class="brand brand--footer" href="/">
                    <span class="brand__mark">M</span>
                    <span><strong>MIYIZE</strong><em>Kannada News</em></span>
                </a>
                <p><?= e(MIYIZE_SITE_TAGLINE) ?>. RSS ಮೂಲಗಳಿಂದ ಸುದ್ದಿಯನ್ನು ಸಂಗ್ರಹಿಸಿ, ಮೂಲದ ಲಿಂಕ್ ಮತ್ತು ಕ್ರೆಡಿಟ್ ಜೊತೆಗೆ ಪ್ರಕಟಿಸಲಾಗುತ್ತದೆ.</p>
            </div>
            <div>
                <h2>ವಿಭಾಗಗಳು</h2>
                <div class="footer-links">
                    <?php foreach (categories() as $slug => $category): ?>
                        <a href="<?= e($slug === 'latest' ? '/' : category_url($slug)) ?>"><?= e((string) $category['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h2>ಸೈಟ್</h2>
                <div class="footer-links">
                    <a href="/about.php">About</a>
                    <a href="/contact.php">Contact</a>
                    <a href="/privacy.php">Privacy</a>
                    <a href="/feed.xml">RSS</a>
                    <a href="/sitemap.xml">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>
    <script>
        // Client-side rate-limited background refresh trigger
        (function() {
            const last = <?= json_encode($last) ?>;
            if (!last || (Date.now() - new Date(last).getTime() > 30000)) {
                fetch('/api/agent-cron.php').catch(function() {});
            }
        })();
    </script>
    <script src="/assets/js/app.js" defer></script>
    <!-- Vercel Web Analytics -->
    <script>
        window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/insights/script.js"></script>
    <!-- Vercel Speed Insights -->
    <script>
        window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
    <!-- Adsterra Social Bar -->
    <script src="https://pl29618721.effectivecpmnetwork.com/fa/71/40/fa7140f8fd70d945a91e09d88365efae.js"></script>
</body>
</html>
    <?php
}

function render_ad_slot(string $variant = 'wide'): void
{
    $slot = $variant === 'article' ? MIYIZE_AD_SLOT_INARTICLE : MIYIZE_AD_SLOT_TOP;
    ?>
    <aside class="ad-slot ad-slot--<?= e($variant) ?>" aria-label="Advertisement">
        <?php if (MIYIZE_ADSENSE_CLIENT !== '' && $slot !== ''): ?>
            <ins class="adsbygoogle"
                style="display:block"
                data-ad-client="<?= e(MIYIZE_ADSENSE_CLIENT) ?>"
                data-ad-slot="<?= e($slot) ?>"
                data-ad-format="auto"
                data-full-width-responsive="true"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
        <?php else: ?>
            <div style="margin: 20px auto; text-align: center; max-width: 300px; overflow: hidden;" aria-label="Advertisement">
              <script>
                atOptions = {
                  'key' : '9aec88a46e19f5cf7198fdb3621d56de',
                  'format' : 'iframe',
                  'height' : 250,
                  'width' : 300,
                  'params' : {}
                };
              </script>
              <script src="https://www.highperformanceformat.com/9aec88a46e19f5cf7198fdb3621d56de/invoke.js"></script>
            </div>
        <?php endif; ?>
    </aside>
    <?php
}

function render_article_card(array $article, string $variant = 'standard'): void
{
    $image = article_image($article);
    ?>
    <article class="story-card story-card--<?= e($variant) ?>">
        <a class="story-card__image" href="<?= e(article_url($article)) ?>" aria-label="<?= e((string) ($article['title'] ?? '')) ?>">
            <img src="<?= e($image) ?>" alt="" loading="<?= $variant === 'lead' ? 'eager' : 'lazy' ?>" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
        </a>
        <div class="story-card__body">
            <div class="meta-row">
                <a href="<?= e(category_url((string) ($article['category'] ?? 'latest'))) ?>"><?= e((string) ($article['category_label'] ?? category_label((string) ($article['category'] ?? 'latest')))) ?></a>
                <span><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
            </div>
            <h2><a href="<?= e(article_url($article)) ?>"><?= e((string) ($article['title'] ?? '')) ?></a></h2>
            <?php if ($variant !== 'compact'): ?>
                <p><?= e(excerpt_text((string) ($article['summary'] ?? ''), $variant === 'lead' ? 260 : 150)) ?></p>
            <?php endif; ?>
            <div class="source-line">
                <span><?= e((string) ($article['source'] ?? 'Source')) ?></span>
            </div>
        </div>
    </article>
    <?php
}

function render_ticker(array $articles): void
{
    ?>
    <section class="ticker" aria-label="ತಾಜಾ ಸುದ್ದಿ">
        <span class="ticker__label">ಲೈವ್</span>
        <div class="ticker__items">
            <?php foreach (array_slice($articles, 0, 6) as $article): ?>
                <a href="<?= e(article_url($article)) ?>"><?= e((string) ($article['title'] ?? '')) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
