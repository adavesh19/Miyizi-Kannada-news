<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$slug    = (string) ($_GET['slug'] ?? '');
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

$catSlug        = (string) ($article['category'] ?? 'latest');
$catLabel       = (string) ($article['category_label'] ?? category_label($catSlug));
$description    = excerpt_text((string) ($article['summary'] ?? ''), 220);
$articleUrl     = article_url($article);
$articleUrlAbs  = site_path($articleUrl);
$shareText      = article_title_core($article) . ' - ' . MIYIZE_SITE_NAME;
$youtubeQuery   = rawurlencode(article_title_core($article) . ' Kannada News');
$youtubeEmbed   = 'https://www.youtube.com/embed?listType=search&list=' . $youtubeQuery;

$related = array_filter(
    articles_for_category($catSlug, 8),
    static fn(array $item): bool => ($item['slug'] ?? '') !== ($article['slug'] ?? '')
);

// FAQ schema from key_points
$faqSchema = null;
if (!empty($article['key_points']) && count($article['key_points']) >= 2) {
    $faqItems = [];
    foreach (array_slice($article['key_points'], 0, 4) as $i => $pt) {
        $faqItems[] = [
            '@type' => 'Question',
            'name'  => 'Key Point ' . ($i + 1) . ': ' . mb_substr((string) $pt, 0, 80),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $pt],
        ];
    }
    $faqSchema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqItems];
}

page_head((string) $article['title'], $description, $articleUrl, $article);
render_header($catSlug);
?>
<?php if ($faqSchema): ?>
<script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<!-- Breadcrumb JSON-LD -->
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_path('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $catLabel, 'item' => site_path(category_url($catSlug))],
        ['@type' => 'ListItem', 'position' => 3, 'name' => (string) ($article['title'] ?? ''), 'item' => $articleUrlAbs],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<main id="main">
    <div class="container art-page">

        <!-- Breadcrumb -->
        <nav class="cat-breadcrumb" aria-label="breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">›</span>
            <a href="<?= e(category_url($catSlug)) ?>"><?= e($catLabel) ?></a>
            <span aria-hidden="true">›</span>
            <span><?= e(excerpt_text((string) ($article['title'] ?? ''), 55)) ?></span>
        </nav>

        <!-- Top ad strip -->
        <?php render_ad_slot('wide'); ?>

        <!-- Main 2-col article layout -->
        <div class="art-layout">

            <!-- ═══ ARTICLE BODY ══════════════════════════════════════════════ -->
            <article class="art-body" itemscope itemtype="https://schema.org/NewsArticle">
                <meta itemprop="datePublished" content="<?= e((string) ($article['published_at'] ?? '')) ?>">
                <meta itemprop="dateModified"  content="<?= e((string) ($article['updated_at'] ?? ($article['published_at'] ?? ''))) ?>">

                <!-- Meta row -->
                <div class="art-meta">
                    <a class="art-meta__cat" href="<?= e(category_url($catSlug)) ?>"><?= e($catLabel) ?></a>
                    <span class="art-meta__time"><?= e(format_kn_date((string) ($article['published_at'] ?? ''))) ?></span>
                    <span class="art-meta__src"><?= e((string) ($article['source'] ?? 'Source')) ?></span>
                    <span class="art-meta__badge live-now">● LIVE</span>
                </div>

                <!-- Title -->
                <h1 itemprop="headline"><?= e((string) ($article['title'] ?? '')) ?></h1>

                <!-- Hero image with 3D float -->
                <figure class="art-figure" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">
                    <img src="<?= e(article_image($article)) ?>" alt="<?= e(excerpt_text((string) ($article['title'] ?? ''), 100)) ?>"
                         loading="eager"
                         onerror="this.onerror=null;this.src='<?= e(MIYIZE_FALLBACK_IMAGE) ?>';"
                         itemprop="url">
                    <div class="art-figure__glow"></div>
                </figure>

                <!-- Share strip (top) -->
                <div class="art-share-strip">
                    <span class="art-share-label">Share:</span>
                    <a class="art-share-btn art-share-btn--wa" href="https://wa.me/?text=<?= e(rawurlencode($shareText . ' ' . $articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </a>
                    <a class="art-share-btn art-share-btn--x" href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode($shareText)) ?>&url=<?= e(rawurlencode($articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer" aria-label="Twitter/X">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.631zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a class="art-share-btn art-share-btn--tg" href="https://t.me/share/url?url=<?= e(rawurlencode($articleUrlAbs)) ?>&text=<?= e(rawurlencode($shareText)) ?>" target="_blank" rel="noopener noreferrer" aria-label="Telegram">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    </a>
                    <a class="art-share-btn art-share-btn--fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <button class="art-share-btn art-share-btn--copy" onclick="navigator.clipboard.writeText('<?= e(addslashes($articleUrlAbs)) ?>');this.textContent='✓ Copied!';setTimeout(()=>this.innerHTML='<svg viewBox=\'0 0 24 24\' fill=\'currentColor\'><path d=\'M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z\'/></svg>',2000)" aria-label="Copy link">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    </button>
                </div>

                <!-- Article content -->
                <div class="art-content" itemprop="articleBody">
                    <?php
                    $content = (string) ($article['full_content'] ?? $article['summary'] ?? '');
                    $paras   = array_filter(array_map('trim', explode("\n\n", $content)));
                    $pIdx    = 0;
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
                        $pIdx++;
                        // Insert in-article ad after paragraph 3
                        if ($pIdx === 3) {
                            render_ad_slot('article');
                        }
                    }
                    ?>
                </div>

                <!-- Key highlights box -->
                <?php if (!empty($article['key_points'])): ?>
                <section class="art-highlights" aria-label="ಮುಖ್ಯಾಂಶಗಳು">
                    <div class="art-highlights__head">
                        <span class="art-highlights__icon">★</span>
                        <h2>ಪ್ರಮುಖ ಮುಖ್ಯಾಂಶಗಳು (Main Highlights)</h2>
                    </div>
                    <ul class="art-highlights__list">
                        <?php foreach (($article['key_points'] ?? []) as $point): ?>
                            <li><?= e((string) $point) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <!-- Trend meter -->
                    <?php $score = (int) ($article['trend_score'] ?? 55); ?>
                    <div class="art-trend-bar">
                        <span>ಟ್ರೆಂಡ್ ಸ್ಕೋರ್</span>
                        <div class="art-trend-track">
                            <div class="art-trend-fill" style="width:<?= e((string) min(100, max(8, $score))) ?>%"></div>
                        </div>
                        <strong><?= e((string) $score) ?>%</strong>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Mid-article ad -->
                <?php render_ad_slot('article'); ?>

                <!-- Fact table -->
                <?php if (!empty($article['quick_facts'])): ?>
                <section class="art-fact-table" aria-label="ಸುದ್ದಿ ವಿವರ">
                    <h2>📋 ಸುದ್ದಿ ವಿವರ</h2>
                    <table class="art-table">
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
                <?php endif; ?>

                <!-- Video + Social hub -->
                <section class="art-social-hub" aria-label="ವಿಡಿಯೋ ಮತ್ತು ಸೋಶಲ್">
                    <h2>📺 ವಿಡಿಯೋ ಮತ್ತು ಸೋಶಲ್ ಬೂಸ್ಟ್</h2>
                    <div class="art-social-btns">
                        <a class="share-btn share-btn--whatsapp" href="https://wa.me/?text=<?= e(rawurlencode($shareText . ' ' . $articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer">📱 WhatsApp</a>
                        <a class="share-btn share-btn--x"         href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode($shareText)) ?>&url=<?= e(rawurlencode($articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer">𝕏 Post</a>
                        <a class="share-btn share-btn--telegram"  href="https://t.me/share/url?url=<?= e(rawurlencode($articleUrlAbs)) ?>&text=<?= e(rawurlencode($shareText)) ?>" target="_blank" rel="noopener noreferrer">✈ Telegram</a>
                        <a class="share-btn share-btn--facebook"  href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer">f Facebook</a>
                    </div>
                    <div class="video-embed">
                        <iframe src="<?= e($youtubeEmbed) ?>" title="YouTube Kannada News Related Videos"
                                loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                </section>

                <!-- Bottom article ad -->
                <?php render_ad_slot('article'); ?>

                <!-- Source box -->
                <div class="art-source-box">
                    <h2>📰 ಮೂಲ ವರದಿ</h2>
                    <p>ಈ ಸುದ್ದಿಯ ಮೂಲ ವರದಿಯನ್ನು ಓದಲು ಮೂಲ ವೆಬ್‌ಸೈಟ್‌ಗೆ ಭೇಟಿ ನೀಡಿ.</p>
                    <a class="button" href="<?= e((string) ($article['source_url'] ?? '#')) ?>" target="_blank" rel="nofollow noopener">ಮೂಲ ಸುದ್ದಿ ಓದಿ →</a>
                </div>

                <!-- Share strip bottom repeat -->
                <div class="art-share-strip art-share-strip--bottom">
                    <span class="art-share-label">Share this article:</span>
                    <a class="art-share-btn art-share-btn--wa"  href="https://wa.me/?text=<?= e(rawurlencode($shareText . ' ' . $articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>
                    <a class="art-share-btn art-share-btn--x"   href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode($shareText)) ?>&url=<?= e(rawurlencode($articleUrlAbs)) ?>" target="_blank" rel="noopener noreferrer" aria-label="X"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.631zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                </div>
            </article>

            <!-- ═══ SIDEBAR ════════════════════════════════════════════════════ -->
            <aside class="art-sidebar">

                <!-- Sticky ad box -->
                <div class="art-sidebar__ad-box">
                    <div class="art-sidebar__ad-label">ADVERTISEMENT</div>
                    <?php render_ad_slot('article'); ?>
                </div>

                <!-- Related news -->
                <div class="art-sidebar__block">
                    <div class="art-sidebar__head">
                        <h2>ಸಂಬಂಧಿತ ಸುದ್ದಿ</h2>
                    </div>
                    <?php foreach (array_slice(array_values($related), 0, 5) as $item): ?>
                        <?php render_article_card($item, 'compact'); ?>
                    <?php endforeach; ?>
                </div>

                <!-- Second sidebar ad -->
                <div class="art-sidebar__ad-box">
                    <div class="art-sidebar__ad-label">SPONSOR</div>
                    <?php render_ad_slot('article'); ?>
                </div>

                <!-- Newsletter signup -->
                <div class="art-sidebar__newsletter">
                    <div class="art-nl-icon">📬</div>
                    <h3>WhatsApp ಸುದ್ದಿ ಅಲರ್ಟ್ ಪಡೆಯಿರಿ</h3>
                    <p>ಕ್ಷಣಕ್ಕೆ ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ ನಿಮ್ಮ ಫೋನಿಗೆ.</p>
                    <a class="button" href="/contact.php">Subscribe Free →</a>
                </div>

                <!-- Category articles -->
                <div class="art-sidebar__block">
                    <div class="art-sidebar__head">
                        <h2><?= e($catLabel) ?> ಸುದ್ದಿ</h2>
                        <a href="<?= e(category_url($catSlug)) ?>">All →</a>
                    </div>
                    <?php foreach (array_slice(array_values($related), 5, 3) as $item): ?>
                        <?php render_article_card($item, 'compact'); ?>
                    <?php endforeach; ?>
                </div>

            </aside>
        </div>

        <!-- Bottom ad -->
        <?php render_ad_slot('wide'); ?>

    </div>
</main>
<?php render_footer(); ?>
