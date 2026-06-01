<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$_GET['category'] = 'latest';
require __DIR__ . '/category.php';
