<?php

declare(strict_types=1);

// Daily backup of the database by a scheduled task (README.md#backups).

require __DIR__ . '/../src/app.php';

const BACKUPS_KEPT = 14;

$config = load_config(getenv('MENUPLAN_CONFIG') ?: ROOT_DIR . '/config.php');
$source = $config['data_dir'] . '/db.sqlite';
if (!is_file($source)) {
    throw new RuntimeException("Database not found: $source");
}
$dir = $config['data_dir'] . '/backups';
backup_db($source, $dir . '/db-' . today()->format('Y-m-d') . '.sqlite');

foreach (array_slice(glob("$dir/db-*.sqlite"), 0, -BACKUPS_KEPT) as $old) {
    unlink($old);
}
