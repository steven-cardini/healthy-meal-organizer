<?php

declare(strict_types=1);

// Copy to config.php (gitignored). See README.md.
return [
    // Hash of the shared household password. Create it with:
    // php -r 'echo password_hash("YOUR-PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
    'password_hash' => '',

    // A Monday (YYYY-MM-DD): first day of cycle week 1 ("Woche 1").
    'cycle_start_date' => '2026-01-05',

    // Folder for db.sqlite and sessions/. Must be writable by PHP and outside public/.
    'data_dir' => __DIR__ . '/data',

    // true: an empty database (no meals, no slots) is filled with a 14-day test plan. For testing only.
    'load_test_data' => false,
];
