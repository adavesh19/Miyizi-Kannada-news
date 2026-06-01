<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$slug = (string) ($_GET['category'] ?? $_GET['slug'] ?? 'latest');
$allCategories = categories();
if (!isset($allCategories[$slug])) {
    http_response_code(404);
    $slug = 'latest';
}
$label = category_label($slug);
$articles = articles_for_category($slug, 40);

page_head($label, $label . ' ವಿಭಾಗದ ತಾಜಾ ಕನ್ನಡ ಸುದ್ದಿ, ಲೈವ್ ಅಪ್ಡೇಟ್ ಮತ್ತು ಸಂಪೂರ್ಣ ವರದಿ.', category_url($slug));
render_header($slug);
?>
<main id="main">
    <div class="container page-stack">
        <?php render_ticker(load_articles(8)); ?>
        <header class="page-heading">
            <span><?= e(MIYIZE_SITE_NAME) ?></span>
            <h1><?= e($label) ?></h1>
            <p><?= e($label) ?> ವಿಭಾಗದ ಮುಖ್ಯ ಸುದ್ದಿ, ಮೂಲ ಲಿಂಕ್ ಮತ್ತು ಸಮಯದೊಂದಿಗೆ.</p>
        </header>
        <?php render_ad_slot('wide'); ?>
        <div class="story-grid story-grid--archive">
            <?php foreach ($articles as $article): ?>
                <?php render_article_card($article); ?>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<?php render_footer(); ?>

