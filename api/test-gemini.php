<?php
declare(strict_types=1);
// Quick diagnostic: tests if Gemini API key is set and working

$key = getenv('MIYIZE_GEMINI_KEY') ?: getenv('GEMINI_API_KEY') ?: '';

echo "=== GEMINI DIAGNOSTIC ===\n";
echo "Key found: " . ($key !== '' ? 'YES (length=' . strlen($key) . ')' : 'NO - KEY NOT SET') . "\n";

if ($key === '') {
    echo "ERROR: MIYIZE_GEMINI_KEY environment variable is not set!\n";
    exit(1);
}

// Test call to Gemini
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $key;
$payload = json_encode([
    'contents' => [['parts' => [['text' => 'Say hello in Kannada in 10 words.']]]],
    'generationConfig' => ['maxOutputTokens' => 50],
]);

$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
        'content' => $payload,
        'timeout' => 15,
    ],
]);

$response = @file_get_contents($url, false, $context);
$httpCode = $http_response_header[0] ?? 'unknown';

echo "HTTP Status: {$httpCode}\n";

if ($response === false) {
    echo "ERROR: No response from Gemini API. Network issue or invalid key.\n";
    exit(1);
}

$data = json_decode($response, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

if ($text) {
    echo "SUCCESS! Gemini replied: {$text}\n";
} else {
    echo "ERROR from Gemini: " . $response . "\n";
}
