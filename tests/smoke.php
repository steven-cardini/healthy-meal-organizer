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

/** A valid meal: the first value of each tag unless given in $tags. */
function meal_input(string $name, array $tags = []): array
{
    $meal = ['name' => $name];
    foreach (MEAL_TAGS as $tag => $definition) {
        $meal[$tag] = $tags[$tag] ?? array_key_first($definition['values']);
    }
    return $meal;
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
check('slot before day 1 lunch is day 14 dinner', previous_slot(1, 'lunch') === [14, 'dinner']
    && previous_slot(5, 'dinner') === [5, 'lunch'] && previous_slot(5, 'lunch') === [4, 'dinner']);
check('slot after day 14 dinner is day 1 lunch', next_slot(14, 'dinner') === [1, 'lunch']
    && next_slot(5, 'lunch') === [5, 'dinner'] && next_slot(5, 'dinner') === [6, 'lunch']);
check('German long date', format_long_date(parse_date('2026-11-07')) === 'Samstag, 7. November 2026');
check('German short date', format_short_date(parse_date('2026-03-02')) === 'Mo 2.3.');
check('German date without weekday', format_date(parse_date('2026-11-07')) === '7. November 2026');
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
$aelplerId = create_meal($db, meal_input('Älplermagronen'));
check('new meal with all tags is created', $mealCount() === 1 && find_meal_id($db, 'Älplermagronen') === $aelplerId);
check('meal names are unique case-insensitively (umlauts)',
    throws(fn() => create_meal($db, meal_input('ÄLPLERMAGRONEN')), UserError::class) && $mealCount() === 1);
check('meal is found case-insensitively', find_meal_id($db, 'älplermagronen') === $aelplerId
    && find_meal_id($db, 'Rösti') === null);
$missingTag = meal_input('Rösti');
unset($missingTag['shelf_life']);
check('meal without a value for every tag is rejected', throws(fn() => create_meal($db, $missingTag), UserError::class));
check('meal with an unknown tag value is rejected',
    throws(fn() => create_meal($db, meal_input('Rösti', ['heaviness' => 'x'])), UserError::class));
check('meal without a name is rejected', throws(fn() => create_meal($db, meal_input('')), UserError::class));
check('database rejects unknown tag values', throws(fn() => $db->exec("INSERT INTO meal VALUES
    (99, 'x', 'x', 'beef_pork', 'light', 'quick', 'high_flyer', 'short', 'eat_fresh', 'main_component')"), PDOException::class));
preg_match_all('/\((\w+) IN\s*\(([^)]*)\)/', file_get_contents(ROOT_DIR . '/db/schema.sql'), $match, PREG_SET_ORDER);
$schemaTags = [];
foreach ($match as [, $column, $values]) {
    $schemaTags[$column] = preg_match_all("/'(\w+)'/", $values, $valueMatch) ? $valueMatch[1] : [];
}
unset($schemaTags['meal_time']);
check('MEAL_TAGS has the same tags and values as db/schema.sql',
    $schemaTags === array_map(fn(array $definition): array => array_keys($definition['values']), MEAL_TAGS));

save_slot($db, 2, 'lunch', $aelplerId, null);
check('slot holds the meal', load_plan($db)[2]['lunch']['meal'] === 'Älplermagronen');
save_slot($db, 3, 'lunch', null, 'Eltern kochen');
check('slot holds a note', load_plan($db)[3]['lunch']['note'] === 'Eltern kochen'
    && load_plan($db)[3]['lunch']['meal'] === null);
save_slot($db, 3, 'lunch', null, null);
check('saving neither meal nor note empties the slot', !isset(load_plan($db)[3]));
check('slot cannot hold a meal and a note',
    throws(fn() => $db->exec("INSERT INTO plan_slot VALUES (5, 'lunch', 1, 'x')"), PDOException::class));

$birchermuesliId = create_meal($db, meal_input('Birchermüesli'));
save_slot($db, 4, 'dinner', $birchermuesliId, null);
$ids = array_column(list_meals($db), 'id', 'name');
check('meals sorted with umlauts like base letters', array_keys($ids) === ['Älplermagronen', 'Birchermüesli']);
check('rename to an existing name is rejected',
    throws(fn() => update_meal($db, $birchermuesliId, meal_input('ÄLPLERMAGRONEN')), UserError::class));
update_meal($db, $birchermuesliId, meal_input('BIRCHERMÜESLI', ['protein_source' => 'dairy_cheese', 'leftovers' => 'eat_fresh']));
$meal = find_meal($db, $birchermuesliId);
check('update changes name case and tags', $meal['name'] === 'BIRCHERMÜESLI'
    && $meal['protein_source'] === 'dairy_cheese' && $meal['leftovers'] === 'eat_fresh' && $meal['uses'] === 1);
check('update of a deleted meal is rejected', throws(fn() => update_meal($db, 999, meal_input('x')), UserError::class));
check('unknown meal is not found', find_meal($db, 999) === null);
check('meal used in the plan cannot be deleted', throws(fn() => delete_meal($db, $aelplerId), UserError::class));
delete_meal($db, create_meal($db, meal_input('Unused')));
check('unused meal can be deleted', $mealCount() === 2);

$hintDb = open_db("$tmp/hints");
$hintMeal = fn(string $name, array $tags): int => create_meal($hintDb, meal_input($name, $tags));
$soup = $hintMeal('Suppe', ['leftovers' => 'reheats_well', 'prep_time' => 'slow', 'vegetable_level' => 'main_component']);
$pizza = $hintMeal('Pizza', ['leftovers' => 'eat_fresh', 'vegetable_level' => 'raw_veggies_needed']);
$schnitzel = $hintMeal('Schnitzel', ['leftovers' => 'reheats_well', 'vegetable_level' => 'side_dish']);
save_slot($hintDb, 14, 'dinner', $soup, null);
save_slot($hintDb, 1, 'lunch', $soup, null);
save_slot($hintDb, 2, 'lunch', $pizza, null);
save_slot($hintDb, 2, 'dinner', $pizza, null);
save_slot($hintDb, 3, 'lunch', $schnitzel, null);
$hintPlan = load_plan($hintDb);
check('reheatable meal in the next slot: cook double (wraps from day 14 to day 1)',
    slot_hints($hintPlan, 14, 'dinner') === ['cook_double' => 'Doppelte Menge kochen', 'slow' => 'Lange Zubereitung']);
check('same reheatable meal in the slot before: leftovers only', slot_hints($hintPlan, 1, 'lunch') === ['leftovers' => 'Reste']);
check('same meal that must be eaten fresh: no leftover hints', slot_hints($hintPlan, 2, 'lunch') === ['raw_veggies_needed' => 'Rohkost dazu']
    && slot_hints($hintPlan, 2, 'dinner') === ['raw_veggies_needed' => 'Rohkost dazu']);
check('vegetable side dish hint', slot_hints($hintPlan, 3, 'lunch') === ['side_dish' => 'Gemüsebeilage kochen']);
check('hints are shown below the meal', slot_html($hintPlan, 14, 'dinner') === 'Suppe<ul class="hints" aria-label="Hinweise">'
    . '<li class="hint-cook_double">Doppelte Menge kochen</li><li class="hint-slow">Lange Zubereitung</li></ul>');

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

$count = fn(PDO $db, string $table): int => (int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$testDb = open_db("$tmp/testdata");
check('test data loads into an empty database', load_test_data_if_empty($testDb));
check('test data fills 27 of 28 slots and 23 meals', $count($testDb, 'plan_slot') === 27 && $count($testDb, 'meal') === 23);
$testPlan = load_plan($testDb);
check('test data has a leftover pair (Minestrone)', isset(slot_hints($testPlan, 8, 'dinner')['cook_double'])
    && slot_hints($testPlan, 9, 'lunch') === ['leftovers' => 'Reste']);
check('test data is not loaded twice', !load_test_data_if_empty($testDb) && $count($testDb, 'plan_slot') === 27);
$noteDb = open_db("$tmp/notedata");
save_slot($noteDb, 1, 'lunch', null, 'Eltern kochen');
check('test data is not loaded if the plan has only a note', !load_test_data_if_empty($noteDb));

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
$otherDay = request('GET', '/?date=2026-12-24')['body'];
check('day view shows today in Woche 1', $day['status'] === 200
    && str_contains($day['body'], '<p class="relative-day">Heute</p>')
    && str_contains($day['body'], '<h1>' . weekday(today()) . '</h1>')
    && str_contains($day['body'], format_date(today()) . ' · Woche 1'));
check('empty slots shown as not planned, with a link to the plan editor',
    substr_count($day['body'], 'Nicht geplant') === 2 && str_contains($day['body'], 'href="/plan#slot-'));
check('today button and current menu item swap on other days',
    !str_contains($day['body'], 'class="secondary">Heute</a>') && str_contains($day['body'], 'aria-current="page">Heute')
    && str_contains($otherDay, 'class="secondary">Heute</a>') && !str_contains($otherDay, 'aria-current="page"'));
check('session cookie renewed on each request', str_contains(header_value($day, 'set-cookie'), 'Max-Age=15552000'));
check('previous/next day links', str_contains($day['body'], '/?date=' . add_days(today(), -1)->format('Y-m-d'))
    && str_contains($day['body'], '/?date=' . add_days(today(), 1)->format('Y-m-d')));
check('invalid date redirects to today', redirects_to(request('GET', '/?date=2026-02-30'), '/'));
check('other days can be shown', str_contains($otherDay, '<h1>Donnerstag</h1>')
    && str_contains($otherDay, '24. Dezember 2026 · Woche') && !str_contains($otherDay, 'relative-day'));
check('tomorrow and yesterday are labelled',
    str_contains(request('GET', '/?date=' . add_days(today(), 1)->format('Y-m-d'))['body'], '<p class="relative-day">Morgen</p>')
    && str_contains(request('GET', '/?date=' . add_days(today(), -1)->format('Y-m-d'))['body'], '<p class="relative-day">Gestern</p>'));

$cycleDay = (int)today()->format('N');
$token = csrf('/plan');
$newMealUrl = '/meals/new?'
    . http_build_query(['name' => 'Rösti <script>x</script>', 'cycle_day' => $cycleDay, 'meal_time' => 'lunch']);
check('unknown meal name in the plan leads to the new meal form', redirects_to(request('POST', '/plan/slot', [
    'csrf' => $token, 'cycle_day' => $cycleDay, 'meal_time' => 'lunch', 'type' => 'meal',
    'text' => ' Rösti <script>x</script> ']), $newMealUrl));
$form = request('GET', $newMealUrl);
check('new meal form has the name (HTML-escaped), the slot and all tag values', $form['status'] === 200
    && str_contains($form['body'], 'value="Rösti &lt;script&gt;x&lt;/script&gt;"')
    && str_contains($form['body'], 'name="cycle_day" value="' . $cycleDay . '"')
    && str_contains($form['body'], WEEKDAYS[$cycleDay] . ' (Woche 1), Mittagessen')
    && substr_count($form['body'], 'type="radio"') === array_sum(array_map(fn(array $d): int => count($d['values']), MEAL_TAGS)));
check('new meal form: no value preselected', !str_contains($form['body'], 'checked'));
check('new meal form with an invalid slot is rejected',
    request('GET', '/meals/new?cycle_day=15&meal_time=lunch')['status'] === 400);
check('slot stays empty until the meal is saved', substr_count(request('GET', '/')['body'], 'Nicht geplant') === 2);
$roesti = meal_input('Rösti <script>x</script>', ['prep_time' => 'slow', 'vegetable_level' => 'side_dish']);
$incomplete = $roesti;
unset($incomplete['heaviness']);
$response = request('POST', '/meals/new', ['csrf' => $token, 'cycle_day' => $cycleDay, 'meal_time' => 'lunch'] + $incomplete);
check('missing tag: form shown again with the error and the entered values', $response['status'] === 422
    && str_contains($response['body'], 'Wähle bei jeder Eigenschaft einen Wert.')
    && str_contains($response['body'], 'value="Rösti &lt;script&gt;x&lt;/script&gt;"')
    && preg_match('/value="slow" required\s+checked>/', $response['body'])
    && str_contains($response['body'], 'name="meal_time" value="lunch"'));
check('saving the new meal fills the slot', redirects_to(request('POST', '/meals/new',
    ['csrf' => $token, 'cycle_day' => $cycleDay, 'meal_time' => 'lunch'] + $roesti), "/plan#slot-$cycleDay-lunch"));
$day = request('GET', '/');
check('day view shows the meal (HTML-escaped) with hints', str_contains($day['body'],
    'Rösti &lt;script&gt;x&lt;/script&gt;<ul class="hints" aria-label="Hinweise"><li class="hint-slow">Lange Zubereitung</li>'
    . '<li class="hint-side_dish">Gemüsebeilage kochen</li></ul>')
    && !str_contains($day['body'], '<script>x'));
request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => $cycleDay, 'meal_time' => 'dinner', 'type' => 'note',
    'text' => 'Eltern kochen']);
$week = request('GET', '/week');
check('week view shows the note and the meal', str_contains($week['body'], '<em class="note">Eltern kochen</em>')
    && str_contains($week['body'], 'Rösti &lt;script&gt;'));
check('week view shows KW and Woche 1',
    str_contains($week['body'], 'KW ' . (int)today()->format('W') . ' · Woche 1'));
check('week view highlights today', substr_count($week['body'], 'class="today"') === 1);
check('this week button only in other weeks', !str_contains($week['body'], 'Diese Woche</a>')
    && str_contains(request('GET', '/week?date=' . add_days($monday, 7)->format('Y-m-d'))['body'], 'Diese Woche</a>'));
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

$response = request('POST', '/meals/new', ['csrf' => $token] + meal_input('Spaghetti'));
check('new meal without a slot goes to the meal list',
    $response['status'] === 303 && preg_match('~^/meals#meal-\d+$~', header_value($response, 'location')));
check('existing meal name (any case) is saved directly', redirects_to(request('POST', '/plan/slot', ['csrf' => $token,
    'cycle_day' => 9, 'meal_time' => 'lunch', 'type' => 'meal', 'text' => 'SPAGHETTI']), '/plan#slot-9-lunch')
    && preg_match('/id="slot-9-lunch".*?value="Spaghetti"/s', request('GET', '/plan')['body']));
request('POST', '/plan/slot', ['csrf' => $token, 'cycle_day' => 9, 'meal_time' => 'lunch', 'type' => 'meal', 'text' => '']);
$meals = request('GET', '/meals');
preg_match_all('~href="/meals/edit\?id=(\d+)"~', $meals['body'], $match);
check('meal list links both meals and shows their uses', $meals['status'] === 200 && count($match[1]) === 2
    && str_contains($meals['body'], '1× im Plan') && str_contains($meals['body'], 'Nicht im Plan')
    && str_contains($meals['body'], 'href="/meals/new"'));
[$roestiId, $spaghettiId] = $match[1];
$edit = request('GET', "/meals/edit?id=$roestiId");
check('edit form shows the saved tags, no delete button for a used meal', $edit['status'] === 200
    && preg_match('/value="slow" required\s+checked>/', $edit['body']) && !str_contains($edit['body'], 'action="/meals/delete"'));
check('edit form of an unused meal has a delete button',
    str_contains(request('GET', "/meals/edit?id=$spaghettiId")['body'], 'action="/meals/delete"'));
check('unknown meal returns 404', request('GET', '/meals/edit?id=999')['status'] === 404
    && request('GET', '/meals/edit?id=x')['status'] === 404);
$response = request('POST', '/meals/edit', ['csrf' => $token, 'id' => $roestiId] + meal_input('SPAGHETTI'));
check('rename to an existing name: error in the form with the entered name', $response['status'] === 422
    && str_contains($response['body'], 'Es gibt bereits ein Gericht mit diesem Namen.')
    && str_contains($response['body'], 'value="SPAGHETTI"'));
check('save name and tags', redirects_to(request('POST', '/meals/edit', ['csrf' => $token, 'id' => $roestiId]
    + meal_input('Rösti', ['vegetable_level' => 'raw_veggies_needed'])), "/meals#meal-$roestiId"));
check('saved message shown at the meal',
    (bool)preg_match("/id=\"meal-$roestiId\".*?Gespeichert\..*?<\/li>/s", request('GET', '/meals')['body']));
check('day view shows the new name and hints', str_contains(request('GET', '/')['body'],
    '>Rösti<ul class="hints" aria-label="Hinweise"><li class="hint-raw_veggies_needed">Rohkost dazu</li></ul>'));
check('used meal cannot be deleted',
    redirects_to(request('POST', '/meals/delete', ['csrf' => $token, 'id' => $roestiId]), "/meals/edit?id=$roestiId")
    && str_contains(request('GET', "/meals/edit?id=$roestiId")['body'], 'kann nicht gelöscht werden'));
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
$writeConfig = function (bool $loadTestData) use ($config, $monday, $tmp): void {
    file_put_contents($config, '<?php return ' . var_export([
        'password_hash' => password_hash('test-password', PASSWORD_DEFAULT),
        'cycle_start_date' => $monday->format('Y-m-d'),
        'data_dir' => "$tmp/http-testdata",
        'load_test_data' => $loadTestData,
    ], true) . ';');
    sleep(3); // opcache.revalidate_freq: the server sees a changed config.php only after 2 s
};
$writeConfig(false);
request('GET', '/login');
check('no test data without the config setting', is_file("$tmp/http-testdata/db.sqlite")
    && $count(open_db("$tmp/http-testdata"), 'plan_slot') === 0);
$writeConfig(true);
request('GET', '/login');
check('config setting loads test data on the next request', $count(open_db("$tmp/http-testdata"), 'plan_slot') === 27);

check('no PHP errors in server log', !preg_match('/(Warning|Notice|Deprecated|Fatal)/', file_get_contents("$tmp/server.log")));

proc_terminate($server);
exec('rm -rf ' . escapeshellarg($tmp));
echo PHP_EOL . ($failures === 0 ? 'All tests passed.' : "$failures test(s) failed.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
