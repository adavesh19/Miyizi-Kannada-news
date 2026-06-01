<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

page_head('Contact', 'MIYIZE Kannada News ಸಂಪರ್ಕ.', '/contact.php');
render_header();
?>
<main id="main">
    <div class="container page-stack">
        <article class="legal-page">
            <h1>Contact</h1>
            <p>ಸುದ್ದಿ ತಿದ್ದುಪಡಿ, ಜಾಹೀರಾತು, ಸಹಯೋಗ ಮತ್ತು ಫೀಡ್ ವಿಚಾರಗಳಿಗಾಗಿ ಈ ಪುಟದಲ್ಲಿ ನಿಮ್ಮ ಅಧಿಕೃತ ಇಮೇಲ್ ವಿಳಾಸವನ್ನು ಸೇರಿಸಿ.</p>
            <p><strong>Email:</strong> <?= e(MIYIZE_CONTACT_EMAIL) ?></p>
        </article>
    </div>
</main>
<?php render_footer(); ?>
