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

// Same values as the CHECK constraints in db/schema.sql. Meaning: docs/requirements.md#meal-tags.
const MEAL_TAGS = [
    'protein_source' => ['label' => 'Proteinquelle', 'values' => [
        'meat' => 'Fleisch (ohne Geflügel)', 'poultry' => 'Geflügel', 'fish' => 'Fisch', 'legumes' => 'Hülsenfrüchte',
        'egg' => 'Ei', 'dairy_cheese' => 'Milchprodukte/Käse', 'vegan_tofu' => 'Tofu/Seitan',
        'none' => 'Keine (möglichst vermeiden)',
    ]],
    'heaviness' => ['label' => 'Bekömmlichkeit', 'values' => [
        'light' => 'Leicht', 'medium' => 'Mittel', 'heavy' => 'Schwer',
    ]],
    'prep_time' => ['label' => 'Zubereitungszeit', 'values' => [
        'quick' => 'Schnell (≤ 20 Min.)', 'medium' => 'Mittel (20–40 Min.)', 'slow' => 'Lang (> 40 Min.)',
    ]],
    'child_factor' => ['label' => 'Kindertauglichkeit', 'values' => [
        'high_flyer' => 'Alle mögen es', 'deconstructed' => 'Baukasten (einzeln servierbar)',
        'acquired_taste' => 'Gewöhnungsbedürftig',
    ]],
    'shelf_life' => ['label' => 'Haltbarkeit', 'hint' => 'der ungekochten Zutaten', 'values' => [
        'short' => 'Kurz (1–2 Tage)', 'medium' => 'Mittel (3–5 Tage)', 'long' => 'Lang (> 5 Tage)',
    ]],
    'leftovers' => ['label' => 'Reste', 'hint' => 'des gekochten Gerichts am Folgetag', 'values' => [
        'reheats_well' => 'Gut aufwärmbar', 'eat_fresh' => 'Frisch essen',
    ]],
    'vegetable_level' => ['label' => 'Gemüse', 'values' => [
        'main_component' => 'Genug enthalten', 'side_dish' => 'Gemüsebeilage kochen', 'raw_veggies_needed' => 'Rohkost dazu',
    ]],
];

/** Tags shown in the day view and the meal list (docs/decisions.md D-25). */
const SUMMARY_TAGS = ['protein_source', 'prep_time', 'child_factor'];

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

/** $nav: active menu item ('day', 'week', 'plan', 'meals'), '' for none; null hides the menu. */
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

/** URL of a file in public/ that changes when the file changes, so browsers never use an outdated copy. */
function asset_url(string $path): string
{
    return $path . '?v=' . filemtime(ROOT_DIR . '/public' . $path);
}

/**
 * HTML for a slot as shown in the day and week view. $plan: see load_plan().
 * $withTags: day view, adds the protein avatar and SUMMARY_TAGS (docs/decisions.md D-25).
 */
function slot_html(array $plan, int $cycleDay, string $mealTime, bool $withTags = false): string
{
    $slot = $plan[$cycleDay][$mealTime] ?? null;
    if ($slot === null) {
        return '<a href="/plan#' . e("slot-$cycleDay-$mealTime") . '" class="unplanned">Nicht geplant <span>+ Planen</span></a>';
    }
    if ($slot['meal'] === null) {
        return '<em class="note">' . e($slot['note']) . '</em>';
    }
    $name = '<p class="meal-name">' . e($slot['meal']) . '</p>';
    $hints = '';
    foreach (slot_hints($plan, $cycleDay, $mealTime) as $kind => $label) {
        $hints .= '<li class="hint-' . e($kind) . '">' . e($label) . '</li>';
    }
    $hints = $hints === '' ? '' : '<ul class="hints" aria-label="Hinweise">' . $hints . '</ul>';
    if (!$withTags) {
        return $name . $hints;
    }
    return '<div class="meal">' . avatar_html($slot['protein_source']) . '<div>' . $name . tags_html($slot) . '</div>'
        . $hints . '</div>';
}

/** Values of SUMMARY_TAGS with their icons. $meal: row with these tag columns. */
function tags_html(array $meal): string
{
    $items = '';
    foreach (SUMMARY_TAGS as $tag) {
        $items .= '<li class="' . e(tag_class($tag, $meal[$tag])) . '"><span class="visually-hidden">'
            . e(MEAL_TAGS[$tag]['label']) . ': </span>' . e(MEAL_TAGS[$tag]['values'][$meal[$tag]]) . '</li>';
    }
    return '<ul class="tags" aria-label="Eigenschaften">' . $items . '</ul>';
}

function avatar_html(string $proteinSource): string
{
    return '<span class="avatar ' . e(tag_class('protein_source', $proteinSource)) . '" aria-hidden="true"></span>';
}

/** CSS class that shows the icon of a tag value (public/assets/app.css). */
function tag_class(string $tag, string $value): string
{
    return "tag-$tag-$value";
}

/** @return array<string, string> kind => label, see docs/requirements.md#meal-tags (FR-14) */
function slot_hints(array $plan, int $cycleDay, string $mealTime): array
{
    $slot = $plan[$cycleDay][$mealTime];
    $sameMealIn = fn(array $neighbour): bool => $slot['leftovers'] === 'reheats_well'
        && ($plan[$neighbour[0]][$neighbour[1]]['meal_id'] ?? null) === $slot['meal_id'];
    $previous = previous_slot($cycleDay, $mealTime);
    if ($sameMealIn($previous)) {
        return ['leftovers' => $previous[1] === 'lunch' ? 'Reste vom Mittagessen' : 'Reste vom Vorabend'];
    }
    $next = next_slot($cycleDay, $mealTime);
    $hints = $sameMealIn($next)
        ? ['cook_double' => 'Doppelte Menge für ' . ($next[1] === 'dinner' ? 'den Abend' : 'morgen Mittag')]
        : [];
    if ($slot['prep_time'] === 'slow') {
        $hints['slow'] = 'Lange Zubereitung';
    }
    if ($slot['vegetable_level'] !== 'main_component') {
        $hints[$slot['vegetable_level']] = MEAL_TAGS['vegetable_level']['values'][$slot['vegetable_level']];
    }
    return $hints;
}

function flash_html(?array $flash): string
{
    if ($flash === null) {
        return '';
    }
    $class = $flash['error'] ? 'flash error' : 'flash success';
    return '<p class="' . $class . '" role="' . ($flash['error'] ? 'alert' : 'status') . '">' . e($flash['text']) . '</p>';
}
