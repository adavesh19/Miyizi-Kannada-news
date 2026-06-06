<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== MIYIZE AI DIAGNOSTIC ===\n\n";

// Check all keys
$groqKey   = getenv('MIYIZE_GROQ_KEY') ?: '';
$geminiKey = getenv('MIYIZE_GEMINI_KEY') ?: '';
$makeHook  = getenv('MIYIZE_MAKE_WEBHOOK') ?: '';
$siteUrl   = getenv('MIYIZE_SITE_URL') ?: '';

echo "1. MIYIZE_GROQ_KEY:      " . ($groqKey   !== '' ? 'SET ✓ (length='.strlen($groqKey).')' : 'NOT SET ✗') . "\n";
echo "2. MIYIZE_GEMINI_KEY:    " . ($geminiKey !== '' ? 'SET ✓ (length='.strlen($geminiKey).')' : 'NOT SET ✗') . "\n";
echo "3. MIYIZE_MAKE_WEBHOOK:  " . ($makeHook  !== '' ? 'SET ✓' : 'NOT SET ✗') . "\n";
echo "4. MIYIZE_SITE_URL:      " . ($siteUrl   !== '' ? $siteUrl : 'NOT SET (using default)') . "\n\n";

// Test Groq API
if ($groqKey !== '') {
    echo "--- Testing Groq API ---\n";
    $payload = json_encode([
        'model'    => 'llama-3.3-70b-versatile',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant. Always respond with valid JSON only.'],
            ['role' => 'user',   'content' => 'Return this JSON: {"hello": "ನಮಸ್ಕಾರ ಕರ್ನಾಟಕ"}'],
        ],
        'max_tokens'      => 50,
        'response_format' => ['type' => 'json_object'],
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$groqKey}\r\nContent-Length: " . strlen($payload),
            'content' => $payload,
            'timeout' => 15,
        ],
    ]);

    $t = microtime(true);
    $response = @file_get_contents('https://api.groq.com/openai/v1/chat/completions', false, $context);
    $elapsed = round((microtime(true) - $t) * 1000) . 'ms';

    if ($response === false) {
        $err = error_get_last();
        echo "ERROR: No response from Groq. " . ($err['message'] ?? 'Unknown error') . "\n";
    } else {
        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? null;
        $httpStatus = $http_response_header[0] ?? 'unknown';

        echo "HTTP Status: {$httpStatus}\n";
        echo "Response time: {$elapsed}\n";

        if ($text) {
            echo "SUCCESS ✓ Groq replied: {$text}\n";
        } else {
            echo "ERROR: Unexpected response:\n" . substr($response, 0, 500) . "\n";
        }
    }
} else {
    echo "--- Groq API: SKIPPED (no key) ---\n";
}

// Test Gemini API
if ($geminiKey !== '') {
    echo "\n--- Testing Gemini API ---\n";
    $payload = json_encode([
        'contents' => [['parts' => [['text' => 'Say hello in Kannada in 5 words']]]],
        'generationConfig' => ['maxOutputTokens' => 50],
    ]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}";
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);

    $t = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $elapsed = round((microtime(true) - $t) * 1000) . 'ms';

    $httpStatus = $http_response_header[0] ?? 'unknown';
    echo "HTTP Status: {$httpStatus}\n";
    echo "Response time: {$elapsed}\n";

    if ($response) {
        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        echo $text ? "SUCCESS ✓ Gemini replied: {$text}\n" : "ERROR: " . substr($response, 0, 300) . "\n";
    } else {
        echo "ERROR: No response from Gemini.\n";
    }
}

echo "\n=== DONE ===\n";
