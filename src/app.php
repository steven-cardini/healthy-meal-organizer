<?php

declare(strict_types=1);

require __DIR__ . '/calendar.php';
require __DIR__ . '/repository.php';
require __DIR__ . '/pages.php';
require __DIR__ . '/test_data.php';

const ROOT_DIR = __DIR__ . '/..';
const SESSION_LIFETIME = 180 * 86400;
const MAX_TEXT_LENGTH = 100;
const MEAL_TIMES = ['lunch' => 'Mittagessen', 'dinner' => 'Abendessen'];

/** Invalid user input; the message is shown in the UI. */
final class UserError extends RuntimeException
{
}

function load_config(string $file): array
{
    if (!is_file($file)) {
        throw new RuntimeException("Config file not found: $file");
    }
    $config = require $file;
    $start = is_string($config['cycle_start_date'] ?? null) ? parse_date($config['cycle_start_date']) : null;
    if (!is_string($config['password_hash'] ?? null) || $config['password_hash'] === ''
        || !is_string($config['data_dir'] ?? null)
        || $start?->format('N') !== '1') {
        throw new RuntimeException('Invalid config, see config.example.php');
    }
    return $config;
}

function open_db(string $dataDir): PDO
{
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0700, true);
    }
    $db = new PDO('sqlite:' . $dataDir . '/db.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    if (schema_version($db) === 0) {
        $db->exec('BEGIN IMMEDIATE');
        if (schema_version($db) === 0) {
            $db->exec(file_get_contents(ROOT_DIR . '/db/schema.sql'));
        }
        $db->exec('COMMIT');
    }
    return $db;
}

function schema_version(PDO $db): int
{
    return (int)$db->query('PRAGMA user_version')->fetchColumn();
}

function start_session(string $dataDir): void
{
    $dir = $dataDir . '/sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    ini_set('session.save_path', $dir);
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.use_strict_mode', '1');
    session_name('menuplan');
    session_set_cookie_params(['lifetime' => SESSION_LIFETIME] + cookie_options());
    session_start();
    // PHP only sends the cookie for new sessions; renew it so it lasts 180 days after the last use.
    if (($_COOKIE[session_name()] ?? null) === session_id()) {
        setcookie(session_name(), session_id(), ['expires' => time() + SESSION_LIFETIME] + cookie_options());
    }
}

function cookie_options(): array
{
    // The PHP dev server runs on http://localhost, see README.md.
    return ['path' => '/', 'secure' => PHP_SAPI !== 'cli-server', 'httponly' => true, 'samesite' => 'Lax'];
}

function logged_in(): bool
{
    return ($_SESSION['authenticated'] ?? false) === true;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_valid(): bool
{
    $token = $_POST['csrf'] ?? null;
    return is_string($token) && is_string($_SESSION['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $token);
}

/** $target: id of the element the message belongs to; null shows it at the top of the page. */
function set_flash(string $text, bool $error, ?string $target = null): void
{
    $_SESSION['flash'] = ['text' => $text, 'error' => $error, 'target' => $target];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/** Collapses whitespace and control characters, trims, and checks the length. */
function clean_text(mixed $value): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new UserError('Ungültige Eingabe.');
    }
    $text = trim(preg_replace('/[\s\p{Cc}]+/u', ' ', $value));
    if (mb_strlen($text) > MAX_TEXT_LENGTH) {
        throw new UserError('Höchstens ' . MAX_TEXT_LENGTH . ' Zeichen erlaubt.');
    }
    return $text;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

/** $nav: active menu item ('day', 'week', 'plan', 'meals'); null hides the menu. */
function render(string $template, string $title, array $vars = [], ?string $nav = null, int $status = 200): never
{
    http_response_code($status);
    $flash = session_status() === PHP_SESSION_ACTIVE ? take_flash() : null;
    extract($vars, EXTR_SKIP);
    ob_start();
    require ROOT_DIR . "/templates/$template.php";
    $content = ob_get_clean();
    require ROOT_DIR . '/templates/layout.php';
    exit;
}

function render_error(int $status, string $message): never
{
    render('error', 'Fehler', ['message' => $message], null, $status);
}

/** HTML for a slot as shown in the day and week view. */
function slot_html(?array $slot): string
{
    return match (true) {
        $slot === null => '<span class="muted">Nicht geplant</span>',
        $slot['meal'] !== null => e($slot['meal']),
        default => '<em>' . e($slot['note']) . '</em>',
    };
}

function flash_html(?array $flash): string
{
    if ($flash === null) {
        return '';
    }
    $class = $flash['error'] ? 'flash error' : 'flash success';
    return '<p class="' . $class . '" role="' . ($flash['error'] ? 'alert' : 'status') . '">' . e($flash['text']) . '</p>';
}
