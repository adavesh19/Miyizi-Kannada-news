<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

maybe_refresh_news();
$query = trim((string) ($_GET['q'] ?? ''));
$results = search_articles($query);

page_head('ಹುಡುಕಾಟ', 'ಕನ್ನಡ ಸುದ್ದಿಗಳಲ್ಲಿ ಹುಡುಕಿ.', '/search.php');
render_header();
?>
<main id="main">
    <div class="container page-stack">
        <header class="page-heading">
            <span>Search</span>
            <h1>ಸುದ್ದಿ ಹುಡುಕಿ</h1>
            <form class="search search--page" method="get" action="/search.php">
                <input name="q" type="search" value="<?= e($query) ?>" placeholder="ವಿಷಯ, ನಗರ, ವ್ಯಕ್ತಿ...">
                <button type="submit">ಹುಡುಕಿ</button>
            </form>
        </header>
        <?php if ($query !== ''): ?>
            <div class="section-title">
                <h2><?= count($results) ?> ಫಲಿತಾಂಶಗಳು</h2>
            </div>
            <div class="story-grid story-grid--archive">
                <?php foreach ($results as $article): ?>
                    <?php render_article_card($article); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php render_footer(); ?>

