<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

define('MIYIZE_SITE_NAME', 'MIYIZE Kannada News');
define('MIYIZE_SITE_TAGLINE', 'ವೇಗದ, ವಿಶ್ವಾಸಾರ್ಹ ಕನ್ನಡ ಸುದ್ದಿ');
define('MIYIZE_SITE_URL', rtrim((string) (getenv('MIYIZE_SITE_URL') ?: 'https://example.com'), '/'));
define('MIYIZE_SITE_LANGUAGE', 'kn-IN');
define('MIYIZE_CONTACT_EMAIL', getenv('MIYIZE_CONTACT_EMAIL') ?: 'editor@example.com');
define('MIYIZE_DATA_DIR', dirname(__DIR__) . '/data');
define('MIYIZE_ARTICLES_FILE', MIYIZE_DATA_DIR . '/articles.json');
define('MIYIZE_STATE_FILE', MIYIZE_DATA_DIR . '/state.json');
define('MIYIZE_FALLBACK_IMAGE', '/assets/images/newsroom-fallback.png');
define('MIYIZE_CACHE_TTL_SECONDS', 300);
define('MIYIZE_RETENTION_DAYS', (int) (getenv('MIYIZE_RETENTION_DAYS') ?: 7));
define('MIYIZE_AUTO_REFRESH_ON_WEB', false);

define('MIYIZE_ADSENSE_CLIENT', getenv('MIYIZE_ADSENSE_CLIENT') ?: '');
define('MIYIZE_AD_SLOT_TOP', getenv('MIYIZE_AD_SLOT_TOP') ?: '');
define('MIYIZE_AD_SLOT_INARTICLE', getenv('MIYIZE_AD_SLOT_INARTICLE') ?: '');

define('MIYIZE_AI_ENABLED', filter_var(getenv('MIYIZE_AI_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('MIYIZE_AI_API_KEY', getenv('MIYIZE_AI_API_KEY') ?: getenv('OPENAI_API_KEY') ?: '');
define('MIYIZE_AI_API_URL', getenv('MIYIZE_AI_API_URL') ?: 'https://api.openai.com/v1/responses');
define('MIYIZE_AI_MODEL', getenv('MIYIZE_AI_MODEL') ?: '');

function miyize_google_news_url(?string $query = null): string
{
    $base = $query === null ? 'https://news.google.com/rss' : 'https://news.google.com/rss/search';
    $params = [
        'hl' => 'kn',
        'gl' => 'IN',
        'ceid' => 'IN:kn',
    ];

    if ($query !== null) {
        $params = ['q' => $query] + $params;
    }

    return $base . '?' . http_build_query($params);
}

$MIYIZE_CATEGORIES = [
    'latest' => [
        'label' => 'ತಾಜಾ ಸುದ್ದಿ',
        'short' => 'ತಾಜಾ',
        'feeds' => [miyize_google_news_url('ಕನ್ನಡ ಸುದ್ದಿ')],
        'fallback_query' => 'Kannada news newsroom',
    ],
    'karnataka' => [
        'label' => 'ಕರ್ನಾಟಕ',
        'short' => 'ಕರ್ನಾಟಕ',
        'feeds' => [miyize_google_news_url('ಕರ್ನಾಟಕ ಸುದ್ದಿ')],
        'fallback_query' => 'Karnataka Bengaluru news',
    ],
    'india' => [
        'label' => 'ಭಾರತ',
        'short' => 'ಭಾರತ',
        'feeds' => [miyize_google_news_url('ಭಾರತ ಸುದ್ದಿ')],
        'fallback_query' => 'India news',
    ],
    'world' => [
        'label' => 'ವಿಶ್ವ',
        'short' => 'ವಿಶ್ವ',
        'feeds' => [miyize_google_news_url('ವಿಶ್ವ ಸುದ್ದಿ')],
        'fallback_query' => 'world news',
    ],
    'business' => [
        'label' => 'ವ್ಯಾಪಾರ',
        'short' => 'ವ್ಯಾಪಾರ',
        'feeds' => [miyize_google_news_url('ವ್ಯಾಪಾರ ಆರ್ಥಿಕತೆ ಸುದ್ದಿ')],
        'fallback_query' => 'business market news',
    ],
    'sports' => [
        'label' => 'ಕ್ರೀಡೆ',
        'short' => 'ಕ್ರೀಡೆ',
        'feeds' => [miyize_google_news_url('ಕ್ರೀಡೆ ಸುದ್ದಿ')],
        'fallback_query' => 'sports news',
    ],
    'cinema' => [
        'label' => 'ಸಿನಿಮಾ',
        'short' => 'ಸಿನಿಮಾ',
        'feeds' => [miyize_google_news_url('ಕನ್ನಡ ಸಿನಿಮಾ ಸುದ್ದಿ')],
        'fallback_query' => 'Kannada cinema',
    ],
    'technology' => [
        'label' => 'ತಂತ್ರಜ್ಞಾನ',
        'short' => 'ಟೆಕ್',
        'feeds' => [miyize_google_news_url('ತಂತ್ರಜ್ಞಾನ ಸುದ್ದಿ')],
        'fallback_query' => 'technology news',
    ],
    'fact-check' => [
        'label' => 'ಫ್ಯಾಕ್ಟ್ ಚೆಕ್',
        'short' => 'ಫ್ಯಾಕ್ಟ್',
        'feeds' => [miyize_google_news_url('ಫ್ಯಾಕ್ಟ್ ಚೆಕ್ ಸುದ್ದಿ')],
        'fallback_query' => 'fact check news',
    ],
];

$MIYIZE_DIRECT_FEEDS = [
    'https://kannada.asianetnews.com/rss',
];
