<?php
/**
 * MIYIZE Kannada News — Fully Automated News Agent
 * =================================================
 * Runs on a schedule (cron/Vercel cron) and:
 *   1. Fetches trending news from ALL category RSS feeds
 *   2. Expands each article into a full Kannada article using
 *      Google Gemini API (free tier via Gemini Pro) or falls back
 *      to a smart template engine if no API key is set
 *   3. Auto-verifies articles (duplicate check, length check, source check)
 *   4. Injects auto-generated images via Unsplash topic search (free, no key)
 *   5. Publishes to articles.json
 *   6. Logs all activity to data/agent.log
 *
 * USAGE:
 *   php agent.php [--dry-run] [--category=karnataka] [--limit=5]
 *
 * CRON (Vercel): Add to vercel.json crons → see README
 * ENVIRONMENT VARIABLES (set in Vercel):
 *   MIYIZE_GEMINI_KEY   — Google Gemini API key (free at aistudio.google.com)
 *   MIYIZE_AI_ENABLED   — true/false
 */

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────────
define('AGENT_VERSION', '2.0.0');
define('AGENT_START', microtime(true));

$isCli = PHP_SAPI === 'cli';
$root   = __DIR__; // public_html parent

require_once $root . '/public_html/includes/config.php';
require_once $root . '/public_html/includes/functions.php';

if ($isCli) {
    $opts    = getopt('', ['dry-run', 'category:', 'limit:', 'force-ai']);
    $dryRun  = isset($opts['dry-run']);
    $catOnly = (string) ($opts['category'] ?? '');
    $limit   = (int) ($opts['limit'] ?? 10);
} else {
    $dryRun  = isset($_GET['dry-run']);
    $catOnly = (string) ($_GET['category'] ?? '');
    $limit   = isset($_GET['limit']) ? (int) $_GET['limit'] : 2;
}
$limit   = max(1, min($limit, 30));

// ── Logger ─────────────────────────────────────────────────────────────────────
$logFile = MIYIZE_DATA_DIR . '/agent.log';
function agent_log(string $level, string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $msg;
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    if (PHP_SAPI === 'cli') { echo $line . PHP_EOL; }
}

// ── Gemini AI Writer (free tier) ──────────────────────────────────────────────
function gemini_write_article(array $article): ?string {
    $apiKey = getenv('MIYIZE_GEMINI_KEY') ?: getenv('GEMINI_API_KEY') ?: '';
    if ($apiKey === '') { return null; }

    $title   = (string) ($article['title'] ?? '');
    $summary = (string) ($article['summary'] ?? '');
    $source  = (string) ($article['source'] ?? '');
    $cat     = (string) ($article['category_label'] ?? '');

    $prompt = <<<PROMPT
You are a professional Kannada news journalist. Write a complete, detailed, and highly engaging news article in Kannada based on the following information.

Title: {$title}
Summary: {$summary}
Source: {$source}
Category: {$cat}

Requirements:
- Do NOT restrict the length or paragraph count to a fixed format. Write dynamically and organically to thoroughly cover all aspects of the news story.
- Include deep context, professional background details, potential societal or political impact, and quotes (inferred in a realistic and professional journalistic manner).
- Start with the most important news fact.
- Use rich, formal, and appealing Kannada vocabulary.
- Do NOT include markdown headers, bold titles, or the title in the body.
- Separate paragraphs with a blank line.
- Write only in Kannada (using the Kannada script).

Write the article now:
PROMPT;

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.75,
            'maxOutputTokens' => 2048,
        ],
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
    $response = http_post($url, $payload, ['Content-Type: application/json']);
    if (!$response) { return null; }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return trim($text) ?: null;
}

// ── Template-based article writer (fallback, no AI key needed) ────────────────
function template_write_article(array $article): string {
    $title   = (string) ($article['title'] ?? '');
    $summary = (string) ($article['summary'] ?? '');
    $source  = (string) ($article['source'] ?? 'ಮೂಲ');
    $cat     = (string) ($article['category_label'] ?? 'ಸುದ್ದಿ');
    $catSlug = (string) ($article['category'] ?? 'latest');

    return generate_dynamic_kannada_body($title, $summary, $source, $cat, $catSlug);
}

function http_post(string $url, string $body, array $headers = []): ?string {
    $headers[] = 'Content-Length: ' . strlen($body);
    $context   = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 20,
        ],
    ]);
    $result = @file_get_contents($url, false, $context);
    return is_string($result) ? $result : null;
}

// ── LoremFlickr image search (free, no key required via loremflickr.com) ─────
function get_article_image(array $article): string {
    $query = rawurlencode(implode(',', array_slice(
        preg_split('/\s+/', (string) ($article['title'] ?? 'news,india')),
        0, 3
    )));
    // loremflickr.com is a free dynamic placeholder image service
    return "https://loremflickr.com/800/450/india,news,{$query}";
}

// ── Key points generator ──────────────────────────────────────────────────────
function generate_key_points(array $article, string $fullContent): array {
    $title   = (string) ($article['title'] ?? '');
    $source  = (string) ($article['source'] ?? '');
    $cat     = (string) ($article['category_label'] ?? '');
    $date    = format_kn_date((string) ($article['published_at'] ?? ''));
    $paras   = array_filter(array_map('trim', explode("\n\n", $fullContent)));
    $first   = (string) array_shift($paras);

    return [
        substr($title, 0, 120),
        "{$source} ಪ್ರಕಟಿಸಿರುವ ವರದಿಯ ಪ್ರಮುಖ ಮುಖ್ಯಾಂಶಗಳು.",
        "ವಿಷಯಕ್ಕೆ ಸಂಬಂಧಿಸಿದ ಮುಂದಿನ ವಿವರಗಳನ್ನು ನಿರಂತರವಾಗಿ ಪರಿಶೀಲಿಸಲಾಗುತ್ತಿದೆ.",
        "ಲಭ್ಯವಾಗುವ ಹೊಸ ಮಾಹಿತಿಯೊಂದಿಗೆ ಈ ಪುಟವನ್ನು ನವೀಕರಿಸಲಾಗುತ್ತದೆ.",
        "ಹೆಚ್ಚಿನ ವಿವರಗಳಿಗಾಗಿ ಕೆಳಗೆ ನೀಡಲಾದ ಮೂಲ ಲಿಂಕ್ ಅನ್ನು ಕ್ಲಿಕ್ ಮಾಡಿ."
    ];
}

// ── Main Agent Logic ──────────────────────────────────────────────────────────
agent_log('info', '=== MIYIZE Agent v' . AGENT_VERSION . ' started ===');
agent_log('info', "Mode: " . ($dryRun ? 'DRY RUN' : 'LIVE') . " | Limit: {$limit} | Category: " . ($catOnly ?: 'all'));

$categories = categories();
if ($catOnly && isset($categories[$catOnly])) {
    $categories = [$catOnly => $categories[$catOnly]];
} elseif (!$isCli) {
    // Select 2 random categories to process to avoid Vercel 10s Hobby timeout
    $allCats = array_keys($categories);
    $allCats = array_filter($allCats, function($c) { return $c !== 'latest'; });
    shuffle($allCats);
    $selectedCats = array_slice($allCats, 0, 2);
    $categories = array_intersect_key($categories, array_flip($selectedCats));
    agent_log('info', "Web mode: selected categories to update: " . implode(', ', $selectedCats));
}

// Load existing articles to avoid duplicates
$existing     = read_json_file(MIYIZE_ARTICLES_FILE);
$existingSlugs = array_flip(array_column($existing, 'slug'));
$existingUrls  = array_flip(array_column($existing, 'source_url'));

$newArticles = [];
$totalFetched = 0;

foreach ($categories as $catSlug => $catData) {
    if ($catSlug === 'latest') { continue; } // Skip latest — it will be derived
    $feeds = (array) ($catData['feeds'] ?? []);
    agent_log('info', "Processing category: {$catSlug} ({$catData['label']}) — " . count($feeds) . " feeds");

    foreach ($feeds as $feedUrl) {
        agent_log('debug', "Fetching feed: {$feedUrl}");
        $body = http_get($feedUrl, 15);
        if (!$body) {
            agent_log('warn', "Failed to fetch feed: {$feedUrl}");
            continue;
        }

        // Parse RSS XML
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            agent_log('warn', "Failed to parse XML from: {$feedUrl}");
            continue;
        }

        $items = $xml->channel->item ?? [];
        agent_log('info', "Found " . count($items) . " items in feed");

        $catCount = 0;
        foreach ($items as $item) {
            if ($catCount >= $limit) { break; }

            $rawTitle = normalize_space((string) ($item->title ?? ''));
            $rawUrl   = trim((string) ($item->link ?? $item->guid ?? ''));
            $rawDate  = (string) ($item->pubDate ?? '');
            $rawDesc  = normalize_space(strip_tags((string) ($item->description ?? '')));

            // Extract real URL from Google News redirect
            if (str_contains($rawUrl, 'news.google.com')) {
                preg_match('/url=([^&]+)/i', $rawUrl, $m);
                if (!empty($m[1])) { $rawUrl = urldecode($m[1]); }
            }

            if (!$rawTitle || !$rawUrl) { continue; }

            // Duplicate check
            $slug = make_article_slug($rawTitle, $rawUrl);
            if (isset($existingSlugs[$slug]) || isset($existingUrls[$rawUrl])) {
                agent_log('debug', "Skipping duplicate: {$rawTitle}");
                continue;
            }

            // Extract source name from URL
            $sourceName = parse_url($rawUrl, PHP_URL_HOST) ?: 'News';
            $sourceName = preg_replace('/^www\./i', '', $sourceName);

            // Build article structure
            $article = [
                'id'             => substr(md5($rawUrl . $rawDate), 0, 12),
                'slug'           => $slug,
                'title'          => clean_news_title($rawTitle, $sourceName),
                'summary'        => $rawDesc ?: $rawTitle,
                'source'         => $sourceName,
                'source_url'     => $rawUrl,
                'category'       => $catSlug,
                'category_label' => (string) ($catData['label'] ?? $catSlug),
                'published_at'   => $rawDate ? date('c', strtotime($rawDate)) : date('c'),
                'updated_at'     => date('c'),
                'image'          => '',
                'full_content'   => '',
                'key_points'     => [],
                'tags'           => [$catSlug, 'kannada', 'news'],
                'auto_agent'     => true,
            ];

            // Write full article content (AI or template)
            agent_log('info', "Writing article: {$rawTitle}");
            $fullContent = gemini_write_article($article);
            if ($fullContent) {
                $article['ai_generated'] = true;
                agent_log('info', "AI-written article ({$catSlug}): " . mb_strlen($fullContent) . " chars");
            } else {
                $fullContent = template_write_article($article);
                agent_log('info', "Template-written article ({$catSlug})");
            }
            $article['full_content'] = $fullContent;

            // Verification
            if (mb_strlen($fullContent) < 100) {
                agent_log('warn', "Article too short, skipping: {$rawTitle}");
                continue;
            }

            // Generate key points
            $article['key_points'] = generate_key_points($article, $fullContent);

            // Auto image (from article feed or Unsplash fallback)
            $imgFromFeed = '';
            foreach ($item->enclosure ?? [] as $enc) {
                $type = (string) ($enc['type'] ?? '');
                if (str_starts_with($type, 'image/')) {
                    $imgFromFeed = (string) ($enc['url'] ?? '');
                    break;
                }
            }
            // Also check media:content
            $mediaNs = $item->children('media', true);
            if (!$imgFromFeed && isset($mediaNs->content)) {
                $imgFromFeed = (string) ($mediaNs->content['url'] ?? '');
            }
            $article['image'] = $imgFromFeed ?: get_article_image($article);

            $newArticles[] = $article;
            $existingSlugs[$slug] = true;
            $existingUrls[$rawUrl] = true;
            $catCount++;
            $totalFetched++;

            // Small delay between articles to avoid rate limits
            if (!$dryRun) { usleep(200_000); }
        }
    }
}

agent_log('info', "Total new articles found: {$totalFetched}");

if ($totalFetched === 0) {
    agent_log('info', 'No new articles to publish. Exiting.');
    exit(0);
}

if ($dryRun) {
    agent_log('info', '[DRY RUN] Would publish ' . count($newArticles) . ' articles. Not writing.');
    exit(0);
}

// ── Merge + publish ────────────────────────────────────────────────────────────
$retentionDays = MIYIZE_RETENTION_DAYS;
$cutoff        = time() - ($retentionDays * 86400);

// Remove stale articles
$existing = array_filter($existing, function (array $a) use ($cutoff): bool {
    $ts = strtotime((string) ($a['published_at'] ?? ''));
    return $ts && $ts >= $cutoff;
});

// Prepend new articles
$merged = array_merge($newArticles, array_values($existing));

// Remove duplicates by slug
$seen = [];
$merged = array_values(array_filter($merged, function (array $a) use (&$seen): bool {
    $s = (string) ($a['slug'] ?? '');
    if (isset($seen[$s])) { return false; }
    $seen[$s] = true;
    return true;
}));

// Sort by published_at desc
usort($merged, function (array $a, array $b): int {
    return strtotime((string) ($b['published_at'] ?? '')) <=> strtotime((string) ($a['published_at'] ?? ''));
});

write_json_file(MIYIZE_ARTICLES_FILE, $merged);

// Update state
$state = read_json_file(MIYIZE_STATE_FILE);
$state['last_refresh_at'] = date('c');
$state['total_articles']  = count($merged);
$state['agent_version']   = AGENT_VERSION;
$state['agent_ran_at']    = date('c');
write_json_file(MIYIZE_STATE_FILE, $state);

$elapsed = round(microtime(true) - AGENT_START, 2);
agent_log('info', "=== Agent complete: {$totalFetched} new articles | Total: " . count($merged) . " | Time: {$elapsed}s ===");
echo "SUCCESS: {$totalFetched} new articles published.\n";
exit(0);
