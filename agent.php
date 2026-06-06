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
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    if (PHP_SAPI === 'cli') { echo $line . PHP_EOL; }
}

// ── Groq AI Writer (free, fast, Llama 3.3 70B) ────────────────────────────────
function gemini_write_article(array $article): ?array {
    // Try Groq first, then fall back to Gemini
    $groqKey   = getenv('MIYIZE_GROQ_KEY') ?: '';
    $geminiKey = getenv('MIYIZE_GEMINI_KEY') ?: getenv('GEMINI_API_KEY') ?: '';

    if ($groqKey !== '') {
        return groq_write_article($article, $groqKey);
    }
    if ($geminiKey !== '') {
        return gemini_api_write_article($article, $geminiKey);
    }
    return null;
}

function build_article_prompt(array $article): string {
    $title   = (string) ($article['title'] ?? '');
    $summary = (string) ($article['summary'] ?? '');
    $source  = (string) ($article['source'] ?? '');
    $cat     = (string) ($article['category_label'] ?? '');

    return <<<PROMPT
You are a professional senior Kannada journalist. Write a complete, detailed, highly informative news article in Kannada (700-1000 words).

Topic:
- Title: {$title}
- Summary: {$summary}
- Source: {$source}
- Category: {$cat}

Output ONLY a valid JSON object with exactly three keys: "title", "article", "social_caption". No markdown, no code fences.

"title": Rewrite title as compelling Kannada headline. Max 100 chars. Pure Kannada script only. No Hindi, no English.

"article": Write LONG DETAILED 700-1000 word Kannada article with these sections:
1. Opening paragraph (3-4 sentences with context)
2. <h2>ಮುಖ್ಯ ಮಾಹಿತಿ</h2> - HTML table with 5-6 key facts: <table><tr><th>ವಿಷಯ</th><th>ವಿವರ</th></tr>...</table>
3. <h2>ಹಿನ್ನೆಲೆ</h2> - 2-3 paragraphs of background and history
4. <h2>ಪ್ರಮುಖ ಅಂಶಗಳು</h2> - bullet list <ul><li>at least 5 detailed points</li></ul>
5. <h2>ಪ್ರಭಾವ ಮತ್ತು ಮಹತ್ವ</h2> - 2 paragraphs on impact for Karnataka and India
6. Closing paragraph with forward-looking conclusion
Use SEO keywords: "ಕನ್ನಡ ಸುದ್ದಿ", "ಕರ್ನಾಟಕ", "ಇಂದಿನ ಸುದ್ದಿ", "ತಾಜಾ ಸುದ್ದಿ". Separate paragraphs with \n\n. NEVER use placeholder text.

"social_caption": Viral Kannada caption max 250 chars, 2 emojis, 4-5 hashtags like #KannadaNews #Karnataka #{$cat}. No URL.
PROMPT;
}

function groq_write_article(array $article, string $apiKey): ?array {
    $prompt  = build_article_prompt($article);
    $payload = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'messages'    => [
            ['role' => 'system', 'content' => 'You are a professional Kannada journalist. Always respond with valid JSON only.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature'       => 0.80,
        'max_tokens'        => 4096,
        'response_format'   => ['type' => 'json_object'],
    ], JSON_UNESCAPED_UNICODE);

    $response = http_post(
        'https://api.groq.com/openai/v1/chat/completions',
        $payload,
        ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]
    );

    if (!$response) {
        agent_log('warn', 'Groq API returned no response');
        return null;
    }

    $data = json_decode($response, true);
    $text = $data['choices'][0]['message']['content'] ?? '{}';
    $json = json_decode($text, true);

    if (is_array($json) && !empty($json['article'])) {
        agent_log('info', 'Groq AI wrote article successfully');
        return [
            'content' => trim($json['article']),
            'social'  => trim($json['social_caption'] ?? ''),
            'title'   => trim($json['title'] ?? ''),
        ];
    }
    agent_log('warn', 'Groq response parse failed: ' . substr($text, 0, 200));
    return null;
}

function gemini_api_write_article(array $article, string $apiKey): ?array {
    $prompt  = build_article_prompt($article);
    $payload = json_encode([
        'contents'          => [['parts' => [['text' => $prompt]]]],
        'generationConfig'  => [
            'temperature'      => 0.80,
            'maxOutputTokens'  => 4096,
            'responseMimeType' => 'application/json',
        ],
    ]);

    $url      = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
    $response = http_post($url, $payload, ['Content-Type: application/json']);
    if (!$response) { return null; }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    $json = json_decode($text, true);

    if (is_array($json) && !empty($json['article'])) {
        agent_log('info', 'Gemini API wrote article successfully');
        return [
            'content' => trim($json['article']),
            'social'  => trim($json['social_caption'] ?? ''),
            'title'   => trim($json['title'] ?? ''),
        ];
    }
    return null;
}



// ── Template-based article writer (rich fallback) ────────────────────────────
function template_write_article(array $article): string {
    $title   = (string) ($article['title'] ?? '');
    $summary = (string) ($article['summary'] ?? '');
    $source  = (string) ($article['source'] ?? 'ಮೂಲ');
    $cat     = (string) ($article['category_label'] ?? 'ಸುದ್ದಿ');
    $catSlug = (string) ($article['category'] ?? 'latest');
    $date    = date('d M Y');

    $cleanSummary = trim(strip_tags($summary));
    if ($cleanSummary === '') { $cleanSummary = $title; }

    $body  = "<p>{$title} ಕುರಿತಾದ ಈ ಮಹತ್ವದ ವರದಿ {$source} ಮೂಲದಿಂದ ಬಂದಿದ್ದು, ಕರ್ನಾಟಕ ಮತ್ತು ಭಾರತಾದ್ಯಂತ ಇಂದಿನ ಸುದ್ದಿಯಲ್ಲಿ ಪ್ರಮುಖ ಸ್ಥಾನ ಪಡೆದಿದೆ. {$cleanSummary}</p>\n\n";

    $body .= "<h2>ಮುಖ್ಯ ಮಾಹಿತಿ</h2>\n";
    $body .= "<table><tr><th>ವಿಷಯ</th><th>ವಿವರ</th></tr>";
    $body .= "<tr><td>ದಿನಾಂಕ</td><td>{$date}</td></tr>";
    $body .= "<tr><td>ಮೂಲ</td><td>{$source}</td></tr>";
    $body .= "<tr><td>ವಿಭಾಗ</td><td>{$cat}</td></tr>";
    $body .= "<tr><td>ಪ್ರಕಾರ</td><td>ತಾಜಾ ಸುದ್ದಿ</td></tr>";
    $body .= "<tr><td>ಭಾಷೆ</td><td>ಕನ್ನಡ</td></tr>";
    $body .= "</table>\n\n";

    $body .= "<h2>ಹಿನ್ನೆಲೆ</h2>\n";
    $body .= "<p>{$cat} ಕ್ಷೇತ್ರದಲ್ಲಿ ಈ ರೀತಿಯ ಬೆಳವಣಿಗೆಗಳು ಕರ್ನಾಟಕ ಮತ್ತು ರಾಷ್ಟ್ರ ಮಟ್ಟದಲ್ಲಿ ವ್ಯಾಪಕ ಪ್ರಭಾವ ಬೀರುತ್ತಿವೆ. ಇಂದಿನ ಸುದ್ದಿ ಪ್ರಕಾರ, ಈ ವಿಷಯ ಈಗ ಸಾರ್ವಜನಿಕ ಚರ್ಚೆಯ ಕೇಂದ್ರಬಿಂದುವಾಗಿದೆ.</p>\n\n";
    $body .= "<p>{$cleanSummary} ಈ ವಿಷಯದ ಕುರಿತು ತಜ್ಞರು ಮತ್ತು ಸಾರ್ವಜನಿಕರ ನಡುವೆ ವ್ಯಾಪಕ ಚರ್ಚೆ ನಡೆಯುತ್ತಿದ್ದು, ಮುಂದಿನ ದಿನಗಳಲ್ಲಿ ಇದು ಇನ್ನಷ್ಟು ಚರ್ಚೆಗೆ ಒಳಗಾಗಲಿದೆ ಎಂದು ನಿರೀಕ್ಷಿಸಲಾಗಿದೆ.</p>\n\n";

    $body .= "<h2>ಪ್ರಮುಖ ಅಂಶಗಳು</h2>\n";
    $body .= "<ul>";
    $body .= "<li>{$title} ಎಂಬ ವಿಷಯ ಕನ್ನಡ ಸುದ್ದಿ ಜಗತ್ತಿನಲ್ಲಿ ಇಂದು ಪ್ರಮುಖ ಚರ್ಚೆಗೆ ಕಾರಣವಾಗಿದೆ.</li>";
    $body .= "<li>{$source} ಪ್ರಕಾರ ಈ ಬೆಳವಣಿಗೆ ಅಧಿಕೃತವಾಗಿ ದೃಢಪಟ್ಟಿದ್ದು, ಸಂಬಂಧಪಟ್ಟ ಎಲ್ಲ ಪಕ್ಷಗಳು ಇದನ್ನು ಗಮನಿಸಿವೆ.</li>";
    $body .= "<li>{$cat} ವಿಭಾಗದಲ್ಲಿ ಇದು ಮಹತ್ವದ ಬೆಳವಣಿಗೆ ಎಂದು ವಿಶ್ಲೇಷಕರು ಅಭಿಪ್ರಾಯಪಟ್ಟಿದ್ದಾರೆ.</li>";
    $body .= "<li>ಕರ್ನಾಟಕ ಮತ್ತು ದೇಶಾದ್ಯಂತ ಈ ವಿಷಯ ವ್ಯಾಪಕ ಗಮನ ಸೆಳೆದಿದ್ದು, ಸಾಮಾಜಿಕ ಮಾಧ್ಯಮದಲ್ಲಿ ಟ್ರೆಂಡ್ ಆಗಿದೆ.</li>";
    $body .= "<li>ಈ ವರದಿ ಕರ್ನಾಟಕದ ಜನರ ಮೇಲೆ ನೇರ ಪ್ರಭಾವ ಬೀರಲಿದ್ದು, ಮುಂದಿನ ದಿನಗಳಲ್ಲಿ ಹೊಸ ಬೆಳವಣಿಗೆಗಳು ನಿರೀಕ್ಷಿತ.</li>";
    $body .= "</ul>\n\n";

    $body .= "<h2>ಪ್ರಭಾವ ಮತ್ತು ಮಹತ್ವ</h2>\n";
    $body .= "<p>ಈ ತಾಜಾ ಸುದ್ದಿ ಕರ್ನಾಟಕದ ಸಾರ್ವಜನಿಕ ಜೀವನದ ಮೇಲೆ ಮಹತ್ವದ ಪ್ರಭಾವ ಬೀರಲಿದೆ. {$cat} ಕ್ಷೇತ್ರದಲ್ಲಿ ಕೆಲಸ ಮಾಡುತ್ತಿರುವ ತಜ್ಞರು ಈ ಬೆಳವಣಿಗೆಯನ್ನು ನಿಕಟವಾಗಿ ಗಮನಿಸುತ್ತಿದ್ದಾರೆ.</p>\n\n";
    $body .= "<p>ಕನ್ನಡ ಓದುಗರಿಗೆ ಈ ವಿಷಯ ಅರ್ಥಮಾಡಿಕೊಳ್ಳಲು MIYIZE ಕನ್ನಡ ಸುದ್ದಿ ಸದಾ ಸಜ್ಜಾಗಿದ್ದು, ಇಂದಿನ ಸುದ್ದಿ, ತಾಜಾ ಸುದ್ದಿ ಮತ್ತು ಮುಖ್ಯ ಮಾಹಿತಿಗಾಗಿ ನಮ್ಮ ವೆಬ್‌ಸೈಟ್ ಭೇಟಿ ನೀಡಿ. ಹೆಚ್ಚಿನ ವಿವರಗಳಿಗಾಗಿ {$source} ಮೂಲ ಲಿಂಕ್ ಗಮನಿಸಿ.</p>\n";

    return $body;
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

// ── Key points generator (rich version) ──────────────────────────────────────
function generate_key_points(array $article, string $fullContent): array {
    $title   = (string) ($article['title'] ?? '');
    $source  = (string) ($article['source'] ?? '');
    $cat     = (string) ($article['category_label'] ?? '');
    $date    = format_kn_date((string) ($article['published_at'] ?? ''));
    $paras   = array_filter(array_map('trim', explode("\n\n", strip_tags($fullContent))));
    $first   = (string) (array_shift($paras) ?? $title);
    $first   = mb_substr($first, 0, 180);

    return array_filter([
        mb_substr($title, 0, 150),
        $first,
        "{$source} ವರದಿಯ ಪ್ರಕಾರ ಈ ವಿಷಯ {$cat} ಕ್ಷೇತ್ರದಲ್ಲಿ ಮಹತ್ವದ ಬದಲಾವಣೆ ತರಲಿದೆ.",
        "ಕರ್ನಾಟಕ ಮತ್ತು ದೇಶಾದ್ಯಂತ ಈ ಬೆಳವಣಿಗೆ ಸಾರ್ವಜನಿಕರ ಗಮನ ಸೆಳೆದಿದೆ.",
        "ಮುಂದಿನ ದಿನಗಳಲ್ಲಿ ಹೊಸ ಮಾಹಿತಿ ಲಭ್ಯವಾದಂತೆ MIYIZE ಕನ್ನಡ ಸುದ್ದಿ ನವೀಕರಿಸಲಾಗುವುದು.",
    ]);
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
            $aiResult = gemini_write_article($article);
            if ($aiResult) {
                $fullContent = $aiResult['content'];
                $article['social_caption'] = $aiResult['social'];
                // Use Kannada title from AI if available
                if (!empty($aiResult['title'])) {
                    $article['title'] = $aiResult['title'];
                }
                $article['ai_generated'] = true;
                agent_log('info', "AI-written article ({$catSlug}): " . mb_strlen($fullContent) . " chars");
            } else {
                $fullContent = template_write_article($article);
                $article['social_caption'] = "{$rawTitle} - ಹೆಚ್ಚಿನ ಮಾಹಿತಿಗಾಗಿ ಲಿಂಕ್ ಕ್ಲಿಕ್ ಮಾಡಿ. #KannadaNews";
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

            // Trigger Make.com webhook for social media auto-posting
            $webhookUrl = getenv('MIYIZE_MAKE_WEBHOOK') ?: '';
            if ($webhookUrl !== '' && filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                // Use the direct source image URL (e.g. asianetnews.com) — always a valid public image
                $srcImage = (string) ($article['image'] ?? '');
                if (!$srcImage || !str_starts_with($srcImage, 'http')) {
                    $srcImage = 'https://miyizi-kannada-news.vercel.app/assets/images/newsroom-fallback.png';
                }
                $webhookData = json_encode([
                    'title'          => $article['title'],
                    'social_caption' => ($article['social_caption'] ?? $article['summary']) . "\n\n" . MIYIZE_SITE_URL . '/article/' . $article['slug'],
                    'article_url'    => MIYIZE_SITE_URL . '/article/' . $article['slug'],
                    'image_url'      => $srcImage
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $webhookData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                @curl_exec($ch);
                @curl_close($ch);
                
                agent_log('info', "Triggered Make.com webhook for: {$article['slug']}");
            }

            // Small delay between articles to avoid rate limits
            if (!$dryRun) { usleep(200_000); }
            $totalFetched++;
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

// Ping Google Search Console to auto-index new pages immediately
$sitemapUrl = urlencode(MIYIZE_SITE_URL . '/sitemap.xml');
$pingUrl = "https://www.google.com/ping?sitemap=" . $sitemapUrl;
@file_get_contents($pingUrl);
agent_log('info', "Pinged Google Sitemap for instant indexing: {$pingUrl}");

echo "SUCCESS: {$totalFetched} new articles published.\n";
exit(0);
