<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$slug = (string) ($_GET['slug'] ?? '');
if (!$slug) {
    http_response_code(400);
    exit('Missing slug');
}

$articles = read_json_file(MIYIZE_ARTICLES_FILE);
$article = null;
foreach ($articles as $a) {
    if ($a['slug'] === $slug) {
        $article = $a;
        break;
    }
}

if (!$article) {
    http_response_code(404);
    exit('Article not found');
}

$sourceImg = $article['image'] ?? MIYIZE_FALLBACK_IMAGE;

// If it's a relative path, make it absolute
if (str_starts_with($sourceImg, '/')) {
    $sourceImg = dirname(__DIR__) . $sourceImg;
}

// Load source image
$image = null;
$ext = strtolower(pathinfo($sourceImg, PATHINFO_EXTENSION));

if (str_contains($sourceImg, 'http')) {
    // It's a URL
    $imgData = @file_get_contents($sourceImg);
    if ($imgData) {
        $image = @imagecreatefromstring($imgData);
    }
} else {
    // Local file
    if (file_exists($sourceImg)) {
        $imgData = file_get_contents($sourceImg);
        $image = @imagecreatefromstring($imgData);
    }
}

if (!$image) {
    // Create a blank fallback image
    $image = imagecreatetruecolor(800, 450);
    $bg = imagecolorallocate($image, 20, 20, 20);
    imagefill($image, 0, 0, $bg);
}

// Ensure the image is 800x450 (16:9) for social media
$width = imagesx($image);
$height = imagesy($image);
$targetWidth = 800;
$targetHeight = 450;

$canvas = imagecreatetruecolor($targetWidth, $targetHeight);
imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

// Draw a dark gradient at the bottom for text readability
for ($y = 200; $y < $targetHeight; $y++) {
    $alpha = (int) (($y - 200) / 250 * 127); // 0 to 127
    $alpha = min(120, $alpha); // max opacity
    $color = imagecolorallocatealpha($canvas, 0, 0, 0, 127 - $alpha);
    imageline($canvas, 0, $y, $targetWidth, $y, $color);
}

// Write the Kannada Text
$fontPath = dirname(__DIR__) . '/assets/fonts/NotoSansKannada-Bold.ttf';
$textColor = imagecolorallocate($canvas, 255, 255, 255);
$brandColor = imagecolorallocate($canvas, 225, 6, 0); // Red

$title = $article['title'];
// Word wrap for Kannada is tricky with GD, but we do our best
$wrappedText = wordwrap($title, 40, "\n", true);

// Draw Brand Tag
imagettftext($canvas, 12, 0, 30, $targetHeight - 120, $brandColor, $fontPath, "MIYIZE NEWS");

// Draw Headline
imagettftext($canvas, 24, 0, 30, $targetHeight - 80, $textColor, $fontPath, $wrappedText);

header('Content-Type: image/jpeg');
imagejpeg($canvas, null, 90);

imagedestroy($image);
imagedestroy($canvas);
