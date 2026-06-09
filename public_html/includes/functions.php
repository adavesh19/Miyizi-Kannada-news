<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_path(string $path = ''): string
{
    return MIYIZE_SITE_URL . '/' . ltrim($path, '/');
}

function asset_path(string $path): string
{
    return '/' . ltrim($path, '/');
}

function categories(): array
{
    global $MIYIZE_CATEGORIES;
    return $MIYIZE_CATEGORIES;
}

function direct_feeds(): array
{
    global $MIYIZE_DIRECT_FEEDS;
    return $MIYIZE_DIRECT_FEEDS ?? [];
}

function ensure_data_dir(): void
{
    if (!is_dir(MIYIZE_DATA_DIR)) {
        mkdir(MIYIZE_DATA_DIR, 0775, true);
    }
}

function read_json_file(string $path, array $fallback = []): array
{
    if (!is_file($path)) {
        return $fallback;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function write_json_file(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // ── Try GitHub API first (persistent across Vercel deployments) ───────────
    $ghToken = getenv('MIYIZE_GITHUB_TOKEN') ?: '';
    $ghRepo  = getenv('MIYIZE_GITHUB_REPO')  ?: 'adavesh19/Miyizi-Kannada-news';

    if ($ghToken !== '' && $json !== false) {
        // Map local path to repo-relative path
        $repoRoot    = dirname(__DIR__, 2); // project root
        $relPath     = ltrim(str_replace('\\', '/', substr($path, strlen($repoRoot))), '/');
        $apiUrl      = "https://api.github.com/repos/{$ghRepo}/contents/{$relPath}";

        // Get current SHA (needed for update)
        $shaContext = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer {$ghToken}\r\nUser-Agent: MiyiziAgent/2.0\r\nAccept: application/vnd.github+json",
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $shaResp = @file_get_contents($apiUrl, false, $shaContext);
        $sha     = ($shaResp ? (json_decode($shaResp, true)['sha'] ?? null) : null);

        // Commit via GitHub API
        $payload = json_encode(array_filter([
            'message' => 'chore: auto-update ' . basename($path) . ' [skip ci]',
            'content' => base64_encode($json),
            'sha'     => $sha,
            'branch'  => 'master',
        ]));

        $putContext = stream_context_create(['http' => [
            'method'  => 'PUT',
            'header'  => "Authorization: Bearer {$ghToken}\r\nUser-Agent: MiyiziAgent/2.0\r\nContent-Type: application/json\r\nAccept: application/vnd.github+json\r\nContent-Length: " . strlen($payload),
            'content' => $payload,
            'timeout' => 15,
            'ignore_errors' => true,
        ]]);

        $result = @file_get_contents($apiUrl, false, $putContext);
        if ($result !== false) {
            return; // Success — GitHub has the data, Vercel will redeploy
        }
    }

    // ── Fallback: write to /tmp (ephemeral, but better than nothing) ──────────
    ensure_data_dir();
    $tmp = $path . '.tmp';
    @file_put_contents($tmp, $json);
    @rename($tmp, $path);
}


function normalize_space(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function clean_news_title(string $title, string $source = ''): string
{
    $title = normalize_space($title);
    if ($source !== '') {
        $title = preg_replace('/\s+-\s+' . preg_quote($source, '/') . '$/iu', '', $title) ?? $title;
    }

    return $title;
}

function make_article_slug(string $title, string $url): string
{
    $ascii = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title));
    $ascii = trim((string) preg_replace('/-+/', '-', $ascii), '-');
    $prefix = $ascii !== '' ? substr($ascii, 0, 48) : 'kannada-news';
    return $prefix . '-' . substr(sha1($url), 0, 10);
}

function article_url(array $article): string
{
    return '/article/' . rawurlencode((string) ($article['slug'] ?? ''));
}

function category_url(string $slug): string
{
    return '/category/' . rawurlencode($slug);
}

function load_articles(int $limit = 0): array
{
    $articles = read_json_file(MIYIZE_ARTICLES_FILE, []);
    usort($articles, static function (array $a, array $b): int {
        return strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? ''));
    });

    if ($limit > 0) {
        return array_slice($articles, 0, $limit);
    }

    return $articles;
}

function find_article(string $slug): ?array
{
    foreach (load_articles() as $article) {
        if (($article['slug'] ?? '') === $slug) {
            return $article;
        }
    }

    return null;
}

function articles_for_category(string $category, int $limit = 12): array
{
    $filtered = array_values(array_filter(load_articles(), static function (array $article) use ($category): bool {
        return ($article['category'] ?? '') === $category || $category === 'latest';
    }));

    return $limit > 0 ? array_slice($filtered, 0, $limit) : $filtered;
}

function search_articles(string $query, int $limit = 30): array
{
    $needle = mb_strtolower(trim($query), 'UTF-8');
    if ($needle === '') {
        return [];
    }

    $matches = array_filter(load_articles(), static function (array $article) use ($needle): bool {
        $haystack = mb_strtolower(implode(' ', [
            $article['title'] ?? '',
            $article['summary'] ?? '',
            $article['source'] ?? '',
            $article['category_label'] ?? '',
        ]), 'UTF-8');

        return mb_strpos($haystack, $needle, 0, 'UTF-8') !== false;
    });

    return array_slice(array_values($matches), 0, $limit);
}

function article_image(array $article): string
{
    $image = trim((string) ($article['image'] ?? ''));
    return $image !== '' ? $image : MIYIZE_FALLBACK_IMAGE;
}

function article_title_core(array $article): string
{
    return clean_news_title((string) ($article['title'] ?? ''), (string) ($article['source'] ?? ''));
}

function category_label(string $slug): string
{
    $categories = categories();
    return (string) ($categories[$slug]['label'] ?? $slug);
}

function format_kn_date(?string $iso): string
{
    $timestamp = $iso ? strtotime($iso) : false;
    if (!$timestamp) {
        return 'ಇತ್ತೀಚೆಗೆ';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'ಈಗಷ್ಟೆ';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' ನಿಮಿಷ ಹಿಂದೆ';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' ಗಂಟೆ ಹಿಂದೆ';
    }

    return date('d M Y, h:i A', $timestamp);
}

function excerpt_text(string $value, int $limit = 180): string
{
    $value = normalize_space($value);
    if (mb_strlen($value, 'UTF-8') <= $limit) {
        return $value;
    }

    return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . '...';
}

function http_get(string $url, int $timeout = 12): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'MIYIZE Kannada News Bot/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return is_string($body) && $status >= 200 && $status < 400 ? $body : null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "User-Agent: MIYIZE Kannada News Bot/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : null;
}

function absolute_url(string $base, string $maybeRelative): string
{
    if (preg_match('/^https?:\/\//i', $maybeRelative)) {
        return $maybeRelative;
    }

    $parts = parse_url($base);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $maybeRelative;
    }

    if (str_starts_with($maybeRelative, '//')) {
        return $parts['scheme'] . ':' . $maybeRelative;
    }

    if (str_starts_with($maybeRelative, '/')) {
        return $parts['scheme'] . '://' . $parts['host'] . $maybeRelative;
    }

    $dir = isset($parts['path']) ? rtrim(dirname($parts['path']), '/\\') : '';
    return $parts['scheme'] . '://' . $parts['host'] . $dir . '/' . $maybeRelative;
}

function extract_meta_image(string $url): string
{
    $html = http_get($url, 6);
    if ($html === null || $html === '') {
        return '';
    }

    $patterns = [
        '/<meta[^>]+(?:property|name)=["\'](?:og:image|twitter:image|twitter:image:src)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\'](?:og:image|twitter:image|twitter:image:src)["\'][^>]*>/i',
        '/<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $match)) {
            $image = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/^https?:\/\//i', $image) || str_starts_with($image, '/')) {
                return absolute_url($url, $image);
            }
        }
    }

    return '';
}

function http_post_json(string $url, array $payload, array $headers = [], int $timeout = 25): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($body) || $status < 200 || $status >= 300) {
        return null;
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function extract_feed_image(SimpleXMLElement $item): string
{
    $namespaces = $item->getNamespaces(true);

    foreach (['media', 'content'] as $nsKey) {
        if (isset($namespaces[$nsKey])) {
            $media = $item->children($namespaces[$nsKey]);
            foreach (['content', 'thumbnail'] as $nodeName) {
                if (isset($media->{$nodeName})) {
                    foreach ($media->{$nodeName} as $node) {
                        $attrs = $node->attributes();
                        if (isset($attrs['url'])) {
                            return (string) $attrs['url'];
                        }
                    }
                }
            }
        }
    }

    if (isset($item->enclosure)) {
        $attrs = $item->enclosure->attributes();
        if (isset($attrs['url']) && str_starts_with((string) ($attrs['type'] ?? ''), 'image/')) {
            return (string) $attrs['url'];
        }
    }

    $description = (string) ($item->description ?? '');
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $match)) {
        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return '';
}

function ai_summary(array $article): ?string
{
    if (!MIYIZE_AI_ENABLED || MIYIZE_AI_API_KEY === '' || MIYIZE_AI_MODEL === '') {
        return null;
    }

    $input = [
        [
            'role' => 'system',
            'content' => 'You are a careful Kannada news editor. Write only from the provided feed facts. Do not invent facts, numbers, names, quotes, or conclusions. Keep it neutral and original.',
        ],
        [
            'role' => 'user',
            'content' => 'Write a 90-130 word original Kannada news summary from these feed facts. Include source attribution naturally. Facts: ' . json_encode([
                'title' => $article['title'] ?? '',
                'description' => $article['summary'] ?? '',
                'source' => $article['source'] ?? '',
                'published_at' => $article['published_at'] ?? '',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ],
    ];

    $response = http_post_json(MIYIZE_AI_API_URL, [
        'model' => MIYIZE_AI_MODEL,
        'input' => $input,
        'max_output_tokens' => 380,
    ], ['Authorization: Bearer ' . MIYIZE_AI_API_KEY]);

    if (!$response) {
        return null;
    }

    if (isset($response['output_text']) && is_string($response['output_text'])) {
        return normalize_space($response['output_text']);
    }

    foreach (($response['output'] ?? []) as $output) {
        foreach (($output['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                return normalize_space((string) $content['text']);
            }
        }
    }

    return null;
}

function generate_dynamic_kannada_body(string $title, string $summary, string $source, string $category, string $catSlug): string
{
    $title = trim($title);
    $summary = trim(strip_tags($summary));
    if ($summary === '' || mb_strlen($summary, 'UTF-8') < 10) {
        $summary = $title;
    }

    $summarySentences = preg_split('/(?<=[.!?।])\s+/u', $summary);
    $summarySentences = array_values(array_filter(array_map('trim', $summarySentences)));

    $intros = [
        "{$title}. ಈ ಮಹತ್ವದ ಬೆಳವಣಿಗೆಯ ಕುರಿತು ಪ್ರಸ್ತುತ ವ್ಯಾಪಕ ಚರ್ಚೆಗಳು ನಡೆಯುತ್ತಿದ್ದು, ಸಾರ್ವಜನಿಕ ವಲಯದಲ್ಲಿ ತೀವ್ರ ಕುತೂಹಲ ಮೂಡಿಸಿದೆ.",
        "ಇಂದಿನ ಪ್ರಮುಖ ವಿದ್ಯಮಾನಗಳಲ್ಲಿ ಒಂದಾದ {$title} ವಿಷಯವು ಸಾರ್ವಜನಿಕರ ಗಮನ ಸೆಳೆದಿದೆ. ಇದಕ್ಕೆ ಸಂಬಂಧಿಸಿದ ಮಹತ್ವದ ವರದಿಗಳು ಹೊರಬಿದ್ದಿವೆ.",
        "{$title}. ವರದಿಗಳ ಪ್ರಕಾರ, ಈ ಹೊಸ ಬೆಳವಣಿಗೆಯು ಪ್ರಸ್ತುತ ಪರಿಸ್ಥಿತಿಯಲ್ಲಿ ಅತ್ಯಂತ ಪ್ರಭಾವಶಾಲಿ ಪರಿಣಾಮ ಬೀರಲಿದೆ ಎಂದು ಅಂದಾಜಿಸಲಾಗಿದೆ."
    ];

    $connectors = [
        "ಲಭ್ಯವಿರುವ ಪ್ರಾಥಮಿಕ ಮಾಹಿತಿಯ ಪ್ರಕಾರ, ",
        "ಈ ವಿಷಯಕ್ಕೆ ಸಂಬಂಧಿಸಿದಂತೆ ಪ್ರಕಟವಾಗಿರುವ ಅಧಿಕೃತ ವರದಿಯಲ್ಲಿ, ",
        "ಪ್ರಸ್ತುತ ಲಭ್ಯವಿರುವ ವಿವರಗಳ ಅನ್ವಯ, "
    ];

    $details = [
        "ಮುಂಬರುವ ದಿನಗಳಲ್ಲಿ ಈ ನಿರ್ಧಾರವು ಇಡೀ ಕ್ಷೇತ್ರದ ಮೇಲೆ ದೊಡ್ಡ ಪ್ರಮಾಣದ ಪರಿಣಾಮ ಬೀರಬಹುದು ಎಂದು ತಜ್ಞರು ವಿಶ್ಲೇಷಿಸುತ್ತಿದ್ದಾರೆ.",
        "ಸಾರ್ವಜನಿಕರು ಈ ಬದಲಾವಣೆಗೆ ಸಂಬಂಧಿಸಿದಂತೆ ಮಿಶ್ರ ಪ್ರತಿಕ್ರಿಯೆಗಳನ್ನು ವ್ಯಕ್ತಪಡಿಸುತ್ತಿದ್ದು, ಮುಂದಿನ ಹೆಜ್ಜೆಗಳ ಮೇಲೆ ನಿಗಾ ಇರಿಸಿದ್ದಾರೆ.",
        "ಸಂಬಂಧಪಟ್ಟ ಅಧಿಕಾರಿಗಳು ಈ ಹೊಸ ಮಾರ್ಗಸೂಚಿಗಳನ್ನು ಜಾರಿಗೆ ತರಲು ಸಿದ್ಧತೆ ನಡೆಸುತ್ತಿದ್ದು, ಶೀಘ್ರದಲ್ಲೇ ಹೆಚ್ಚಿನ ಸ್ಪಷ್ಟತೆ ಸಿಗುವ ಸಾಧ್ಯತೆಯಿದೆ."
    ];

    $outros = [
        "ಈ ವಿಷಯದ ಪ್ರತಿಯೊಂದು ಹಂತದ ಬೆಳವಣಿಗೆಗಳನ್ನು ಸೂಕ್ಷ್ಮವಾಗಿ ಗಮನಿಸಲಾಗುತ್ತಿದ್ದು, ಹೆಚ್ಚಿನ ಮಾಹಿತಿಗಾಗಿ ಮೂಲ ವರದಿಯನ್ನು ಪರಿಶೀಲಿಸಬಹುದಾಗಿದೆ.",
        "ಹೆಚ್ಚಿನ ವಿವರಗಳು ಮತ್ತು ಅಧಿಕೃತ ಹೇಳಿಕೆಗಳು ಲಭ್ಯವಾದ ತಕ್ಷಣ ಈ ವರದಿಯನ್ನು ನವೀಕರಿಸಲಾಗುತ್ತದೆ."
    ];

    $paragraphs = [];

    shuffle($intros);
    $paragraphs[] = $intros[0];

    shuffle($connectors);
    $paragraphs[] = $connectors[0] . " " . implode(" ", $summarySentences);

    $catContext = [
        'karnataka' => 'ಕರ್ನಾಟಕ ರಾಜ್ಯದ ಹಿತಾಸಕ್ತಿಗಳು ಮತ್ತು ಸ್ಥಳೀಯ ನಾಗರಿಕರ ದೃಷ್ಟಿಯಿಂದ ಈ ಸುದ್ದಿ ಅತ್ಯಂತ ಮಹತ್ವದ್ದಾಗಿದ್ದು, ಪ್ರಮುಖ ಸ್ಥಳಗಳಲ್ಲಿ ಇದರ ಚರ್ಚೆಗಳು ತೀವ್ರಗೊಂಡಿವೆ.',
        'india' => 'ರಾಷ್ಟ್ರೀಯ ಮಟ್ಟದಲ್ಲಿ ಕಂಡುಬರುತ್ತಿರುವ ಪ್ರಮುಖ ಆರ್ಥಿಕ ಮತ್ತು ರಾಜಕೀಯ ಸಮೀಕರಣಗಳಿಗೆ ಈ ಘಟನೆಯು ಹೊಸ ತಿರುವು ನೀಡಬಹುದು ಎಂದು ಭಾವಿಸಲಾಗಿದೆ.',
        'world' => 'ಜಾಗತಿಕ ವಿದ್ಯಮಾನಗಳ ಮೇಲೆ ಈ ಬೆಳವಣಿಗೆಯು ಸೂಕ್ಷ್ಮ ಪರಿಣಾಮ ಬೀರಲಿದ್ದು, ಅಂತರಾಷ್ಟ್ರೀಯ ವಲಯದಲ್ಲಿ ತೀವ್ರ ನಿಗಾ ವಹಿಸಲಾಗಿದೆ.',
        'business' => 'ಹಣಕಾಸು ವಲಯ, ಗ್ರಾಹಕ ಮಾರುಕಟ್ಟೆ ಮತ್ತು ಹೂಡಿಕೆದಾರರ ಮೇಲೆ ಈ ನಿರ್ಧಾರ ನೇರ ಪ್ರಭಾವ ಬೀರಲಿದ್ದು, ಮಾರುಕಟ್ಟೆಯಲ್ಲಿ ಹೊಸ ಚಲನೆಗಳು ಗೋಚರಿಸುತ್ತಿವೆ.',
        'sports' => 'ಕ್ರೀಡಾ ಲೋಕದಲ್ಲಿ ಈ ವಿಷಯವು ಭಾರಿ ಕುತೂಹಲ ಮೂಡಿಸಿದ್ದು, ಕ್ರೀಡಾಭಿಮಾನಿಗಳು ಮತ್ತು ತಜ್ಞರು ಈ ಬಗ್ಗೆ ತಮ್ಮದೇ ಆದ ವಿಶ್ಲೇಷಣೆಗಳನ್ನು ನೀಡುತ್ತಿದ್ದಾರೆ.',
        'cinema' => 'ಮನರಂಜನಾ ಕ್ಷೇತ್ರ ಮತ್ತು ಚಿತ್ರರಂಗದಲ್ಲಿ ಈ ಅಪ್ಡೇಟ್ ಹೊಸ ಆಸಕ್ತಿ ಮೂಡಿಸಿದ್ದು, ಪ್ರೇಕ್ಷಕರ ವಲಯದಲ್ಲಿ ಬಿಸಿ ಬಿಸಿ ಚರ್ಚೆಗಳು ನಡೆಯುತ್ತಿವೆ.',
        'technology' => 'ತಾಂತ್ರಿಕ ಆವಿಷ್ಕಾರಗಳು ಮತ್ತು ಡಿಜಿಟಲ್ ಸೇವೆಗಳ ಬಳಕೆಯ ಮೇಲೆ ಈ ಬೆಳವಣಿಗೆ ಮಹತ್ವದ ಪ್ರಭಾವ ಬೀರಲಿದೆ ಎಂದು ನಿರೀಕ್ಷಿಸಲಾಗಿದೆ.',
        'fact-check' => 'ಸಾಮಾಜಿಕ ಮಾಧ್ಯಮಗಳಲ್ಲಿ ಹರಡುತ್ತಿರುವ ಮಾಹಿತಿಗಳ ನೈಜತೆಯನ್ನು ಪರಿಶೀಲಿಸುವುದು ನಾಗರಿಕರ ಜವಾಬ್ದಾರಿಯಾಗಿದ್ದು, ಅಧಿಕೃತ ಮೂಲಗಳನ್ನೇ ನಂಬಬೇಕು.',
        'politics' => 'ರಾಜಕೀಯ ವಲಯದಲ್ಲಿ ಈ ವಿದ್ಯಮಾನವು ಹೊಸ ತಂತ್ರಗಾರಿಕೆಗಳಿಗೆ ಕಾರಣವಾಗಲಿದ್ದು, ಮುಂಬರುವ ದಿನಗಳಲ್ಲಿ ತೀವ್ರ ರಾಜಕೀಯ ಧ್ರುವೀಕರಣಕ್ಕೆ ಕಾರಣವಾಗಬಹುದು.',
        'health' => 'ಜನಸಾಮಾನ್ಯರ ಆರೋಗ್ಯ ಸುಧಾರಣೆ ಮತ್ತು ದಿನನಿತ್ಯದ ಸುರಕ್ಷತೆಗೆ ಸಂಬಂಧಿಸಿದ ಮಹತ್ವದ ಮಾರ್ಗಸೂಚಿಗಳು ಇದರಲ್ಲಿ ಅಡಕವಾಗಿವೆ.',
        'education' => 'ಶಿಕ್ಷಣ ಕ್ಷೇತ್ರ ಮತ್ತು ವಿದ್ಯಾರ್ಥಿಗಳ ಭವಿಷ್ಯದ ಹಿತದೃಷ್ಟಿಯಿಂದ ಈ ನಿಯಮಗಳು ಅತ್ಯಂತ ನಿರ್ಣಾಯಕ ಪಾತ್ರ ವಹಿಸಲಿವೆ.',
        'crime' => 'ಪೊಲೀಸ್ ಇಲಾಖೆಯು ಪ್ರಕರಣದ ತನಿಖೆಯನ್ನು ತೀವ್ರಗೊಳಿಸಿದ್ದು, ಮುಂಜಾಗ್ರತಾ ಕ್ರಮಗಳನ್ನು ಕೈಗೊಳ್ಳಲು ಸಾರ್ವಜನಿಕರಿಗೆ ಸೂಚಿಸಿದೆ.',
        'agriculture' => 'ರೈತ ಮಿತ್ರರ ಆರ್ಥಿಕ ಪ್ರಗತಿ ಮತ್ತು ಕೃಷಿ ವಲಯದ ಸುಧಾರಣೆಗಳಿಗೆ ಪೂರಕವಾದ ಮಹತ್ವದ ಮಾರ್ಗಸೂಚಿಗಳು ಇಲ್ಲಿ ಪ್ರಸ್ತಾಪವಾಗಿವೆ.',
        'lifestyle' => 'ಆರೋಗ್ಯಕರ ಮತ್ತು ಸುಲಭ ಜೀವನಶೈಲಿಯನ್ನು ಅಳವಡಿಸಿಕೊಳ್ಳಲು ಇಂತಹ ಪ್ರಾಯೋಗಿಕ ಮಾಹಿತಿಗಳು ಸಹಕಾರಿಯಾಗಲಿವೆ.',
        'automobile' => 'ಮಾರುಕಟ್ಟೆಗೆ ಹೊಸ ವಾಹನಗಳ ಪ್ರವೇಶ ಹಾಗೂ ಸುರಕ್ಷತಾ ನಿಯಮಗಳ ಜಾರಿಯು ಗ್ರಾಹಕರಿಗೆ ಹೊಸ ಅನುಭವ ನೀಡಲಿದೆ.',
        'career' => 'ಕರ್ನಾಟಕದಲ್ಲಿ ಸರ್ಕಾರಿ ಉದ್ಯೋಗಾವಕಾಶಗಳು ಮತ್ತು ಸ್ಪರ್ಧಾತ್ಮಕ ಪರೀಕ್ಷೆಗಳ ಸಿದ್ಧತೆಯ ವಿವರಗಳು ಇದರಲ್ಲಿವೆ.',
        'astrology' => 'ದಿನನಿತ್ಯದ ಗ್ರಹಗತಿಗಳು ಮತ್ತು ರಾಶಿಫಲದ ಅನ್ವಯ, ಜ್ಯೋತಿಷಿಗಳು ಈ ದಿನದ ಶುಭ ಫಲಗಳ ಬಗ್ಗೆ ವಿವರಣೆ ನೀಡಿದ್ದಾರೆ.'
    ];
    $paragraphs[] = $catContext[$catSlug] ?? 'ಈ ವಿದ್ಯಮಾನಗಳು ಸಾರ್ವಜನಿಕ ವಲಯದಲ್ಲಿ ತೀವ್ರ ಆಸಕ್ತಿಯನ್ನು ಉಂಟುಮಾಡಿದ್ದು, ಮುಂದಿನ ಹಂತದ ಮಾಹಿತಿ ನಿರೀಕ್ಷಿಸಲಾಗುತ್ತಿದೆ.';

    $paragraphs[] = "<!-- AD_SLOT -->";

    shuffle($details);
    $detailCount = rand(2, 3);
    for ($i = 0; $i < $detailCount; $i++) {
        $paragraphs[] = $details[$i];
    }

    shuffle($outros);
    $paragraphs[] = $outros[0];

    $paragraphs[] = "ಮಾಹಿತಿ ಕೃಪೆ: {$source}";

    return implode("\n\n", $paragraphs);
}

function auto_editor_pack(array $article): array
{
    $title = article_title_core($article);
    $source = (string) ($article['source'] ?? 'ಮೂಲ ವರದಿ');
    $category = (string) ($article['category_label'] ?? 'ಸುದ್ದಿ');
    $catSlug = (string) ($article['category'] ?? 'latest');
    $summary = normalize_space((string) ($article['summary'] ?? ''));
    if ($summary === '' || mb_strlen($summary, 'UTF-8') < 10) {
        $summary = $title;
    }

    $published = (string) ($article['published_at'] ?? gmdate(DATE_ATOM));
    $scoreSeed = abs(crc32($title . $source));
    $trendScore = 42 + ($scoreSeed % 57);
    $pubLabel = format_kn_date($published);

    // Title words for tags & highlight
    $titleWords = array_values(array_filter(preg_split('/\\s+/u', $title), static function(string $w): bool {
        return mb_strlen($w, 'UTF-8') > 2;
    }));
    $titleWords = array_slice($titleWords, 0, 6);

    $tags = array_values(array_filter(array_unique(array_merge(
        [$category, $source],
        $titleWords,
        ['Kannada News', 'MIYIZE', $catSlug]
    ))));
    $tags = array_slice($tags, 0, 10);

    $highlightWord = '';
    if (!empty($titleWords)) {
        $randIndex = $scoreSeed % count($titleWords);
        $highlightWord = $titleWords[$randIndex];
    }
    if ($highlightWord === '') {
        $highlightWord = $category;
    }

    // Generate dynamic body
    $fullContent = generate_dynamic_kannada_body($title, $summary, $source, $category, $catSlug);

    // Apply highlighting
    if (mb_strlen($highlightWord, 'UTF-8') > 3) {
        $regex = '/(' . preg_quote($highlightWord, '/') . ')/iu';
        $fullContent = preg_replace($regex, '<span class="highlight">$1</span>', $fullContent) ?? $fullContent;
    }

    $article['summary'] = $summary;
    $article['full_content'] = isset($article['full_content']) && mb_strlen($article['full_content'], 'UTF-8') > mb_strlen($fullContent, 'UTF-8') ? $article['full_content'] : $fullContent;
    $article['seo_title'] = excerpt_text($title . ' | ' . MIYIZE_SITE_NAME, 68);
    $article['meta_description'] = excerpt_text($summary, 155);
    $article['key_points'] = [
        "{$category} ವಿಭಾಗದ ಪ್ರಮುಖ ಅಪ್ಡೇಟ್ — {$pubLabel} ಪ್ರಕಟಣೆ.",
        "{$source} ಪ್ರಕಟಿಸಿರುವ ವರದಿಯ ಪ್ರಮುಖ ಮುಖ್ಯಾಂಶಗಳು.",
        "ವಿಷಯಕ್ಕೆ ಸಂಬಂಧಿಸಿದ ಮುಂದಿನ ವಿವರಗಳನ್ನು ನಿರಂತರವಾಗಿ ಪರಿಶೀಲಿಸಲಾಗುತ್ತಿದೆ.",
        "ಲಭ್ಯವಾಗುವ ಹೊಸ ಮಾಹಿತಿಯೊಂದಿಗೆ ಈ ಪುಟವನ್ನು ನವೀಕರಿಸಲಾಗುತ್ತದೆ.",
        "ಹೆಚ್ಚಿನ ವಿವರಗಳಿಗಾಗಿ ಕೆಳಗೆ ನೀಡಲಾದ ಮೂಲ ಲಿಂಕ್ ಅನ್ನು ಕ್ಲಿಕ್ ಮಾಡಿ."
    ];
    $article['tags'] = $tags;
    $article['trend_score'] = $article['trend_score'] ?? $trendScore;
    $article['reading_minutes'] = max(1, (int) ceil(mb_strlen((string) $article['full_content'], 'UTF-8') / 650));
    $article['auto_written'] = true;
    $article['quick_facts'] = [
        ['label' => 'ವಿಭಾಗ', 'value' => $category],
        ['label' => 'ಮೂಲ', 'value' => $source],
        ['label' => 'ಪ್ರಕಟಣೆ', 'value' => $pubLabel],
        ['label' => 'ಸ್ಥಿತಿ', 'value' => 'ಪ್ರಕಟಿತ'],
        ['label' => 'ಟ್ರೆಂಡ್ ಸ್ಕೋರ್', 'value' => "{$trendScore}/100"],
        ['label' => 'ಓದುವ ಸಮಯ', 'value' => $article['reading_minutes'] . ' ನಿಮಿಷ']
    ];

    return $article;
}

function parse_feed(string $categorySlug, array $categoryConfig, string $feedUrl): array
{
    $xml = http_get($feedUrl);
    if ($xml === null) {
        return [];
    }

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    if (!$feed instanceof SimpleXMLElement) {
        return [];
    }

    $items = $feed->channel->item ?? [];
    $articles = [];
    foreach ($items as $item) {
        $title = normalize_space((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        if ($title === '' || $link === '') {
            continue;
        }

        $source = '';
        if (isset($item->source)) {
            $source = normalize_space((string) $item->source);
        }
        if ($source === '') {
            $host = parse_url($link, PHP_URL_HOST);
            $source = is_string($host) ? preg_replace('/^www\./', '', $host) : 'News source';
        }

        $title = clean_news_title($title, $source);
        $summary = excerpt_text((string) ($item->description ?? ''), 360);
        $published = strtotime((string) ($item->pubDate ?? '')) ?: time();
        $image = extract_feed_image($item);
        if ($image === '') {
            $image = extract_meta_image($link);
        }

        $article = [
            'id' => sha1($link),
            'slug' => make_article_slug($title, $link),
            'title' => $title,
            'summary' => $summary,
            'source' => $source,
            'source_url' => $link,
            'category' => $categorySlug,
            'category_label' => (string) ($categoryConfig['label'] ?? $categorySlug),
            'image' => $image,
            'published_at' => gmdate(DATE_ATOM, $published),
            'updated_at' => gmdate(DATE_ATOM),
            'ai_generated' => false,
        ];

        $ai = ai_summary($article);
        if ($ai !== null && $ai !== '') {
            $article['summary'] = $ai;
            $article['ai_generated'] = true;
        }

        $article = auto_editor_pack($article);
        $articles[] = $article;
    }

    return $articles;
}

function guess_category_from_text(string $title, string $url, string $description = ''): string
{
    $haystack = mb_strtolower($title . ' ' . $url . ' ' . $description, 'UTF-8');
    $rules = [
        'fact-check' => ['ಫ್ಯಾಕ್ಟ್', 'fact', 'ವೈರಲ್', 'ಸುಳ್ಳು', 'ನಿಜವಾ', 'fake'],
        'technology' => ['ತಂತ್ರಜ್ಞಾನ', 'ಮೊಬೈಲ್', 'ai', 'ಕೃತಕ ಬುದ್ಧಿಮತ್ತೆ', 'whatsapp', 'smartphone', 'tech'],
        'cinema' => ['ಸಿನಿಮಾ', 'ನಟ', 'ನಟಿ', 'ಚಿತ್ರ', 'ott', 'movie', 'film', 'serial', 'celebrity'],
        'sports' => ['ಕ್ರೀಡೆ', 'ಕ್ರಿಕೆಟ್', 'ipl', 't20', 'football', 'match', 'score'],
        'business' => ['ವ್ಯಾಪಾರ', 'ಷೇರು', 'ಮಾರುಕಟ್ಟೆ', 'ಚಿನ್ನ', 'ಬೆಲೆ', 'bank', 'tax', 'business', 'market'],
        'world' => ['ವಿಶ್ವ', 'ಅಮೆರಿಕ', 'ಚೀನಾ', 'ರಷ್ಯಾ', 'trump', 'world', 'global'],
        'india' => ['ಭಾರತ', 'ದೆಹಲಿ', 'ಸರ್ಕಾರ', 'ಲೋಕಸಭೆ', 'ಮೋದಿ', 'india', 'national'],
        'karnataka' => ['ಕರ್ನಾಟಕ', 'ಬೆಂಗಳೂರು', 'ಮೈಸೂರು', 'ಮಂಗಳೂರು', 'ಹುಬ್ಬಳ್ಳಿ', 'ಬೆಳಗಾವಿ', 'bengaluru', 'karnataka'],
        'astrology' => ['ಭವಿಷ್ಯ', 'ರಾಶಿ', 'ದಿನಭವಿಷ್ಯ', 'ಜಾತಕ', 'horoscope', 'astrology'],
        'career' => ['ಉದ್ಯೋಗ', 'ಕೆಲಸ', 'career', 'job', 'recruitment', 'exam', 'ಪರೀಕ್ಷೆ'],
        'automobile' => ['ಕಾರು', 'ಬೈಕು', 'ವಾಹನ', 'car', 'bike', 'automobile', 'vehicle'],
        'lifestyle' => ['ಜೀವನಶೈಲಿ', 'ಫ್ಯಾಷನ್', 'ಆರೋಗ್ಯ', 'lifestyle', 'fashion', 'health', 'beauty'],
        'agriculture' => ['ಕೃಷಿ', 'ರೈತ', 'ಬೆಳೆ', 'agriculture', 'farming', 'farmer', 'crop'],
    ];

    foreach ($rules as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($haystack, mb_strtolower($keyword, 'UTF-8'), 0, 'UTF-8') !== false) {
                return $category;
            }
        }
    }

    return 'latest';
}

function parse_direct_feed(string $feedUrl): array
{
    $xml = http_get($feedUrl);
    if ($xml === null) {
        return [];
    }

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    if (!$feed instanceof SimpleXMLElement) {
        return [];
    }

    $articles = [];
    $categories = categories();
    foreach (array_slice(iterator_to_array($feed->channel->item ?? []), 0, 60) as $item) {
        $link = trim((string) ($item->link ?? ''));
        $source = (string) (parse_url($feedUrl, PHP_URL_HOST) ?: 'News source');
        $title = clean_news_title((string) ($item->title ?? ''), $source);
        if ($title === '' || $link === '') {
            continue;
        }

        $description = (string) ($item->description ?? '');
        $categorySlug = guess_category_from_text($title, $link, $description);
        $published = strtotime((string) ($item->pubDate ?? '')) ?: time();
        $image = extract_feed_image($item);
        if ($image === '') {
            $image = extract_meta_image($link);
        }

        $article = [
            'id' => sha1($link),
            'slug' => make_article_slug($title, $link),
            'title' => $title,
            'summary' => excerpt_text($description, 520),
            'source' => $source,
            'source_url' => $link,
            'category' => $categorySlug,
            'category_label' => (string) ($categories[$categorySlug]['label'] ?? $categories['latest']['label']),
            'image' => $image,
            'published_at' => gmdate(DATE_ATOM, $published),
            'updated_at' => gmdate(DATE_ATOM),
            'ai_generated' => false,
        ];

        $ai = ai_summary($article);
        if ($ai !== null && $ai !== '') {
            $article['summary'] = $ai;
            $article['ai_generated'] = true;
        }

        $articles[] = auto_editor_pack($article);
    }

    return $articles;
}

function run_news_refresh(): array
{
    ensure_data_dir();
    $existing = load_articles();
    $bySource = [];
    foreach ($existing as $article) {
        $existingKey = (string) ($article['source_url'] ?? '');
        if ($existingKey === '' || str_starts_with($existingKey, '/')) {
            $existingKey = (string) ($article['slug'] ?? $article['id'] ?? '');
        }
        if ($existingKey !== '') {
            $bySource[$existingKey] = $article;
        }
    }

    $fetched = 0;
    foreach (direct_feeds() as $feedUrl) {
        $items = parse_direct_feed((string) $feedUrl);
        foreach ($items as $item) {
            $key = (string) ($item['source_url'] ?? $item['slug'] ?? '');
            if ($key === '') {
                continue;
            }

            if (isset($bySource[$key])) {
                $item['summary'] = mb_strlen((string) ($bySource[$key]['summary'] ?? ''), 'UTF-8') > mb_strlen((string) ($item['summary'] ?? ''), 'UTF-8') ? $bySource[$key]['summary'] : $item['summary'];
                $item['image'] = $item['image'] ?: ($bySource[$key]['image'] ?? '');
                $item['ai_generated'] = (bool) ($bySource[$key]['ai_generated'] ?? $item['ai_generated']);
            } else {
                $fetched++;
            }

            $bySource[$key] = auto_editor_pack($item);
        }
    }

    foreach (categories() as $slug => $config) {
        foreach (($config['feeds'] ?? []) as $feedUrl) {
            $items = parse_feed($slug, $config, (string) $feedUrl);
            foreach ($items as $item) {
                $key = (string) ($item['source_url'] ?? '');
                if ($key === '' || str_starts_with($key, '/')) {
                    $key = (string) ($item['slug'] ?? $item['id'] ?? '');
                }
                if ($key === '') {
                    continue;
                }

                if (isset($bySource[$key])) {
                    $item['summary'] = $bySource[$key]['summary'] ?? $item['summary'];
                    $item['image'] = $item['image'] ?: ($bySource[$key]['image'] ?? '');
                    $item['ai_generated'] = (bool) ($bySource[$key]['ai_generated'] ?? $item['ai_generated']);
                } else {
                    $fetched++;
                }

                $bySource[$key] = $item;
            }
        }
    }

    $merged = array_values($bySource);
    usort($merged, static function (array $a, array $b): int {
        return strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? ''));
    });
    $cutoff = time() - (MIYIZE_RETENTION_DAYS * 86400);
    $merged = array_values(array_filter($merged, static function (array $article) use ($cutoff): bool {
        $published = strtotime((string) ($article['published_at'] ?? '')) ?: 0;
        return $published >= $cutoff;
    }));
    $merged = array_slice($merged, 0, 220);

    write_json_file(MIYIZE_ARTICLES_FILE, $merged);
    $state = [
        'last_refresh_at' => gmdate(DATE_ATOM),
        'article_count' => count($merged),
        'new_articles' => $fetched,
        'retention_days' => MIYIZE_RETENTION_DAYS,
        'cache_ttl_seconds' => MIYIZE_CACHE_TTL_SECONDS,
    ];
    write_json_file(MIYIZE_STATE_FILE, $state);

    return $state;
}

function maybe_refresh_news(): void
{
    if (!MIYIZE_AUTO_REFRESH_ON_WEB) {
        return;
    }

    $state = read_json_file(MIYIZE_STATE_FILE, []);
    $last = strtotime((string) ($state['last_refresh_at'] ?? '')) ?: 0;
    if (time() - $last < MIYIZE_CACHE_TTL_SECONDS) {
        return;
    }

    $lock = MIYIZE_DATA_DIR . '/refresh.lock';
    $handle = fopen($lock, 'c');
    if (!$handle) {
        return;
    }

    if (flock($handle, LOCK_EX | LOCK_NB)) {
        run_news_refresh();
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

function live_state(): array
{
    return read_json_file(MIYIZE_STATE_FILE, [
        'last_refresh_at' => null,
        'article_count' => count(load_articles()),
        'new_articles' => 0,
    ]);
}

function pick_items(array $items, int $start, int $count): array
{
    if (empty($items)) {
        return [];
    }
    $picked = [];
    $total = count($items);
    for ($i = 0; $i < $count; $i++) {
        $picked[] = $items[($start + $i) % $total];
    }
    return $picked;
}

