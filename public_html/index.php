<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$articles = load_articles(48);
$lead = $articles[0] ?? null;
$side = array_slice($articles, 1, 4);
$live = array_slice($articles, 0, 8);

page_head(MIYIZE_SITE_NAME, MIYIZE_SITE_TAGLINE, '/');
render_header('latest');
?>
<main id="main">
    <div class="container">
        <?php render_ticker($articles); ?>
        <?php render_ad_slot('wide'); ?>

        <section class="hero-news" aria-label="ಮುಖ್ಯ ಸುದ್ದಿ">
            <div class="hero-news__main">
                <?php if ($lead): ?>
                    <?php render_article_card($lead, 'lead'); ?>
                <?php else: ?>
                    <article class="empty-state">
                        <h1>ಸುದ್ದಿ ಫೀಡ್ ಸಿದ್ಧವಾಗಿದೆ</h1>
                        <p>Hostinger cron job ಓಡಿದ ನಂತರ ಇಲ್ಲಿ ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ ಕಾಣಿಸುತ್ತದೆ.</p>
                    </article>
                <?php endif; ?>
            </div>
            <div class="hero-news__side">
                <?php foreach ($side as $article): ?>
                    <?php render_article_card($article, 'compact'); ?>
                <?php endforeach; ?>
            </div>
            <aside class="live-rail" aria-label="ಲೈವ್ ಅಪ್ಡೇಟ್">
                <div class="section-title">
                    <h2>ಲೈವ್</h2>
                    <a href="/feed.xml">RSS</a>
                </div>
                <?php foreach ($live as $article): ?>
                    <a class="live-item" href="<?= e(article_url($article)) ?>">
                        <time><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></time>
                        <span><?= e((string) ($article['title'] ?? '')) ?></span>
                    </a>
                <?php endforeach; ?>
            </aside>
        </section>

        <section class="follow-strip" aria-label="ಫಾಲೋ">
            <div>
                <h2>WhatsApp ಮತ್ತು Newsletter ಅಪ್ಡೇಟ್</h2>
                <p>ಪ್ರಮುಖ ಸುದ್ದಿ, ಫ್ಯಾಕ್ಟ್ ಚೆಕ್, ಟೆಕ್ ಮತ್ತು ಉದ್ಯೋಗ ಅಪ್ಡೇಟ್‌ಗಳನ್ನು ಒಂದೇ ಜಾಗದಲ್ಲಿ ಪಡೆಯಿರಿ.</p>
            </div>
            <form class="subscribe" action="/contact.php" method="get">
                <input type="email" name="email" placeholder="email@example.com" aria-label="Email">
                <button type="submit">Subscribe</button>
            </form>
        </section>

        <section class="signal-board" aria-label="ಸುದ್ದಿ ಡ್ಯಾಶ್‌ಬೋರ್ಡ್">
            <div class="signal-board__panel">
                <div class="section-title">
                    <h2>ಟ್ರೆಂಡ್ ಸ್ಕ್ಯಾನರ್</h2>
                    <a href="/api/latest.php">Live API</a>
                </div>
                <div class="trend-bars">
                    <?php foreach (array_slice($articles, 0, 5) as $article): ?>
                        <?php $score = (int) ($article['trend_score'] ?? 55); ?>
                        <a class="trend-bar" href="<?= e(article_url($article)) ?>">
                            <span><?= e(excerpt_text((string) ($article['title'] ?? ''), 54)) ?></span>
                            <strong><?= e((string) $score) ?></strong>
                            <i style="--score: <?= e((string) min(100, max(8, $score))) ?>%"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="signal-board__panel">
                <div class="section-title">
                    <h2>ವಿಭಾಗ ಟೇಬಲ್</h2>
                    <a href="/sitemap.xml">SEO</a>
                </div>
                <table class="news-table">
                    <thead>
                        <tr>
                            <th>ವಿಭಾಗ</th>
                            <th>ಸುದ್ದಿ</th>
                            <th>ಸ್ಥಿತಿ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (categories() as $slug => $category): ?>
                            <?php if ($slug === 'latest') { continue; } ?>
                            <?php $count = count(articles_for_category($slug, 0)); ?>
                            <tr>
                                <td><a href="<?= e(category_url($slug)) ?>"><?= e((string) $category['label']) ?></a></td>
                                <td><?= e((string) $count) ?></td>
                                <td><span class="status-pill">Auto</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="category-wall">
            <div class="section-title">
                <h2>ಎಲ್ಲಾ ವಿಭಾಗಗಳು</h2>
                <a href="/sitemap.xml">Sitemap</a>
            </div>
            <div class="category-wall__grid">
                <?php foreach (categories() as $slug => $category): ?>
                    <?php if ($slug === 'latest') { continue; } ?>
                    <?php $categoryArticles = articles_for_category($slug, 4); ?>
                    <?php if (!$categoryArticles) { continue; } ?>
                    <?php $first = $categoryArticles[0]; ?>
                    <section class="category-column">
                        <div class="category-column__head">
                            <h2><?= e((string) $category['label']) ?></h2>
                            <a href="<?= e(category_url($slug)) ?>">ಎಲ್ಲಾ</a>
                        </div>
                        <a class="category-column__image" href="<?= e(article_url($first)) ?>">
                            <img src="<?= e(article_image($first)) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
                        </a>
                        <h3><a href="<?= e(article_url($first)) ?>"><?= e((string) ($first['title'] ?? '')) ?></a></h3>
                        <div class="meta-row">
                            <span><?= e(format_kn_date((string) ($first['published_at'] ?? ''))) ?></span>
                            <span><?= e((string) ($first['source'] ?? 'Source')) ?></span>
                        </div>
                        <?php if (count($categoryArticles) > 1): ?>
                            <ul>
                                <?php foreach (array_slice($categoryArticles, 1) as $item): ?>
                                    <li><a href="<?= e(article_url($item)) ?>"><?= e((string) ($item['title'] ?? '')) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>
<?php render_footer(); ?>
