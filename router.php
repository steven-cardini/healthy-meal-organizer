<?php

declare(strict_types=1);

// Router for the PHP dev server: serves existing files in public/, everything else goes to index.php.
$file = realpath(__DIR__ . '/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($file !== false && is_file($file) && str_starts_with($file, __DIR__ . '/public/')) {
    return false;
}
require __DIR__ . '/public/index.php';
