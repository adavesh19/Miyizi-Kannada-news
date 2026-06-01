<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo 'Sitemap: ' . site_path('/sitemap.xml') . "\n";

