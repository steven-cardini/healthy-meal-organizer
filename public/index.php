<?php

declare(strict_types=1);

require __DIR__ . '/../src/app.php';

ini_set('display_errors', PHP_SAPI === 'cli-server' ? '1' : '0');
date_default_timezone_set(TIME_ZONE);

header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://cdn.jsdelivr.net; "
    . "img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Strict-Transport-Security: max-age=31536000');

set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    render_error(500, 'Es ist ein Fehler aufgetreten. Versuche es später nochmals.');
});

const ROUTES = [
    'GET /' => 'day_page',
    'GET /week' => 'week_page',
    'GET /plan' => 'plan_page',
    'POST /plan/slot' => 'save_slot_action',
    'GET /meals' => 'meals_page',
    'POST /meals/rename' => 'rename_meal_action',
    'POST /meals/delete' => 'delete_meal_action',
    'GET /login' => 'login_page',
    'POST /login' => 'login_action',
    'POST /logout' => 'logout_action',
];

$config = load_config(getenv('MENUPLAN_CONFIG') ?: ROOT_DIR . '/config.php');
$db = open_db($config['data_dir']);
if (($config['load_test_data'] ?? false) === true) {
    load_test_data_if_empty($db);
}
start_session($config['data_dir']);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : $_SERVER['REQUEST_METHOD'];

if ($path !== '/login' && !logged_in()) {
    redirect('/login');
}
$handler = ROUTES["$method $path"] ?? null;
if ($handler === null) {
    $pathExists = array_filter(array_keys(ROUTES), fn(string $route): bool => str_ends_with($route, " $path")) !== [];
    render_error($pathExists ? 405 : 404, 'Diese Seite gibt es nicht.');
}
if ($method === 'POST' && !csrf_valid()) {
    render_error(400, 'Die Anfrage ist abgelaufen. Lade die Seite neu und versuche es nochmals.');
}
$handler($db, $config);
