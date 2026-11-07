<?php

declare(strict_types=1);

// Smoke tests. Usage: php tests/smoke.php (uses a temporary data folder, never data/).

require __DIR__ . '/../src/app.php';

$failures = 0;

function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
}

function throws(callable $fn, string $class): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

$tmp = sys_get_temp_dir() . '/menuplan-smoke-' . bin2hex(random_bytes(4));
mkdir($tmp);

// --- Calendar --------------------------------------------------------------

$start = parse_date('2026-01-05');
check('cycle day 1 on start date', cycle_day($start, $start) === 1);
check('cycle day 7 on first Sunday', cycle_day(add_days($start, 6), $start) === 7);
check('cycle day 8 on second Monday', cycle_day(add_days($start, 7), $start) === 8);
check('cycle day 14 on second Sunday', cycle_day(add_days($start, 13), $start) === 14);
check('cycle repeats after 14 days', cycle_day(add_days($start, 14), $start) === 1);
check('day before start is cycle day 14', cycle_day(add_days($start, -1), $start) === 14);
check('14 days before start is cycle day 1', cycle_day(add_days($start, -14), $start) === 1);
$consecutive = true;
$weeksConsistent = true;
for ($date = parse_date('2025-01-01'); $date < parse_date('2028-12-31'); $date = add_days($date, 1)) {
    $consecutive = $consecutive && cycle_day(add_days($date, 1), $start) === cycle_day($date, $start) % 14 + 1;
    $monday = monday_of($date);
    $weeksConsistent = $weeksConsistent && $monday->format('N') === '1'
        && cycle_week(cycle_day($date, $start)) === cycle_week(cycle_day($monday, $start));
}
check('cycle days are consecutive 2025–2028 (incl. DST changes)', $consecutive);
check('every calendar week maps to one cycle week', $weeksConsistent);
check('invalid dates are rejected', parse_date('2026-02-30') === null && parse_date('2026-1-5') === null
    && parse_date('x') === null);
check('German long date', format_long_date(parse_date('2026-11-07')) === 'Samstag, 7. November 2026');
check('German short date', format_short_date(parse_date('2026-03-02')) === 'Mo 2.3.');
check('today uses Europe/Zurich',
    today()->format('Y-m-d') === (new DateTimeImmutable('now', new DateTimeZone('Europe/Zurich')))->format('Y-m-d'));

// --- Database ----------------------------------------------------------------

$db = open_db("$tmp/unit");
check('schema created with version 1', schema_version($db) === 1);
check('reopening keeps the schema', schema_version(open_db("$tmp/unit")) === 1);
check('text is trimmed and whitespace collapsed', clean_text("  Älpler\t magronen \n") === 'Älpler magronen');
check('text over 100 characters is rejected', throws(fn() => clean_text(str_repeat('ä', 101)), UserError::class));
check('invalid UTF-8 is rejected', throws(fn() => clean_text("\xff"), UserError::class));

$mealCount = fn(): int => (int)$db->query('SELECT COUNT(*) FROM meal')->fetchColumn();
save_slot($db, 1, 'lunch', 'meal', 'Älplermagronen');
save_slot($db, 2, 'lunch', 'meal', 'älplermagronen');
save_slot($db, 3, 'lunch', 'meal', 'ÄLPLERMAGRONEN');
check('meal names are unique case-insensitively (umlauts)', $mealCount() === 1);
check('slot holds the meal', load_plan($db)[2]['lunch'] === ['meal' => 'Älplermagronen', 'note' => null]);
save_slot($db, 3, 'lunch', 'note', 'Eltern kochen');
check('slot holds a note', load_plan($db)[3]['lunch'] === ['meal' => null, 'note' => 'Eltern kochen']);
save_slot($db, 3, 'lunch', 'meal', '');
check('empty text empties the slot', !isset(load_plan($db)[3]));
check('slot cannot hold a meal and a note',
    throws(fn() => $db->exec("INSERT INTO plan_slot VALUES (5, 'lunch', 1, 'x')"), PDOException::class));

save_slot($db, 4, 'dinner', 'meal', 'Birchermüesli');
$ids = array_column(list_meals($db), 'id', 'name');
check('meals sorted with umlauts like base letters', array_keys($ids) === ['Älplermagronen', 'Birchermüesli']);
check('rename to an existing name is rejected',
    throws(fn() => rename_meal($db, $ids['Birchermüesli'], 'ÄLPLERMAGRONEN'), UserError::class));
rename_meal($db, $ids['Birchermüesli'], 'BIRCHERMÜESLI');
check('rename may change the case of its own name', list_meals($db)[1]['name'] === 'BIRCHERMÜESLI');
check('meal used in the plan cannot be deleted',
    throws(fn() => delete_meal($db, $ids['Älplermagronen']), UserError::class));
find_or_create_meal($db, 'Unused');
delete_meal($db, array_column(list_meals($db), 'id', 'name')['Unused']);
check('unused meal can be deleted', $mealCount() === 2);

$now = 1_000_000;
for ($i = 0; $i < 4; $i++) {
    record_login_failure($db, '1.2.3.4', $now);
}
check('4 failures do not block', !login_blocked($db, '1.2.3.4', $now));
record_login_failure($db, '1.2.3.4', $now);
check('5 failures block', login_blocked($db, '1.2.3.4', $now + 60));
check('other IPs are not blocked', !login_blocked($db, '5.6.7.8', $now));
check('block ends after 15 minutes', !login_blocked($db, '1.2.3.4', $now + 15 * 60));

// --- Test data ---------------------------------------------------------------

$testConfig = "$tmp/config-testdata.php";
file_put_contents($testConfig, '<?php return ' . var_export([
    'password_hash' => password_hash('x', PASSWORD_DEFAULT),
    'cycle_start_date' => '2026-01-05',
    'data_dir' => "$tmp/testdata",
], true) . ';');
$run = fn(): int => proc_close(proc_open([PHP_BINARY, ROOT_DIR . '/tests/load_test_data.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['MENUPLAN_CONFIG' => $testConfig]));
check('test data loads', $run() === 0);
$testDb = open_db("$tmp/testdata");
check('test data fills 27 of 28 slots', (int)$testDb->query('SELECT COUNT(*) FROM plan_slot')->fetchColumn() === 27);
check('test data is not loaded twice', $run() === 1);

// --- HTTP ----------------------------------------------------------------------

$monday = monday_of(today());
$config = "$tmp/config.php";
file_put_contents($config, '<?php return ' . var_export([
    'password_hash' => password_hash('test-password', PASSWORD_DEFAULT),
    'cycle_start_date' => $monday->format('Y-m-d'),
    'data_dir' => "$tmp/http",
], true) . ';');
$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', ROOT_DIR . '/public', ROOT_DIR . '/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', "$tmp/server.log", 'w']], $pipes, null,
    ['MENUPLAN_CONFIG' => $config]);
register_shutdown_function(fn() => proc_terminate($server));
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
    usleep(100_000);
}

$cookies = [];

/** @return array{status: int, headers: array<string, list<string>>, body: string} */
function request(string $method, string $path, array $post = []): array
{
    global $cookies, $port;
    $headers = ['Cookie: ' . http_build_query($cookies, '', '; ')];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $context = stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => http_build_query($post),
        'ignore_errors' => true, 'follow_location' => 0,
    ]]);
    $body = file_get_contents("http://127.0.0.1:$port$path", false, $context);
    $raw = http_get_last_response_headers();
    $result = ['status' => (int)explode(' ', $raw[0])[1], 'headers' => [], 'body' => $body];
    foreach (array_slice($raw, 1) as $line) {
        [$name, $value] = explode(':', $line, 2);
        $result['headers'][strtolower($name)][] = trim($value);
    }
    foreach ($result['headers']['set-cookie'] ?? [] as $cookie) {
        [$name, $value] = explode('=', explode(';', $cookie)[0], 2);
        if ($value === '' || $value === 'deleted') {
            unset($cookies[$name]);
        } else {
            $cookies[$name] = $value;
        }
    }
    return $result;
}

function header_value(array $response, string $name): string
{
    return implode("\n", $response['headers'][$name] ?? []);
}

function csrf(string $path): string
{
    preg_match('/name="csrf" value="([0-9a-f]{64})"/', request('GET', $path)['body'], $match);
    return $match[1] ?? '';
}

function redirects_to(array $response, string $location): bool
{
    return $response['status'] === 303 && header_value($response, 'location') === $location;
}

check('dev server started', @fsockopen('127.0.0.1', $port) !== false);

check('pages require login', redirects_to(request('GET', '/'), '/login'));
check('unknown pages require login too', redirects_to(request('GET', '/nope'), '/login'));
$login = request('GET', '/login');
check('login page shown', $login['status'] === 200 && str_contains($login['body'], 'type="password"'));
check('CSP header without inline scripts or external scripts',
    str_contains(header_value($login, 'content-security-policy'), "script-src 'self';"));
check('nosniff, referrer and HSTS headers', header_value($login, 'x-content-type-options') === 'nosniff'
    && header_value($login, 'referrer-policy') === 'same-origin'
    && str_contains(header_value($login, 'strict-transport-security'), 'max-age='));
check('session cookie is HttpOnly, SameSite=Lax, 180 days', (bool)preg_match(
    '/^menuplan=.*Max-Age=15552000.*HttpOnly; SameSite=Lax/', header_value($login, 'set-cookie')));

check('POST without CSRF token is rejected', request('POST', '/login', ['password' => 'test-password'])['status'] === 400);
check('wrong password redirects to login',
    redirects_to(request('POST', '/login', ['csrf' => csrf('/login'), 'password' => 'wrong']), '/login'));
check('wrong password message shown', str_contains(request('GET', '/login')['body'], 'Falsches Passwort.'));
$sessionBefore = $cookies['menuplan'];
check('login with correct password',
    redirects_to(request('POST', '/login', ['csrf' => csrf('/login'), 'password' => 'test-password']), '/'));
check('session ID changes on login', $cookies['menuplan'] !== $sessionBefore);
check('login page redirects when logged in', redirects_to(request('GET', '/login'), '/'));

$day = request('GET', '/');
check('day view shows today in Woche 1', $day['status'] === 200
    && str_contains($day['body'], format_long_date(today())) && str_contains($day['body'], 'Heute · Woche 1'));
check('empty slots shown as not planned', substr_count($day['body'], 'Nicht geplant') === 2);
check('session cookie renewed on each request', str_contains(header_value($day, 'set-cookie'), 'Max-Age=15552000'));
check('previous/next day links', str_contains($day['body'], '/?date=' . add_days(today(), -1)->format('Y-m-d'))
    && str_contains($day['body'], '/?date=' . add_days(today(), 1)->format('Y-m-d')));
check('invalid date redirects to today', redirects_to(request('GET', '/?date=2026-02-30'), '/'));
check('other days can be shown', str_contains(request('GET', '/?date=2026-12-24')['body'], 'Donnerstag, 24. Dezember 2026'));

$cycleDay = (int)today()->format('N');
$token = csrf('/plan');
check('save a meal', redirects_to(request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => $cycleDay,
    'meal_time' => 'lunch', 'type' => 'meal', 'text' => ' Rösti <script>x</script> ']), "/plan#slot-$cycleDay-lunch"));
$day = request('GET', '/');
check('day view shows the meal, HTML-escaped', str_contains($day['body'], 'Rösti &lt;script&gt;x&lt;/script&gt;')
    && !str_contains($day['body'], '<script>x'));
request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => $cycleDay, 'meal_time' => 'dinner', 'type' => 'note',
    'text' => 'Eltern kochen']);
$week = request('GET', '/week');
check('week view shows the note and the meal', str_contains($week['body'], '<em>Eltern kochen</em>')
    && str_contains($week['body'], 'Rösti &lt;script&gt;'));
check('week view shows KW and Woche 1',
    str_contains($week['body'], 'KW ' . (int)today()->format('W') . ' · Woche 1'));
check('week view highlights today', substr_count($week['body'], 'class="today"') === 1);
check('next week is Woche 2',
    str_contains(request('GET', '/week?date=' . add_days($monday, 7)->format('Y-m-d'))['body'], '· Woche 2'));
check('week after next is Woche 1 again',
    str_contains(request('GET', '/week?date=' . add_days($monday, 14)->format('Y-m-d'))['body'], '· Woche 1'));

request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => 14, 'meal_time' => 'dinner', 'type' => 'note',
    'text' => 'Auswärts']);
$plan = request('GET', '/plan');
check('plan editor has 28 slot forms', substr_count($plan['body'], 'action="/plan/slot"') === 28);
check('plan editor suggests meals', str_contains($plan['body'], '<option value="Rösti &lt;script&gt;'));
check('plan editor shows saved message at the slot', (bool)preg_match(
    '/id="slot-14-dinner".*?value="Auswärts".*?Gespeichert\..*?<\/form>/s', $plan['body']));
check('saved message is shown only once', !str_contains(request('GET', '/plan')['body'], 'Gespeichert.'));
check('invalid slot is rejected', request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => 15,
    'meal_time' => 'lunch', 'type' => 'meal', 'text' => 'x'])['status'] === 400);
check('slot POST with wrong CSRF token is rejected', request('POST', '/plan/slot', ['csrf' => str_repeat('0', 64),
    'cycle_day' => 1, 'meal_time' => 'lunch', 'type' => 'meal', 'text' => 'x'])['status'] === 400);

request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => 9, 'meal_time' => 'lunch', 'type' => 'meal',
    'text' => 'Spaghetti']);
request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => 9, 'meal_time' => 'lunch', 'type' => 'meal',
    'text' => '']);
$meals = request('GET', '/meals');
preg_match_all('/name="id" value="(\d+)"/', $meals['body'], $match);
$ids = array_values(array_unique($match[1]));
check('meal list shows both meals', $meals['status'] === 200 && count($ids) === 2
    && str_contains($meals['body'], '1× im Plan'));
[$roestiId, $spaghettiId] = $ids;
request('POST', '/meals/rename', ['csrf' => $token, 'id' => $roestiId, 'name' => 'SPAGHETTI']);
check('rename to an existing name shows an error',
    str_contains(request('GET', '/meals')['body'], 'Es gibt bereits ein Gericht mit diesem Namen.'));
request('POST', '/meals/rename', ['csrf' => $token, 'id' => $roestiId, 'name' => 'Rösti']);
check('rename a meal', str_contains(request('GET', '/')['body'], '>Rösti<'));
request('POST', '/meals/delete', ['csrf' => $token, 'id' => $roestiId]);
check('used meal cannot be deleted', str_contains(request('GET', '/meals')['body'], 'kann nicht gelöscht werden'));
check('unused meal can be deleted', redirects_to(request('POST', '/meals/delete', ['csrf' => $token,
    'id' => $spaghettiId]), '/meals') && !str_contains(request('GET', '/meals')['body'], 'Spaghetti'));

check('unknown page returns 404', request('GET', '/nope')['status'] === 404);
check('wrong method returns 405', request('GET', '/plan/slot')['status'] === 405);
foreach (['/manifest.webmanifest', '/sw.js', '/offline.html', '/assets/app.css', '/assets/app.js',
             '/assets/icons/icon.svg', '/assets/icons/icon-192.png', '/assets/icons/icon-512.png',
             '/assets/icons/icon-maskable-512.png', '/assets/icons/apple-touch-icon.png'] as $file) {
    check("static file $file", request('GET', $file)['status'] === 200);
}

check('logout', redirects_to(request('POST', '/logout', ['csrf' => $token]), '/login'));
check('logged out after logout', redirects_to(request('GET', '/'), '/login'));

for ($i = 0; $i < 5; $i++) {
    request('POST', '/login', ['csrf' => csrf('/login'), 'password' => 'wrong']);
}
request('POST', '/login', ['csrf' => csrf('/login'), 'password' => 'test-password']);
check('throttled after 5 failures, even with the correct password',
    str_contains(request('GET', '/login')['body'], 'Zu viele Fehlversuche') && redirects_to(request('GET', '/'), '/login'));
check('no PHP errors in server log', !preg_match('/(Warning|Notice|Deprecated|Fatal)/', file_get_contents("$tmp/server.log")));

proc_terminate($server);
exec('rm -rf ' . escapeshellarg($tmp));
echo PHP_EOL . ($failures === 0 ? 'All tests passed.' : "$failures test(s) failed.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
