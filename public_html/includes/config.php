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
    $base   = $query === null ? 'https://news.google.com/rss' : 'https://news.google.com/rss/search';
    $params = ['hl' => 'kn', 'gl' => 'IN', 'ceid' => 'IN:kn'];
    if ($query !== null) { $params = ['q' => $query] + $params; }
    return $base . '?' . http_build_query($params);
}

$MIYIZE_CATEGORIES = [
    'latest' => [
        'label' => 'ತಾಜಾ ಸುದ್ದಿ', 'short' => 'ತಾಜಾ',
        'feeds' => [
            miyize_google_news_url('ಕನ್ನಡ ಸುದ್ದಿ'),
            miyize_google_news_url('ಇಂದಿನ ಮುಖ್ಯ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'Kannada news newsroom',
    ],
    'karnataka' => [
        'label' => 'ಕರ್ನಾಟಕ', 'short' => 'ಕರ್ನಾಟಕ',
        'feeds' => [
            miyize_google_news_url('ಕರ್ನಾಟಕ ಸುದ್ದಿ'),
            miyize_google_news_url('ಬೆಂಗಳೂರು ಸುದ್ದಿ'),
            'https://kannada.asianetnews.com/rss/category/karnataka',
        ],
        'fallback_query' => 'Karnataka Bengaluru news',
    ],
    'india' => [
        'label' => 'ಭಾರತ', 'short' => 'ಭಾರತ',
        'feeds' => [
            miyize_google_news_url('ಭಾರತ ಸುದ್ದಿ'),
            miyize_google_news_url('ರಾಷ್ಟ್ರೀಯ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'India national news',
    ],
    'world' => [
        'label' => 'ವಿಶ್ವ', 'short' => 'ವಿಶ್ವ',
        'feeds' => [
            miyize_google_news_url('ವಿಶ್ವ ಸುದ್ದಿ'),
            miyize_google_news_url('ಅಂತರಾಷ್ಟ್ರೀಯ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'world international news',
    ],
    'politics' => [
        'label' => 'ರಾಜಕೀಯ', 'short' => 'ರಾಜಕೀಯ',
        'feeds' => [
            miyize_google_news_url('ಭಾರತ ರಾಜಕೀಯ ಸುದ್ದಿ'),
            miyize_google_news_url('ಕರ್ನಾಟಕ ರಾಜಕೀಯ'),
        ],
        'fallback_query' => 'Karnataka India politics',
    ],
    'business' => [
        'label' => 'ವ್ಯಾಪಾರ', 'short' => 'ವ್ಯಾಪಾರ',
        'feeds' => [
            miyize_google_news_url('ವ್ಯಾಪಾರ ಆರ್ಥಿಕತೆ ಸುದ್ದಿ'),
            miyize_google_news_url('ಷೇರು ಮಾರುಕಟ್ಟೆ'),
        ],
        'fallback_query' => 'business market economy',
    ],
    'sports' => [
        'label' => 'ಕ್ರೀಡೆ', 'short' => 'ಕ್ರೀಡೆ',
        'feeds' => [
            miyize_google_news_url('ಕ್ರೀಡೆ ಸುದ್ದಿ'),
            miyize_google_news_url('ಕ್ರಿಕೆಟ್ ಸುದ್ದಿ'),
            miyize_google_news_url('IPL ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'cricket sports IPL news',
    ],
    'cinema' => [
        'label' => 'ಸಿನಿಮಾ', 'short' => 'ಸಿನಿಮಾ',
        'feeds' => [
            miyize_google_news_url('ಕನ್ನಡ ಸಿನಿಮಾ ಸುದ್ದಿ'),
            miyize_google_news_url('ಬಾಲಿವುಡ್ ಸುದ್ದಿ'),
            miyize_google_news_url('ಕಾಲಿವುಡ್ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'Kannada cinema Sandalwood',
    ],
    'technology' => [
        'label' => 'ತಂತ್ರಜ್ಞಾನ', 'short' => 'ಟೆಕ್',
        'feeds' => [
            miyize_google_news_url('ತಂತ್ರಜ್ಞಾನ ಸುದ್ದಿ'),
            miyize_google_news_url('AI ಮೊಬೈಲ್ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'technology AI mobile news',
    ],
    'health' => [
        'label' => 'ಆರೋಗ್ಯ', 'short' => 'ಆರೋಗ್ಯ',
        'feeds' => [
            miyize_google_news_url('ಆರೋಗ್ಯ ಸುದ್ದಿ'),
            miyize_google_news_url('ವೈದ್ಯಕೀಯ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'health medical news India',
    ],
    'education' => [
        'label' => 'ಶಿಕ್ಷಣ', 'short' => 'ಶಿಕ್ಷಣ',
        'feeds' => [
            miyize_google_news_url('ಶಿಕ್ಷಣ ಸುದ್ದಿ'),
            miyize_google_news_url('SSLC PUC ಪರೀಕ್ಷೆ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'education exam results Karnataka',
    ],
    'crime' => [
        'label' => 'ಅಪರಾಧ', 'short' => 'ಅಪರಾಧ',
        'feeds' => [
            miyize_google_news_url('ಅಪರಾಧ ಸುದ್ದಿ ಕರ್ನಾಟಕ'),
            miyize_google_news_url('ಪೊಲೀಸ್ ಸುದ್ದಿ'),
        ],
        'fallback_query' => 'crime police news Karnataka',
    ],
    'fact-check' => [
        'label' => 'ಫ್ಯಾಕ್ಟ್ ಚೆಕ್', 'short' => 'ಫ್ಯಾಕ್ಟ್',
        'feeds' => [
            miyize_google_news_url('ಫ್ಯಾಕ್ಟ್ ಚೆಕ್ ಸುದ್ದಿ'),
            miyize_google_news_url('fact check India Kannada'),
        ],
        'fallback_query' => 'fact check misinformation India',
    ],
];

$MIYIZE_DIRECT_FEEDS = [
    'https://kannada.asianetnews.com/rss',
    'https://www.prajavani.net/feed',
    'https://www.kannadaprabha.com/feed',
];
