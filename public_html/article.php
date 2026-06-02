<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$slug = (string) ($_GET['slug'] ?? '');
$article = find_article($slug);

if (!$article) {
    http_response_code(404);
    page_head('ಸುದ್ದಿ ಕಂಡುಬಂದಿಲ್ಲ', 'ಈ ಸುದ್ದಿ ಲಭ್ಯವಿಲ್ಲ ಅಥವಾ ಫೀಡ್‌ನಿಂದ ತೆಗೆದುಹಾಕಲಾಗಿದೆ.', '/');
    render_header();
    ?>
    <main id="main">
        <div class="container page-stack">
            <article class="empty-state">
                <h1>ಸುದ್ದಿ ಕಂಡುಬಂದಿಲ್ಲ</h1>
                <p>ದಯವಿಟ್ಟು ಮತ್ತೊಂದು ಸುದ್ದಿ ಓದಿ ಅಥವಾ homepage ಗೆ ಮರಳಿ.</p>
                <a class="button" href="/">Homepage</a>
            </article>
        </div>
    </main>
    <?php
    render_footer();
    exit;
}

$description = excerpt_text((string) ($article['summary'] ?? ''), 220);
page_head((string) $article['title'], $description, article_url($article), $article);
render_header((string) ($article['category'] ?? 'latest'));
$related = array_filter(articles_for_category((string) ($article['category'] ?? 'latest'), 6), static function (array $item) use ($article): bool {
    return ($item['slug'] ?? '') !== ($article['slug'] ?? '');
});
$articleUrlAbsolute = site_path(article_url($article));
$shareText = article_title_core($article) . ' - ' . MIYIZE_SITE_NAME;
$youtubeQuery = rawurlencode(article_title_core($article) . ' Kannada News');
$youtubeEmbed = 'https://www.youtube.com/embed?listType=search&list=' . $youtubeQuery;
?>
<main id="main">
    <div class="container article-layout">
        <article class="article-body">
            <div class="meta-row meta-row--article">
                <a href="<?= e(category_url((string) ($article['category'] ?? 'latest'))) ?>"><?= e((string) ($article['category_label'] ?? 'ತಾಜಾ ಸುದ್ದಿ')) ?></a>
                <span><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
                <span><?= e((string) ($article['source'] ?? 'Source')) ?></span>
            </div>
            <h1><?= e((string) ($article['title'] ?? '')) ?></h1>
            <figure>
                <img src="<?= e(article_image($article)) ?>" alt="" loading="eager" onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';">
            </figure>
            <div class="article-content">
                <?php
                $content = (string) ($article['full_content'] ?? $article['summary'] ?? '');
                $paras = array_filter(array_map('trim', explode("\n\n", $content)));
                foreach ($paras as $p) {
                    if ($p === '<!-- AD_SLOT -->') {
                        render_ad_slot('article');
                        continue;
                    }
                    $safeP = e($p);
                    $safeP = str_replace(
                        ['&lt;span class=&quot;highlight&quot;&gt;', '&lt;/span&gt;'],
                        ['<span class="highlight">', '</span>'],
                        $safeP
                    );
                    echo "<p>{$safeP}</p>\n";
                }
                ?>
            </div>
            <section class="article-insight" aria-label="ಮುಖ್ಯಾಂಶಗಳು">
                <div>
                    <h2>ಪ್ರಮುಖ ಮುಖ್ಯಾಂಶಗಳು (Main Highlights)</h2>
                    <ul>
                        <?php foreach (($article['key_points'] ?? []) as $point): ?>
                            <li><?= e((string) $point) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="trend-meter">
                    <?php $score = (int) ($article['trend_score'] ?? 55); ?>
                    <span>ಟ್ರೆಂಡ್ ಸ್ಕೋರ್</span>
                    <strong><?= e((string) $score) ?></strong>
                    <i style="--score: <?= e((string) min(100, max(8, $score))) ?>%"></i>
                </div>
            </section>
            <section class="fact-table" aria-label="ಸುದ್ದಿ ವಿವರ">
                <h2>ಸುದ್ದಿ ವಿವರ</h2>
                <table class="news-table">
                    <tbody>
                        <?php foreach (($article['quick_facts'] ?? []) as $fact): ?>
                            <tr>
                                <th><?= e((string) ($fact['label'] ?? '')) ?></th>
                                <td><?= e((string) ($fact['value'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <section class="social-video-hub" aria-label="ವಿಡಿಯೋ ಮತ್ತು ಸೋಶಲ್">
                <h2>ವಿಡಿಯೋ ಮತ್ತು ಸೋಶಲ್ ಬೂಸ್ಟ್</h2>
                <div class="social-actions">
                    <a class="share-btn share-btn--whatsapp" href="https://wa.me/?text=<?= e(rawurlencode($shareText . ' ' . $articleUrlAbsolute)) ?>" target="_blank" rel="noopener noreferrer">WhatsApp Share</a>
                    <a class="share-btn share-btn--x" href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode($shareText)) ?>&url=<?= e(rawurlencode($articleUrlAbsolute)) ?>" target="_blank" rel="noopener noreferrer">X Post</a>
                    <a class="share-btn share-btn--telegram" href="https://t.me/share/url?url=<?= e(rawurlencode($articleUrlAbsolute)) ?>&text=<?= e(rawurlencode($shareText)) ?>" target="_blank" rel="noopener noreferrer">Telegram</a>
                    <a class="share-btn share-btn--facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($articleUrlAbsolute)) ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
                </div>
                <div class="video-embed">
                    <iframe src="<?= e($youtubeEmbed) ?>" title="YouTube Kannada News Related Videos" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                </div>
            </section>
            <?php render_ad_slot('article'); ?>
            <div class="source-box">
                <h2>ಮೂಲ ವರದಿ</h2>
                <p>ಈ ಸುದ್ದಿಯ ಮೂಲ ವರದಿಯನ್ನು ಓದಲು ಮೂಲ ವೆಬ್‌ಸೈಟ್‌ಗೆ ಭೇಟಿ ನೀಡಿ. MIYIZE Kannada News ಸುದ್ದಿ ಫೀಡ್‌ನಿಂದ ಮುಖ್ಯಾಂಶ ಮತ್ತು ಸಾರಾಂಶವನ್ನು ಪ್ರದರ್ಶಿಸುತ್ತದೆ.</p>
                <a class="button" href="<?= e((string) ($article['source_url'] ?? '#')) ?>" target="_blank" rel="nofollow noopener">ಮೂಲ ಸುದ್ದಿ ಓದಿ</a>
            </div>
        </article>
        <aside class="article-sidebar">
            <div class="section-title">
                <h2>ಸಂಬಂಧಿತ ಸುದ್ದಿ</h2>
            </div>
            <?php foreach (array_slice(array_values($related), 0, 4) as $item): ?>
                <?php render_article_card($item, 'compact'); ?>
            <?php endforeach; ?>
        </aside>
    </div>
</main>
<?php render_footer(); ?>
