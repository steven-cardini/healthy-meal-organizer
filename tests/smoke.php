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

$start = parse_date('2026-01-17');
check('week starts on Saturday', week_start($start) == $start && week_start(add_days($start, 1)) == $start
    && week_start(add_days($start, 6)) == $start && week_start(add_days($start, 7)) == add_days($start, 7)
    && week_start(add_days($start, -1)) == add_days($start, -7));
check('days of a week: 1 = Saturday, 7 = Friday', week_day($start) === 1 && week_day(add_days($start, 6)) === 7
    && week_day_name(1) === 'Samstag' && week_day_name(3) === 'Montag' && week_day_name(7) === 'Freitag');
$consecutive = true;
for ($date = parse_date('2025-01-01'); $date < parse_date('2028-12-31'); $date = add_days($date, 1)) {
    $consecutive = $consecutive && week_day(add_days($date, 1)) === week_day($date) % 7 + 1
        && week_start($date)->format('N') === '6' && weeks_between(week_start($date), week_start(add_days($date, 7))) === 1;
}
check('week days are consecutive and weeks start on Saturday 2025–2028 (incl. DST changes)', $consecutive);
check('weeks between two week starts', weeks_between($start, add_days($start, 14)) === 2
    && weeks_between($start, add_days($start, -7)) === -1);
check('week dates within and across months', format_week_dates($start) === '17.–23.1.'
    && format_week_dates(parse_date('2026-11-28')) === '28.11.–4.12.');
check('invalid dates are rejected', parse_date('2026-02-30') === null && parse_date('2026-1-5') === null
    && parse_date('x') === null);
check('slot before lunch is dinner of the day before', previous_slot($start, 'lunch') == [add_days($start, -1), 'dinner']
    && previous_slot($start, 'dinner') == [$start, 'lunch']);
check('slot after dinner is lunch of the next day', next_slot($start, 'dinner') == [add_days($start, 1), 'lunch']
    && next_slot($start, 'lunch') == [$start, 'dinner']);
check('ingredients keep until Monday (short), Thursday (medium), all week (long)',
    fresh_until('short', parse_date('2026-11-07')) == parse_date('2026-11-09')
    && fresh_until('medium', parse_date('2026-11-07')) == parse_date('2026-11-12')
    && fresh_until('long', parse_date('2026-11-07')) === null);
$shortMeal = ['meal' => 'Fisch', 'shelf_life' => 'short'];
$mediumMeal = ['meal' => 'Poulet', 'shelf_life' => 'medium'];
check('freshness warning when a meal moves later than its ingredients keep',
    move_warning($shortMeal, parse_date('2026-11-08'), parse_date('2026-11-10')) === 'Die Zutaten für Fisch halten evtl. nicht bis Dienstag.'
    && move_warning($shortMeal, parse_date('2026-11-07'), parse_date('2026-11-09')) === null
    && move_warning($shortMeal, parse_date('2026-11-11'), parse_date('2026-11-10')) === null
    && move_warning($mediumMeal, parse_date('2026-11-09'), parse_date('2026-11-12')) === null
    && move_warning($mediumMeal, parse_date('2026-11-09'), parse_date('2026-11-13')) !== null
    && move_warning(['meal' => 'Reis', 'shelf_life' => 'long'], parse_date('2026-11-07'), parse_date('2026-11-13')) === null);
check('freshness warning when a short or medium meal is skipped or replaced',
    unused_warning($shortMeal) === 'Die Zutaten für Fisch bleiben übrig und halten nur wenige Tage.'
    && unused_warning($mediumMeal) !== null
    && unused_warning(['meal' => 'Reis', 'shelf_life' => 'long']) === null
    && unused_warning(['meal' => null, 'note' => 'x']) === null);
check('German long date', format_long_date(parse_date('2026-11-07')) === 'Samstag, 7. November 2026');
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
    (99, 'x', 'x', 'beef_pork', 'light', 'quick', 'high_flyer', 'short', 'eat_fresh', 'main_component', NULL)"), PDOException::class));
preg_match_all('/\((\w+) IN\s*\(([^)]*)\)/', file_get_contents(ROOT_DIR . '/db/schema.sql'), $match, PREG_SET_ORDER);
$schemaTags = [];
foreach ($match as [, $column, $values]) {
    $schemaTags[$column] = preg_match_all("/'(\w+)'/", $values, $valueMatch) ? $valueMatch[1] : [];
}
$schemaCategories = $schemaTags['category'];
unset($schemaTags['blueprint'], $schemaTags['meal_time'], $schemaTags['planned'], $schemaTags['swap'],
    $schemaTags['partner_meal_time'], $schemaTags['moved'], $schemaTags['category']);
check('MEAL_TAGS has the same tags and values as db/schema.sql',
    $schemaTags === array_map(fn(array $definition): array => array_keys($definition['values']), MEAL_TAGS));
check('INGREDIENT_CATEGORIES has the same values as db/schema.sql', $schemaCategories === array_keys(INGREDIENT_CATEGORIES));

save_blueprint_slot($db, 1, 2, 'lunch', $aelplerId, null);
check('slot holds the meal', load_blueprints($db)[1][2]['lunch']['meal'] === 'Älplermagronen');
save_blueprint_slot($db, 2, 3, 'lunch', null, 'Eltern kochen');
check('slot holds a note', load_blueprints($db)[2][3]['lunch']['note'] === 'Eltern kochen'
    && load_blueprints($db)[2][3]['lunch']['meal'] === null);
save_blueprint_slot($db, 2, 3, 'lunch', null, null);
check('saving neither meal nor note empties the slot', !isset(load_blueprints($db)[2]));
check('slot cannot hold a meal and a note',
    throws(fn() => $db->exec("INSERT INTO blueprint_slot VALUES (1, 5, 'lunch', 1, 'x')"), PDOException::class));
check('database rejects a third week blueprint and an eighth day',
    throws(fn() => $db->exec("INSERT INTO blueprint_slot VALUES (3, 1, 'lunch', NULL, 'x')"), PDOException::class)
    && throws(fn() => $db->exec("INSERT INTO blueprint_slot VALUES (1, 8, 'lunch', NULL, 'x')"), PDOException::class));

$birchermuesliId = create_meal($db, meal_input('Birchermüesli'));
save_blueprint_slot($db, 1, 4, 'dinner', $birchermuesliId, null);
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
check('meal used in a week blueprint cannot be deleted', throws(fn() => delete_meal($db, $aelplerId, today()), UserError::class));
delete_meal($db, create_meal($db, meal_input('Unused')), today());
check('unused meal can be deleted', $mealCount() === 2);

// Ingredients and recipe link (FR-27 to FR-29).
$ingredientCount = fn(): int => (int)$db->query('SELECT COUNT(*) FROM ingredient')->fetchColumn();
$soupId = create_meal($db, meal_input('Suppe') + ['recipe_url' => ' https://example.com/suppe ', 'ingredients' => [
    ['name' => ' Karotten ', 'category' => 'fruit_vegetables'], ['name' => '', 'category' => ''],
    ['name' => 'Lauch', 'category' => 'fruit_vegetables'], ['name' => 'KAROTTEN', 'category' => 'other']]]);
$soup = find_meal($db, $soupId);
check('meal with recipe link and ingredients: trimmed, empty and repeated names dropped, order kept',
    $soup['recipe_url'] === 'https://example.com/suppe' && array_column($soup['ingredients'], 'name') === ['Karotten', 'Lauch']
    && array_column($soup['ingredients'], 'category') === ['fruit_vegetables', 'fruit_vegetables']);
check('new ingredient name without a category is rejected, nothing saved', throws(fn() => create_meal($db, meal_input('Eintopf')
    + ['ingredients' => [['name' => 'Speck', 'category' => '']]]), UserError::class) && find_meal_id($db, 'Eintopf') === null);
check('unknown category is rejected', throws(fn() => create_meal($db, meal_input('Eintopf')
    + ['ingredients' => [['name' => 'Speck', 'category' => 'x']]]), UserError::class));
$stewId = create_meal($db, meal_input('Eintopf') + ['ingredients' => [['name' => 'karotten', 'category' => ''],
    ['name' => 'Speck', 'category' => 'meat_fish']]]);
check('known ingredient name (any case) without a category keeps its category, one row per name',
    load_ingredients($db, $stewId)[$stewId][0] === ['name' => 'karotten', 'name_key' => 'karotten', 'category' => 'fruit_vegetables']
    && $ingredientCount() === 3);
update_meal($db, $stewId, meal_input('Eintopf') + ['ingredients' => [['name' => 'Karotten', 'category' => 'other']]]);
check('a changed category applies to all meals; unused ingredient names are deleted',
    find_meal($db, $soupId)['ingredients'][0] === ['name' => 'Karotten', 'name_key' => 'karotten', 'category' => 'other']
    && $ingredientCount() === 2);
check('meal list counts ingredients', array_column(list_meals($db), 'ingredients', 'name') === ['Älplermagronen' => 0,
    'BIRCHERMÜESLI' => 0, 'Eintopf' => 1, 'Suppe' => 2]);
check('recipe link: http(s) only, no spaces, at most 2000 characters', clean_url(' ') === null
    && clean_url('HTTPS://example.com/rösti?a=1#b') === 'HTTPS://example.com/rösti?a=1#b'
    && throws(fn() => clean_url('javascript:alert(1)'), UserError::class) && throws(fn() => clean_url('ftp://x'), UserError::class)
    && throws(fn() => clean_url('https://'), UserError::class) && throws(fn() => clean_url('https://a b'), UserError::class)
    && throws(fn() => clean_url('https://x/' . str_repeat('a', 2000)), UserError::class)
    && throws(fn() => clean_url(['https://x']), UserError::class));
check('database rejects a recipe link without http(s)',
    throws(fn() => $db->exec("UPDATE meal SET recipe_url = 'javascript:x' WHERE id = $soupId"), PDOException::class));
delete_meal($db, $stewId, today());
delete_meal($db, $soupId, today());
check('deleting meals deletes their ingredients', $ingredientCount() === 0
    && (int)$db->query('SELECT COUNT(*) FROM meal_ingredient')->fetchColumn() === 0);

$groceries = grocery_list([
    '2026-01-17' => ['lunch' => ['meal_id' => 1, 'meal' => 'Suppe'], 'dinner' => ['meal_id' => 1, 'meal' => 'Suppe']],
    '2026-01-18' => ['lunch' => ['meal_id' => null, 'meal' => null], 'dinner' => ['meal_id' => 2, 'meal' => 'Eintopf']],
    '2026-01-19' => ['lunch' => ['meal_id' => 3, 'meal' => 'Raclette'], 'dinner' => ['meal_id' => 3, 'meal' => 'Raclette']],
], [
    1 => [['name' => 'Lauch', 'name_key' => 'lauch', 'category' => 'fruit_vegetables'],
        ['name' => 'Brot', 'name_key' => 'brot', 'category' => 'bread_pastries'],
        ['name' => 'Äpfel', 'name_key' => 'äpfel', 'category' => 'fruit_vegetables']],
    2 => [['name' => 'Lauch', 'name_key' => 'lauch', 'category' => 'fruit_vegetables'],
        ['name' => 'Speck', 'name_key' => 'speck', 'category' => 'meat_fish']],
    9 => [['name' => 'Reis', 'name_key' => 'reis', 'category' => 'pasta_condiments_canned']],
]);
check('grocery list: categories in their order, names sorted, each once with its meals and slot count',
    array_keys($groceries['categories']) === ['fruit_vegetables', 'meat_fish', 'bread_pastries']
    && array_column($groceries['categories']['fruit_vegetables'], 'name') === ['Äpfel', 'Lauch']
    && grocery_meals_text($groceries['categories']['fruit_vegetables'][1]['meals']) === 'Suppe 2×, Eintopf'
    && grocery_meals_text($groceries['categories']['meat_fish'][0]['meals']) === 'Eintopf');
check('grocery list: meals without ingredients', grocery_meals_text($groceries['without']) === 'Raclette 2×');

// Woche 1 from Saturday 2026-01-17 (first use), so the week before is Woche 2.
putenv('MENUPLAN_NOW=2026-01-17 12:00');
$d = fn(string $date): DateTimeImmutable => parse_date($date);
$hintDb = open_db("$tmp/hints");
plan_current_week($hintDb);
$hintMeal = fn(string $name, array $tags): int => create_meal($hintDb, meal_input($name, ['shelf_life' => 'long'] + $tags));
$soup = $hintMeal('Suppe', ['leftovers' => 'reheats_well', 'prep_time' => 'slow', 'vegetable_level' => 'main_component']);
$pizza = $hintMeal('Pizza', ['leftovers' => 'eat_fresh', 'vegetable_level' => 'raw_veggies_needed']);
$schnitzel = $hintMeal('Schnitzel', ['leftovers' => 'reheats_well', 'vegetable_level' => 'side_dish']);
save_blueprint_slot($hintDb, 2, 7, 'dinner', $soup, null);       // Fri 2026-01-16
save_blueprint_slot($hintDb, 1, 1, 'lunch', $soup, null);        // Sat 2026-01-17
save_blueprint_slot($hintDb, 1, 2, 'lunch', $pizza, null);       // Sun 2026-01-18
save_blueprint_slot($hintDb, 1, 2, 'dinner', $pizza, null);
save_blueprint_slot($hintDb, 1, 3, 'lunch', $schnitzel, null);   // Mon 2026-01-19
save_blueprint_slot($hintDb, 1, 3, 'dinner', $schnitzel, null);
$hintEntries = load_entries($hintDb, $d('2026-01-15'), $d('2026-01-20'));
check('reheatable meal in the next slot: cook double (also from Friday of Woche 2 to Saturday of Woche 1)',
    slot_hints($hintEntries, $d('2026-01-16'), 'dinner') === ['cook_double' => 'Doppelte Menge für morgen Mittag', 'slow' => 'Lange Zubereitung']);
check('same reheatable meal in the slot before: leftovers only',
    slot_hints($hintEntries, $d('2026-01-17'), 'lunch') === ['leftovers' => 'Reste vom Vorabend']);
check('same meal that must be eaten fresh: no leftover hints', slot_hints($hintEntries, $d('2026-01-18'), 'lunch') === ['raw_veggies_needed' => 'Rohkost dazu']
    && slot_hints($hintEntries, $d('2026-01-18'), 'dinner') === ['raw_veggies_needed' => 'Rohkost dazu']);
check('lunch and dinner the same: cook double for the evening, vegetable side dish hint',
    slot_hints($hintEntries, $d('2026-01-19'), 'lunch') === ['cook_double' => 'Doppelte Menge für den Abend', 'side_dish' => 'Gemüsebeilage kochen']
    && slot_hints($hintEntries, $d('2026-01-19'), 'dinner') === ['leftovers' => 'Reste vom Mittagessen']);
check('no hints for a neighbour outside the loaded dates', slot_hints(load_entries($hintDb, $d('2026-01-16'), $d('2026-01-16')),
    $d('2026-01-16'), 'dinner') === ['slow' => 'Lange Zubereitung']);
check('week view: hints below the meal, no tags', slot_html($hintEntries, $d('2026-01-16'), 'dinner') === '<p class="meal-name">Suppe</p>'
    . '<ul class="hints" aria-label="Hinweise"><li class="hint-cook_double">Doppelte Menge für morgen Mittag</li>'
    . '<li class="hint-slow">Lange Zubereitung</li></ul>');
$dayHtml = slot_html($hintEntries, $d('2026-01-16'), 'dinner', true);
check('day view: protein avatar, summary tags with label and icon class, hints', str_contains($dayHtml,
    '<span class="avatar tag-protein_source-meat" aria-hidden="true"></span>')
    && str_contains($dayHtml, '<span role="listitem" class="tag-prep_time-slow"><span class="visually-hidden">'
        . 'Zubereitungszeit: </span>Lang (&gt; 40 Min.)</span>')
    && substr_count($dayHtml, '<span role="listitem" class="tag-') === count(SUMMARY_TAGS)
    && str_contains($dayHtml, '<li class="hint-slow">Lange Zubereitung</li>'));
$fish = create_meal($hintDb, meal_input('Fisch', ['shelf_life' => 'short', 'leftovers' => 'eat_fresh']));
$chicken = create_meal($hintDb, meal_input('Poulet', ['shelf_life' => 'medium', 'leftovers' => 'eat_fresh']));
save_blueprint_slot($hintDb, 1, 3, 'lunch', $fish, null);        // Mon 2026-01-19: keeps until Monday
save_blueprint_slot($hintDb, 1, 4, 'lunch', $fish, null);        // Tue 2026-01-20
save_blueprint_slot($hintDb, 1, 6, 'dinner', $chicken, null);    // Thu 2026-01-22: keeps until Thursday
save_blueprint_slot($hintDb, 1, 7, 'dinner', $chicken, null);    // Fri 2026-01-23
save_date_slot($hintDb, true, $d('2026-01-21'), 'lunch', $fish, null);
$freshEntries = load_entries($hintDb, $d('2026-01-18'), $d('2026-01-24'));
$notFresh = fn(string $shelfLife): array => ["not_fresh_$shelfLife" => 'Zutaten evtl. nicht mehr frisch'];
check('meal planned after its ingredients keep: hint, from the week blueprint or a swap',
    slot_hints($freshEntries, $d('2026-01-19'), 'lunch') === [] && slot_hints($freshEntries, $d('2026-01-20'), 'lunch') === $notFresh('short')
    && slot_hints($freshEntries, $d('2026-01-21'), 'lunch') === $notFresh('short')
    && slot_hints($freshEntries, $d('2026-01-22'), 'dinner') === [] && slot_hints($freshEntries, $d('2026-01-23'), 'dinner') === $notFresh('medium')
    && str_contains(slot_html($freshEntries, $d('2026-01-20'), 'lunch'), '<li class="hint-not_fresh_short">Zutaten evtl. nicht mehr frisch</li>'));

// Swaps: week Saturday 2026-01-17 to Friday 2026-01-23 (first use, Woche 1), now is its Saturday noon.
putenv('MENUPLAN_NOW=2026-01-17 12:00');
$swapDb = open_db("$tmp/swaps");
plan_current_week($swapDb);
$swapMeal = fn(string $name, array $tags = []): int => create_meal($swapDb, meal_input($name, $tags));
$a = $swapMeal('A', ['leftovers' => 'reheats_well']);
$b = $swapMeal('B');
$c = $swapMeal('C');
$e = $swapMeal('E', ['leftovers' => 'reheats_well']);
save_blueprint_slot($swapDb, 1, 3, 'lunch', $a, null);      // Mon 2026-01-19
save_blueprint_slot($swapDb, 1, 3, 'dinner', null, 'Eltern kochen');
save_blueprint_slot($swapDb, 1, 5, 'lunch', $b, null);      // Wed 2026-01-21
save_blueprint_slot($swapDb, 1, 6, 'lunch', $c, null);      // Thu 2026-01-22
$entries = fn(): array => load_entries($swapDb, $d('2026-01-17'), $d('2026-01-23'));
$meals = fn(): array => array_map(fn(array $slots): array => array_map(
    fn(array $entry): string => ($entry['changed'] ? '*' : '') . ($entry['meal'] ?? $entry['note'] ?? '-'), $slots), $entries());
$mon = $d('2026-01-19');
$wed = $d('2026-01-21');
$thu = $d('2026-01-22');
check('without swaps, entries are the week blueprint', $meals()['2026-01-19'] === ['lunch' => 'A', 'dinner' => 'Eltern kochen']
    && $meals()['2026-01-18'] === ['lunch' => '-', 'dinner' => '-']);
exchange_meals($swapDb, true, [$mon, 'lunch', $a], [$wed, 'lunch', $b]);
check('exchange swaps the two meals and marks both', $meals()['2026-01-19']['lunch'] === '*B'
    && $meals()['2026-01-21']['lunch'] === '*A');
check('swaps apply to their date only, not to the week after next',
    load_entries($swapDb, $d('2026-02-02'), $d('2026-02-02'))['2026-02-02']['lunch']['meal'] === 'A');
reset_date_slot($swapDb, true, $mon, 'lunch');
check('reset restores both slots of an exchange', $meals()['2026-01-19']['lunch'] === 'A' && $meals()['2026-01-21']['lunch'] === 'B');
exchange_meals($swapDb, true, [$mon, 'lunch', $a], [$wed, 'lunch', $b]);
save_date_slot($swapDb, true, $wed, 'lunch', null, null);
check('skip without a note', $meals()['2026-01-21']['lunch'] === '*-'
    && str_contains(slot_html($entries(), $wed, 'lunch'), '<em class="skipped">Ausgelassen</em>'));
reset_date_slot($swapDb, true, $mon, 'lunch');
check('reset after the partner changed again: also restores the partner', $meals()['2026-01-19']['lunch'] === 'A'
    && $meals()['2026-01-21']['lunch'] === 'B');
exchange_meals($swapDb, true, [$mon, 'lunch', $a], [$wed, 'lunch', $b]);
save_date_slot($swapDb, true, $wed, 'lunch', null, 'Besuch');
reset_date_slot($swapDb, true, $wed, 'lunch');
check('reset of a changed partner also restores the slot still holding the exchange',
    $meals()['2026-01-19']['lunch'] === 'A' && $meals()['2026-01-21']['lunch'] === 'B');
exchange_meals($swapDb, true, [$mon, 'lunch', $a], [$wed, 'lunch', $b]);
exchange_meals($swapDb, true, [$mon, 'lunch', $b], [$thu, 'lunch', $c]);
check('chained exchanges', $meals()['2026-01-19']['lunch'] === '*C' && $meals()['2026-01-21']['lunch'] === '*A'
    && $meals()['2026-01-22']['lunch'] === '*B');
reset_date_slot($swapDb, true, $thu, 'lunch');
check('reset of a chain restores all linked slots', $meals()['2026-01-19']['lunch'] === 'A'
    && $meals()['2026-01-21']['lunch'] === 'B' && $meals()['2026-01-22']['lunch'] === 'C');
exchange_meals($swapDb, true, [$mon, 'lunch', $a], [$wed, 'lunch', $b]);
exchange_meals($swapDb, true, [$wed, 'lunch', $a], [$thu, 'lunch', $c]);
check('reset warning names all other linked slots in time order', reset_warning(reset_slots($swapDb, true, $mon, 'lunch'), $mon, 'lunch')
    === 'Setzt auch Mittwoch Mittag und Donnerstag Mittag zurück.');
reset_date_slot($swapDb, true, $mon, 'lunch');
check('reset of the first slot of a chain restores all linked slots', $meals()['2026-01-19']['lunch'] === 'A'
    && $meals()['2026-01-21']['lunch'] === 'B' && $meals()['2026-01-22']['lunch'] === 'C');
check('no reset warning for a slot without linked slots', reset_slots($swapDb, true, $mon, 'lunch') === []
    && reset_warning([[$mon, 'lunch']], $mon, 'lunch') === null);
move_meal($swapDb, true, [$mon, 'dinner'], [$thu, 'lunch', $c]);
check('move: the meal replaces the note, the other slot is empty', $meals()['2026-01-19']['dinner'] === '*C'
    && $meals()['2026-01-22']['lunch'] === '*-');
check('move: marked as moved, the other slot names where to',
    slot_html($entries(), $mon, 'dinner') === '<p class="meal-name">C <small class="swapped">verschoben</small></p>'
    && slot_html($entries(), $thu, 'lunch') === '<em class="moved">Verschoben auf Montag Abend</em>'
    && reset_warning(reset_slots($swapDb, true, $thu, 'lunch'), $thu, 'lunch') === 'Setzt auch Montag Abend zurück.');
save_date_slot($swapDb, true, $thu, 'lunch', $b, null);
check('a new meal in the emptied slot keeps the moved meal marked',
    str_contains(slot_html($entries(), $mon, 'dinner'), 'verschoben')
    && str_contains(slot_html($entries(), $thu, 'lunch'), 'getauscht')
    && reset_warning(reset_slots($swapDb, true, $mon, 'dinner'), $mon, 'dinner') === 'Setzt auch Donnerstag Mittag zurück.');
reset_date_slot($swapDb, true, $mon, 'dinner');
check('reset of a moved meal also restores the emptied slot, even after a new meal there',
    $meals()['2026-01-19']['dinner'] === 'Eltern kochen' && $meals()['2026-01-22']['lunch'] === 'C');
move_meal($swapDb, true, [$mon, 'dinner'], [$thu, 'lunch', $c]);
save_date_slot($swapDb, true, $mon, 'dinner', $a, null);
check('emptied slot without a destination once the moved meal is replaced',
    slot_html($entries(), $thu, 'lunch') === '<em class="moved">Verschoben</em>');
reset_date_slot($swapDb, true, $thu, 'lunch');
check('reset of the emptied slot restores both', $meals()['2026-01-19']['dinner'] === 'Eltern kochen'
    && $meals()['2026-01-22']['lunch'] === 'C');
putenv('MENUPLAN_NOW=2026-01-21 13:59');
check('lunch is not past before 14:00', !is_past($wed, 'lunch') && is_swappable($wed, 'lunch', false));
putenv('MENUPLAN_NOW=2026-01-21 14:00');
check('lunch is past from 14:00, dinner only from the next day', is_past($wed, 'lunch') && !is_past($wed, 'dinner')
    && is_past($d('2026-01-20'), 'dinner') && !is_past($thu, 'lunch'));
check('past slots and slots after Friday cannot be swapped', !is_swappable($wed, 'lunch', false) && is_swappable($wed, 'dinner', false)
    && is_swappable($d('2026-01-23'), 'dinner', false) && !is_swappable($d('2026-01-24'), 'lunch', false)
    && is_swappable($d('2026-01-24'), 'lunch', true) && !is_swappable($d('2026-01-31'), 'lunch', true));
putenv('MENUPLAN_NOW=2026-01-21 12:00');
exchange_meals($swapDb, true, [$wed, 'lunch', $b], [$thu, 'lunch', $c]);
putenv('MENUPLAN_NOW=2026-01-21 14:00');
reset_date_slot($swapDb, true, $thu, 'lunch');
check('reset leaves a past partner slot unchanged', $meals()['2026-01-21']['lunch'] === '*C' && $meals()['2026-01-22']['lunch'] === 'C');
reset_date_slot($swapDb, true, $wed, 'lunch');
putenv('MENUPLAN_NOW=2026-01-17 12:00');
save_date_slot($swapDb, true, $mon, 'dinner', $a, null);
$swapEntries = $entries();
check('skip with a note, meal from the list, hints follow the swapped meals',
    slot_hints($swapEntries, $mon, 'lunch') === ['cook_double' => 'Doppelte Menge für den Abend']
    && slot_hints($swapEntries, $mon, 'dinner') === ['leftovers' => 'Reste vom Mittagessen']
    && slot_html($swapEntries, $mon, 'dinner') === '<p class="meal-name">A <small class="swapped">getauscht</small></p>'
        . '<ul class="hints" aria-label="Hinweise"><li class="hint-leftovers">Reste vom Mittagessen</li></ul>');
save_date_slot($swapDb, true, $mon, 'dinner', null, 'Grosseltern');
check('skipped slot with a note shows the note', $meals()['2026-01-19']['dinner'] === '*Grosseltern'
    && slot_html($entries(), $mon, 'dinner') === '<em class="note">Grosseltern</em>');
check('swap to an unknown meal is rejected', throws(fn() => save_date_slot($swapDb, true, $mon, 'dinner', 999, null), UserError::class));
check('database rejects a note with a partner, and moved without a meal or partner', throws(fn() => $swapDb->exec(
    "INSERT INTO date_slot VALUES ('2026-01-20', 'lunch', 1, NULL, 'Notiz', '2026-01-21', 'lunch', 0)"), PDOException::class)
    && throws(fn() => $swapDb->exec("INSERT INTO date_slot VALUES ('2026-01-20', 'lunch', 1, NULL, NULL, '2026-01-21', 'lunch', 1)"),
        PDOException::class)
    && throws(fn() => $swapDb->exec("INSERT INTO date_slot VALUES ('2026-01-20', 'lunch', 1, $a, NULL, NULL, NULL, 1)"),
        PDOException::class));
save_date_slot($swapDb, true, $wed, 'dinner', $e, null);
check('meal swapped in from today on cannot be deleted',
    throws(fn() => delete_meal($swapDb, $e, $wed), UserError::class) && find_meal($swapDb, $e) !== null);
delete_meal($swapDb, $e, $thu);
check('meal only in past swaps is deleted with its swaps', find_meal($swapDb, $e) === null
    && $meals()['2026-01-21']['dinner'] === '-');

// Planning: now is Wednesday 2026-01-21. The current week (from 2026-01-17, first use) is Woche 1, the next week
// (from 2026-01-24) Woche 2.
putenv('MENUPLAN_NOW=2026-01-21 12:00');
$planDb = open_db("$tmp/planning");
$cur = $d('2026-01-17');
$sat = $d('2026-01-24');
$sun = $d('2026-01-25');
$mon2 = $d('2026-01-26');
check('without any week, every week is Woche 1 and not planned', load_week($planDb, $sat) === ['blueprint' => 1, 'planned' => false]);
plan_current_week($planDb);
check('first use: the current week is a planned Woche 1, the weeks before and after alternate',
    load_week($planDb, $cur) === ['blueprint' => 1, 'planned' => true]
    && load_week($planDb, $sat) === ['blueprint' => 2, 'planned' => false]
    && load_week($planDb, $d('2026-01-31'))['blueprint'] === 1 && load_week($planDb, $d('2026-01-10'))['blueprint'] === 2);
$planMeal = fn(string $name): int => create_meal($planDb, meal_input($name, ['shelf_life' => 'long']));
$p = $planMeal('P');
$q = $planMeal('Q');
$r = $planMeal('R');
save_blueprint_slot($planDb, 1, 5, 'lunch', $p, null);    // Wed 2026-01-21
save_blueprint_slot($planDb, 2, 1, 'lunch', $p, null);    // Sat 2026-01-24
save_blueprint_slot($planDb, 2, 1, 'dinner', $q, null);
save_blueprint_slot($planDb, 2, 3, 'lunch', $r, null);    // Mon 2026-01-26
$planEntries = fn(): array => load_entries($planDb, $cur, add_days($sat, 13));
$planMeals = fn(string $date): array => array_map(fn(array $entry): string => ($entry['changed'] ? '*' : '')
    . ($entry['meal'] ?? $entry['note'] ?? '-'), $planEntries()[$date]);
check('the first week of use follows the week blueprints, the next week its week blueprint',
    $planMeals('2026-01-21')['lunch'] === 'P' && $planMeals('2026-01-24') === ['lunch' => 'P', 'dinner' => 'Q']);
exchange_meals($planDb, false, [$sat, 'lunch', $p], [$mon2, 'lunch', $r]);
save_date_slot($planDb, false, $sat, 'dinner', null, null);
check('planning changes are marked until the week is planned',
    $planMeals('2026-01-24') === ['lunch' => '*R', 'dinner' => '*-'] && $planMeals('2026-01-26')['lunch'] === '*P'
    && slot_html($planEntries(), $sat, 'dinner') === '<em class="skipped">Ausgelassen</em>'
    && str_contains(slot_html($planEntries(), $sat, 'lunch'), 'getauscht'));
check('reset of a swap ignores planning changes', reset_slots($planDb, true, $sat, 'lunch') === []);
reset_date_slot($planDb, false, $mon2, 'lunch');
check('reset while planning restores the week blueprint, also of the linked slot',
    $planMeals('2026-01-24') === ['lunch' => 'P', 'dinner' => '*-'] && $planMeals('2026-01-26')['lunch'] === 'R');
move_meal($planDb, false, [$sat, 'dinner'], [$mon2, 'lunch', $r]);
save_blueprint_slot($planDb, 2, 2, 'lunch', $q, null);    // Sun 2026-01-25
check('unchanged slots follow the week blueprint while planning', $planMeals('2026-01-25')['lunch'] === 'Q'
    && str_contains(slot_html($planEntries(), $mon2, 'lunch'), 'Verschoben auf Samstag Abend'));
check('the other week blueprint cannot be chosen for a planned week', !choose_blueprint($planDb, $cur, 2));
check('complete planning, only once', complete_week($planDb, $sat)
    && load_week($planDb, $sat) === ['blueprint' => 2, 'planned' => true] && !complete_week($planDb, $sat));
check('planned week: changes no longer marked, an emptied slot is not planned',
    $planMeals('2026-01-24') === ['lunch' => 'P', 'dinner' => 'R'] && $planMeals('2026-01-26')['lunch'] === '-'
    && slot_html($planEntries(), $mon2, 'lunch') === '<span class="unplanned">Nicht geplant</span>'
    && slot_html($planEntries(), $sat, 'dinner') === '<p class="meal-name">R</p>');
save_blueprint_slot($planDb, 2, 2, 'lunch', null, null);
save_blueprint_slot($planDb, 2, 4, 'lunch', $q, null);    // Tue 2026-01-27, empty when completed
check('changes to the week blueprints do not affect a planned week',
    $planMeals('2026-01-25')['lunch'] === 'Q' && $planMeals('2026-01-27')['lunch'] === '-');
check('the week after a planned week takes the other week blueprint', load_week($planDb, $d('2026-01-31'))['blueprint'] === 1);
exchange_meals($planDb, true, [$sat, 'lunch', $p], [$sat, 'dinner', $r]);
check('a swap in a planned week is marked', $planMeals('2026-01-24') === ['lunch' => '*R', 'dinner' => '*P']);
reset_date_slot($planDb, true, $sat, 'dinner');
check('reset of a swap restores the planned week', $planMeals('2026-01-24') === ['lunch' => 'P', 'dinner' => 'R']);
save_blueprint_slot($planDb, 1, 5, 'lunch', null, null);
save_blueprint_slot($planDb, 2, 1, 'lunch', null, null);
try {
    delete_meal($planDb, $p, today());
    $deleteError = '';
} catch (UserError $exception) {
    $deleteError = $exception->getMessage();
}
check('a meal in a planned week from today on cannot be deleted', str_contains($deleteError, 'ab heute eingeplant'));
save_blueprint_slot($planDb, 2, 1, 'lunch', $p, null);
putenv('MENUPLAN_NOW=2026-01-28 12:00');
$next = $d('2026-01-31');
save_date_slot($planDb, false, $next, 'lunch', $q, null);
check('choosing the other week blueprint discards the planning, the weeks after alternate from it',
    choose_blueprint($planDb, $next, 2) && load_week($planDb, $next) === ['blueprint' => 2, 'planned' => false]
    && load_week($planDb, $d('2026-02-07'))['blueprint'] === 1 && $planMeals('2026-01-31')['lunch'] === 'P');
save_date_slot($planDb, false, $next, 'dinner', null, 'Besuch');
putenv('MENUPLAN_NOW=2026-01-31 08:00');
plan_current_week($planDb);
save_blueprint_slot($planDb, 2, 1, 'lunch', $r, null);
check('a week not planned by its Saturday becomes a planned week with its planning so far',
    load_week($planDb, $next) === ['blueprint' => 2, 'planned' => true]
    && $planMeals('2026-01-31') === ['lunch' => 'P', 'dinner' => 'Besuch']);
putenv('MENUPLAN_NOW');

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
check('test data fills 27 of 28 slots and 23 meals', $count($testDb, 'blueprint_slot') === 27 && $count($testDb, 'meal') === 23);
$testDb->exec("INSERT INTO week VALUES ('2026-01-17', 2, 1)");
$testEntries = load_entries($testDb, $d('2026-01-19'), $d('2026-01-20'));
check('test data has a leftover pair (Minestrone)', isset(slot_hints($testEntries, $d('2026-01-19'), 'dinner')['cook_double'])
    && slot_hints($testEntries, $d('2026-01-20'), 'lunch') === ['leftovers' => 'Reste vom Vorabend']);
$usedNames = array_unique(array_merge(...array_values(TEST_MEAL_INGREDIENTS)));
check('test data has ingredients with categories and recipe links; Raclette and Zopf-Brunch without ingredients',
    $count($testDb, 'ingredient') === count($usedNames) && $count($testDb, 'meal') - count(TEST_MEAL_INGREDIENTS) === 2
    && find_meal($testDb, find_meal_id($testDb, 'Raclette'))['ingredients'] === []
    && find_meal($testDb, find_meal_id($testDb, 'Pizza'))['ingredients'][0]['category'] === 'dairy_eggs_ready_meals'
    && find_meal($testDb, find_meal_id($testDb, 'Kürbisrisotto'))['recipe_url'] === TEST_RECIPE_URLS['Kürbisrisotto']);
check('test data is not loaded twice', !load_test_data_if_empty($testDb) && $count($testDb, 'blueprint_slot') === 27);
$noteDb = open_db("$tmp/notedata");
save_blueprint_slot($noteDb, 1, 1, 'lunch', null, 'Eltern kochen');
check('test data is not loaded if a week blueprint has only a note', !load_test_data_if_empty($noteDb));

// --- HTTP ----------------------------------------------------------------------

// Today at noon: lunch is not past yet.
$noon = now()->format('Y-m-d') . ' 12:00';
putenv("MENUPLAN_NOW=$noon");
$weekStart = week_start(today());
$config = "$tmp/config.php";
file_put_contents($config, '<?php return ' . var_export([
    'password_hash' => password_hash('test-password', PASSWORD_DEFAULT),
    'data_dir' => "$tmp/http",
], true) . ';');
$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', ROOT_DIR . '/public', ROOT_DIR . '/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', "$tmp/server.log", 'w']], $pipes, null,
    ['MENUPLAN_CONFIG' => $config, 'MENUPLAN_NOW' => $noon]);
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
$nextWeekDay = request('GET', '/?date=' . add_days(today(), 7)->format('Y-m-d'))['body'];
check('empty slots shown as not planned, without a link',
    substr_count($day['body'], '<span class="unplanned">Nicht geplant</span>') === 2 && !str_contains($day['body'], 'href="/week-blueprints')
    && substr_count($nextWeekDay, '<span class="unplanned">Nicht geplant</span>') === 2);
check('the next week, not planned yet, is marked with a link to Planen, the current week not',
    str_contains($nextWeekDay, '<p class="planning-open">Planung offen · <a href="/planning">Planen</a></p>')
    && !str_contains($day['body'], 'planning-open')
    && str_contains(request('GET', '/?date=' . add_days(today(), 14)->format('Y-m-d'))['body'], '<p class="planning-open">Planung offen</p>'));
check('today button and current menu item swap on other days',
    !str_contains($day['body'], 'class="current">Heute</a>') && str_contains($day['body'], 'href="/" aria-current="page"')
    && str_contains($otherDay, 'class="current">Heute</a>') && !str_contains($otherDay, 'aria-current="page"'));
check('CSS and JS URLs change with the file', str_contains($day['body'],
    'href="/assets/app.css?v=' . filemtime(ROOT_DIR . '/public/assets/app.css') . '"')
    && str_contains($day['body'], 'src="/assets/app.js?v=' . filemtime(ROOT_DIR . '/public/assets/app.js') . '"'));
check('session cookie renewed on each request', str_contains(header_value($day, 'set-cookie'), 'Max-Age=15552000'));
check('previous/next day links (swipe targets)',
    str_contains($day['body'], 'href="/?date=' . add_days(today(), -1)->format('Y-m-d') . '" rel="prev"')
    && str_contains($day['body'], 'href="/?date=' . add_days(today(), 1)->format('Y-m-d') . '" rel="next"'));
check('invalid date redirects to today', redirects_to(request('GET', '/?date=2026-02-30'), '/'));
check('other days can be shown', str_contains($otherDay, '<h1>Donnerstag</h1>')
    && str_contains($otherDay, '24. Dezember 2026 · Woche') && !str_contains($otherDay, 'relative-day'));
check('tomorrow and yesterday are labelled',
    str_contains(request('GET', '/?date=' . add_days(today(), 1)->format('Y-m-d'))['body'], '<p class="relative-day">Morgen</p>')
    && str_contains(request('GET', '/?date=' . add_days(today(), -1)->format('Y-m-d'))['body'], '<p class="relative-day">Gestern</p>'));

// The first week of use is Woche 1 and follows the week blueprints.
$blueprintDay = week_day(today());
$token = csrf('/week-blueprints');
$newMealUrl = '/meals/new?'
    . http_build_query(['name' => 'Rösti <script>x</script>', 'blueprint' => 1, 'day' => $blueprintDay, 'meal_time' => 'lunch']);
check('unknown meal name in a week blueprint leads to the new meal form', redirects_to(request('POST', '/week-blueprints/slot', [
    'csrf' => $token, 'blueprint' => 1, 'day' => $blueprintDay, 'meal_time' => 'lunch', 'type' => 'meal',
    'text' => ' Rösti <script>x</script> ']), $newMealUrl));
$form = request('GET', $newMealUrl);
check('new meal form has the name (HTML-escaped), the slot and all tag values', $form['status'] === 200
    && str_contains($form['body'], 'value="Rösti &lt;script&gt;x&lt;/script&gt;"')
    && str_contains($form['body'], 'name="day" value="' . $blueprintDay . '"')
    && str_contains($form['body'], weekday(today()) . ' (Woche 1), Mittagessen')
    && substr_count($form['body'], 'type="radio"') === array_sum(array_map(fn(array $d): int => count($d['values']), MEAL_TAGS)));
check('new meal form: no value preselected', !str_contains($form['body'], 'checked'));
check('new meal form with an invalid slot is rejected',
    request('GET', '/meals/new?blueprint=3&day=1&meal_time=lunch')['status'] === 400
    && request('GET', '/meals/new?blueprint=1&day=8&meal_time=lunch')['status'] === 400);
check('slot stays empty until the meal is saved', substr_count(request('GET', '/')['body'], 'Nicht geplant') === 2);
$roesti = meal_input('Rösti <script>x</script>', ['prep_time' => 'slow', 'vegetable_level' => 'side_dish', 'shelf_life' => 'long']);
$incomplete = $roesti;
unset($incomplete['heaviness']);
$response = request('POST', '/meals/new', ['csrf' => $token, 'blueprint' => 1, 'day' => $blueprintDay, 'meal_time' => 'lunch'] + $incomplete);
check('missing tag: form shown again with the error and the entered values', $response['status'] === 422
    && str_contains($response['body'], 'Wähle bei jeder Eigenschaft einen Wert.')
    && str_contains($response['body'], 'value="Rösti &lt;script&gt;x&lt;/script&gt;"')
    && preg_match('/value="slow" required\s+checked>/', $response['body'])
    && str_contains($response['body'], 'name="meal_time" value="lunch"'));
check('saving the new meal fills the slot', redirects_to(request('POST', '/meals/new',
    ['csrf' => $token, 'blueprint' => 1, 'day' => $blueprintDay, 'meal_time' => 'lunch'] + $roesti), "/week-blueprints#slot-1-$blueprintDay-lunch"));
$day = request('GET', '/');
check('day view shows the meal (HTML-escaped) with tags and hints',
    str_contains($day['body'], '<p class="meal-name">Rösti &lt;script&gt;x&lt;/script&gt;</p><span class="tags"')
    && str_contains($day['body'], '<ul class="hints" aria-label="Hinweise"><li class="hint-slow">Lange Zubereitung</li>'
        . '<li class="hint-side_dish">Gemüsebeilage kochen</li></ul>')
    && !str_contains($day['body'], '<script>x'));
request('POST', '/week-blueprints/slot', ['csrf' => $token, 'blueprint' => 1, 'day' => $blueprintDay, 'meal_time' => 'dinner',
    'type' => 'note', 'text' => 'Eltern kochen']);
$week = request('GET', '/week');
check('week view shows the note and the meal', str_contains($week['body'], '<em class="note">Eltern kochen</em>')
    && str_contains($week['body'], 'Rösti &lt;script&gt;'));
check('week view shows Woche 1 and the dates from Saturday to Friday',
    str_contains($week['body'], '<h1>Woche 1 · ' . format_week_dates($weekStart) . '</h1>')
    && (bool)preg_match('~Samstag.*Sonntag.*Montag.*Dienstag.*Mittwoch.*Donnerstag.*Freitag~s', $week['body'])
    && !str_contains($week['body'], 'planning-open'));
check('week view highlights today', substr_count($week['body'], 'aria-current="date"') === 1);
check('week view shows hints but no tags', str_contains($week['body'], '<li class="hint-slow">Lange Zubereitung</li>')
    && !str_contains($week['body'], 'class="tags"'));
check('this week button only in other weeks', !str_contains($week['body'], 'Diese Woche</a>')
    && str_contains(request('GET', '/week?date=' . add_days($weekStart, 7)->format('Y-m-d'))['body'], 'Diese Woche</a>'));
$nextWeek = request('GET', '/week?date=' . add_days($weekStart, 7)->format('Y-m-d'))['body'];
check('next week is Woche 2, not planned yet', str_contains($nextWeek, '<h1>Woche 2 · ')
    && str_contains($nextWeek, '<p class="planning-open">Planung offen · <a href="/planning">Planen</a></p>'));
check('week after next is Woche 1 again',
    str_contains(request('GET', '/week?date=' . add_days($weekStart, 14)->format('Y-m-d'))['body'], '<h1>Woche 1 · '));

request('POST', '/week-blueprints/slot', ['csrf' => $token, 'blueprint' => 2, 'day' => 7, 'meal_time' => 'dinner',
    'type' => 'note', 'text' => 'Auswärts']);
$blueprints = request('GET', '/week-blueprints');
check('week blueprint editor has 28 slot forms, Saturday first, under the Planen tab',
    substr_count($blueprints['body'], 'action="/week-blueprints/slot"') === 28
    && (bool)preg_match('~id="woche-1">.*?<h3>Samstag</h3>.*?<h3>Freitag</h3>.*?id="woche-2"~s', $blueprints['body'])
    && str_contains($blueprints['body'], 'href="/planning" aria-current="page"'));
check('week blueprint editor suggests meals', str_contains($blueprints['body'], '<option value="Rösti &lt;script&gt;'));
check('week blueprint editor shows saved message at the slot', (bool)preg_match(
    '/id="slot-2-7-dinner".*?value="Auswärts".*?Gespeichert\..*?<\/form>/s', $blueprints['body']));
check('saved message is shown only once', !str_contains(request('GET', '/week-blueprints')['body'], 'Gespeichert.'));
check('invalid slot is rejected', request('POST', '/week-blueprints/slot', ['csrf' => $token, 'blueprint' => 3, 'day' => 1,
    'meal_time' => 'lunch', 'type' => 'meal', 'text' => 'x'])['status'] === 400);
check('slot POST with wrong CSRF token is rejected', request('POST', '/week-blueprints/slot', ['csrf' => str_repeat('0', 64),
    'blueprint' => 1, 'day' => 1, 'meal_time' => 'lunch', 'type' => 'meal', 'text' => 'x'])['status'] === 400);

$response = request('POST', '/meals/new', ['csrf' => $token] + meal_input('Spaghetti'));
check('new meal without a slot goes to the meal list',
    $response['status'] === 303 && preg_match('~^/meals#meal-\d+$~', header_value($response, 'location')));
check('existing meal name (any case) is saved directly', redirects_to(request('POST', '/week-blueprints/slot', ['csrf' => $token,
    'blueprint' => 2, 'day' => 2, 'meal_time' => 'lunch', 'type' => 'meal', 'text' => 'SPAGHETTI']), '/week-blueprints#slot-2-2-lunch')
    && preg_match('/id="slot-2-2-lunch".*?value="Spaghetti"/s', request('GET', '/week-blueprints')['body']));
request('POST', '/week-blueprints/slot', ['csrf' => $token, 'blueprint' => 2, 'day' => 2, 'meal_time' => 'lunch', 'type' => 'meal', 'text' => '']);
$meals = request('GET', '/meals');
preg_match_all('~href="/meals/edit\?id=(\d+)"~', $meals['body'], $match);
check('meal list links both meals and shows their uses', $meals['status'] === 200 && count($match[1]) === 2
    && str_contains($meals['body'], '1× in Vorlagen') && str_contains($meals['body'], 'Nicht in Vorlagen')
    && substr_count($meals['body'], '<span class="tags"') === 2
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
    + meal_input('Rösti', ['vegetable_level' => 'raw_veggies_needed', 'shelf_life' => 'long'])), "/meals#meal-$roestiId"));
check('saved message shown at the meal',
    (bool)preg_match("/id=\"meal-$roestiId\".*?Gespeichert\..*?<\/li>/s", request('GET', '/meals')['body']));
$day = request('GET', '/')['body'];
check('day view shows the new name and hints', str_contains($day, '<p class="meal-name">Rösti</p>')
    && str_contains($day, '<ul class="hints" aria-label="Hinweise"><li class="hint-raw_veggies_needed">Rohkost dazu</li></ul>'));
check('used meal cannot be deleted',
    redirects_to(request('POST', '/meals/delete', ['csrf' => $token, 'id' => $roestiId]), "/meals/edit?id=$roestiId")
    && str_contains(request('GET', "/meals/edit?id=$roestiId")['body'], 'kann nicht gelöscht werden'));
check('unused meal can be deleted', redirects_to(request('POST', '/meals/delete', ['csrf' => $token,
    'id' => $spaghettiId]), '/meals') && !str_contains(request('GET', '/meals')['body'], 'Spaghetti'));

// Ingredients and recipe link (FR-27 to FR-30). Rösti is today's lunch.
$roestiInput = ['csrf' => $token, 'id' => $roestiId] + meal_input('Rösti', ['vegetable_level' => 'raw_veggies_needed', 'shelf_life' => 'long']);
$edit = request('GET', "/meals/edit?id=$roestiId")['body'];
check('meal form: recipe link, one empty ingredient row, a row template, + Zutat hidden without JavaScript',
    str_contains($edit, 'name="recipe_url"') && substr_count($edit, 'name="ingredient_name[]"') === 2
    && str_contains($edit, '<template id="ingredient-row">') && str_contains($edit, 'class="secondary outline add-ingredient" hidden>')
    && substr_count($edit, '<option value="fruit_vegetables">Früchte &amp; Gemüse</option>') === 2);
check('meal list marks meals without ingredients', str_contains(request('GET', '/meals')['body'], 'Keine Zutaten'));
$response = request('POST', '/meals/edit', $roestiInput + ['recipe_url' => 'javascript:alert(1)',
    'ingredient_name' => ['Kartoffeln'], 'ingredient_category' => ['fruit_vegetables']]);
check('recipe link without http(s) is rejected, entered ingredients kept', $response['status'] === 422
    && str_contains($response['body'], 'mit https:// beginnt') && str_contains($response['body'], 'value="Kartoffeln"')
    && str_contains($response['body'], '<option value="fruit_vegetables" selected>'));
$response = request('POST', '/meals/edit', $roestiInput + ['ingredient_name' => ['Kartoffeln', 'Butter'],
    'ingredient_category' => ['fruit_vegetables', '']]);
check('new ingredient without a category is rejected', $response['status'] === 422
    && str_contains($response['body'], 'Wähle eine Kategorie für «Butter».'));
check('save ingredients and recipe link', redirects_to(request('POST', '/meals/edit', $roestiInput + [
    'recipe_url' => ' https://example.com/rösti?a=1&b=2 ', 'ingredient_name' => ['Kartoffeln', '', 'Butter <b>', 'kartoffeln'],
    'ingredient_category' => ['fruit_vegetables', '', 'dairy_eggs_ready_meals', 'other']]), "/meals#meal-$roestiId"));
$edit = request('GET', "/meals/edit?id=$roestiId")['body'];
check('edit form shows the saved ingredients with their category, suggestions and the recipe link',
    substr_count($edit, 'name="ingredient_name[]"') === 4
    && (bool)preg_match('~value="Kartoffeln".*?<option value="fruit_vegetables" selected>.*?value="Butter &lt;b&gt;".*?'
        . '<option value="dairy_eggs_ready_meals" selected>~s', $edit)
    && str_contains($edit, 'value="https://example.com/rösti?a=1&amp;b=2"') && str_contains($edit, '<option value="Butter &lt;b&gt;" data-category="dairy_eggs_ready_meals"></option>'));
check('meal list: no mark once a meal has ingredients', !str_contains(request('GET', '/meals')['body'], 'Keine Zutaten'));
check('day view shows the recipe link, opening in a new tab', str_contains(request('GET', '/')['body'],
    '<a href="https://example.com/rösti?a=1&amp;b=2" class="recipe" target="_blank" rel="noopener noreferrer">Rezept</a>'));
$groceries = request('GET', '/grocery-list');
check('grocery list of the current week (planned), linked from the week view', $groceries['status'] === 200
    && str_contains(request('GET', '/week')['body'], '<a href="' . e(grocery_list_url($weekStart)) . '">Einkaufsliste</a>')
    && (bool)preg_match('~<h2>Früchte &amp; Gemüse</h2>.*?value="kartoffeln".*?Kartoffeln <small>Rösti</small>.*?'
        . '<h2>Milchprodukte, Eier &amp; frische Fertiggerichte</h2>.*?Butter &lt;b&gt; <small>Rösti</small>~s', $groceries['body'])
    && str_contains($groceries['body'], 'data-week="' . $weekStart->format('Y-m-d') . '"')
    && str_contains($groceries['body'], 'href="/week" aria-current="page"'));

// Today: lunch Rösti (plan), dinner note "Eltern kochen" (plan).
$todayKey = today()->format('Y-m-d');
$slotInput = fn(string $mealTime, string $date = ''): array => ['csrf' => $token, 'date' => $date ?: $todayKey, 'meal_time' => $mealTime];
$swapUrl = fn(string $path, string $mealTime, string $date = ''): string => $path . '?' . http_build_query(['date' => $date ?: $todayKey, 'meal_time' => $mealTime]);
$day = request('GET', '/')['body'];
check('day view: meal slot can be changed or skipped, note slot only changed',
    substr_count($day, '<details class="slot-menu">') === 2 && str_contains($day, '<summary aria-label="Mittagessen ändern">')
    && !str_contains($day, ' past') && str_contains($day, 'href="' . e($swapUrl('/swap', 'lunch')) . '" class="swap">Anderes Gericht</a>')
    && str_contains($day, 'href="' . e($swapUrl('/swap/skip', 'lunch')) . '" class="skip">Auslassen</a>')
    && str_contains($day, 'href="' . e($swapUrl('/swap', 'dinner')) . '" class="swap">Gericht planen</a>')
    && !str_contains($day, e($swapUrl('/swap/skip', 'dinner'))) && !str_contains($day, 'Zurücksetzen'));
$yesterday = add_days(today(), -1)->format('Y-m-d');
$yesterdayView = request('GET', "/?date=$yesterday")['body'];
check('past meal times shown as past', substr_count($yesterdayView, '<article class="meal-time lunch past">') === 1
    && substr_count($yesterdayView, '<span class="visually-hidden"> (vorbei)</span>') === 2);
check('week view: meal times before today are past', substr_count(request('GET', '/week')['body'], ' past">')
    === 2 * (week_day(today()) - 1));
check('no swap actions on past dates or in the next week while it is not planned', !str_contains($yesterdayView, 'slot-actions')
    && !str_contains(request('GET', '/?date=' . add_days(today(), 7)->format('Y-m-d'))['body'], 'slot-actions'));
check('swap page for a past date: back to the day with a message',
    redirects_to(request('GET', $swapUrl('/swap', 'lunch', $yesterday)), "/?date=$yesterday")
    && str_contains(request('GET', "/?date=$yesterday")['body'], 'Diese Mahlzeit kannst du hier nicht ändern.'));
check('swap page with an invalid slot is rejected', request('GET', '/swap?date=x&meal_time=lunch')['status'] === 400
    && request('GET', $swapUrl('/swap', 'brunch'))['status'] === 400
    && request('POST', '/swap/reset', $slotInput('brunch'))['status'] === 400);
$response = request('GET', $swapUrl('/meals/new', 'dinner'));
check('new meal form from the swap page names the date', $response['status'] === 200
    && str_contains($response['body'], 'Nach dem Speichern wird es eingetragen: ' . format_short_date(today()) . ', Abendessen.')
    && str_contains($response['body'], 'name="date" value="' . $todayKey . '"')
    && str_contains($response['body'], 'href="' . e($swapUrl('/swap', 'dinner')) . '"'));
check('saving the new meal swaps it in', redirects_to(request('POST', '/meals/new', $slotInput('dinner')
    + meal_input('Pasta', ['protein_source' => 'fish', 'shelf_life' => 'long'])), "/?date=$todayKey"));
$day = request('GET', "/?date=$todayKey")['body'];
check('day view: swapped meal marked, reset possible', str_contains($day, 'Getauscht.')
    && str_contains($day, '<p class="meal-name">Pasta <small class="swapped">getauscht</small></p>')
    && substr_count($day, 'action="/swap/reset"') === 1 && str_contains($day, 'Eltern kochen') === false);
check('week view shows the swapped meal', str_contains(request('GET', '/week')['body'],
    '<p class="meal-name">Pasta <small class="swapped">getauscht</small></p>'));
// Rösti keeps only briefly from now on: replacing or skipping it warns (FR-20).
request('POST', '/meals/edit', ['csrf' => $token, 'id' => $roestiId] + meal_input('Rösti', ['shelf_life' => 'short']));
$swap = request('GET', $swapUrl('/swap', 'lunch'));
check('swap page: partner this week, meal list, warning for unused ingredients', $swap['status'] === 200
    && str_contains($swap['body'], 'name="partner_meal_time" value="dinner"') && str_contains($swap['body'], '<strong>Pasta</strong>')
    && substr_count($swap['body'], 'name="meal_id"') === 2 && substr_count($swap['body'], '<span class="tags"') === 3
    && !preg_match('~<button(?:(?!</button>).)*<(?:ul|li|div|p)\b~s', $swap['body'])
    && str_contains($swap['body'], 'Die Zutaten für Rösti bleiben übrig und halten nur wenige Tage.')
    && str_contains($swap['body'], 'href="' . e($swapUrl('/meals/new', 'lunch')) . '"'));
$filtered = request('GET', $swapUrl('/swap', 'lunch') . '&protein_source=fish&prep_time=x')['body'];
check('meal list filter (invalid values ignored)', substr_count($filtered, 'name="meal_id"') === 1
    && str_contains($filtered, '<option value="fish" selected>') && str_contains($filtered, '<strong>Pasta</strong>')
    && substr_count(request('GET', $swapUrl('/swap', 'lunch') . '&prep_time=slow&protein_source=fish')['body'], 'name="meal_id"') === 0);
check('exchange the two meals of today', redirects_to(request('POST', '/swap/exchange', $slotInput('lunch')
    + ['partner_date' => $todayKey, 'partner_meal_time' => 'dinner']), "/?date=$todayKey"));
$day = request('GET', '/')['body'];
check('day view after the exchange', (bool)preg_match('~Mittagessen.*Pasta <small class="swapped">.*Abendessen.*Rösti <small class="swapped">~s', $day)
    && substr_count($day, 'action="/swap/reset"') === 2);
check('exchange with the same meal is rejected', redirects_to(request('POST', '/swap/exchange', $slotInput('lunch')
    + ['partner_date' => $todayKey, 'partner_meal_time' => 'lunch']), $swapUrl('/swap', 'lunch'))
    && str_contains(request('GET', $swapUrl('/swap', 'lunch'))['body'], 'Der Plan hat sich inzwischen geändert.'));
check('reset restores the plan of both slots', redirects_to(request('POST', '/swap/reset', $slotInput('dinner')), "/?date=$todayKey")
    && !str_contains($day = request('GET', '/')['body'], 'getauscht') && str_contains($day, 'Eltern kochen')
    && str_contains($day, '<p class="meal-name">Rösti</p>'));
$swap = request('GET', $swapUrl('/swap', 'dinner'))['body'];
check('swap page of a note slot: plan a meal by moving it here', str_contains($swap, '<h1>Gericht planen</h1>')
    && str_contains($swap, '<h2>Gericht verschieben</h2>') && !str_contains($swap, 'Gericht tauschen')
    && substr_count($swap, 'action="/swap/move"') === 1 && str_contains($swap, 'name="partner_meal_time" value="lunch"'));
check('move a meal to the note slot', redirects_to(request('POST', '/swap/move', $slotInput('dinner')
    + ['partner_date' => $todayKey, 'partner_meal_time' => 'lunch']), "/?date=$todayKey"));
$day = request('GET', '/')['body'];
$weekday = weekday(today());
check('day view after the move: moved meal, emptied slot, reset warnings', str_contains($day, 'Verschoben.')
    && (bool)preg_match('~Mittagessen.*<em class="moved">Verschoben auf ' . $weekday . ' Abend</em>.*Gericht planen.*'
        . 'Setzt auch ' . $weekday . ' Abend zurück\..*Abendessen.*Rösti <small class="swapped">verschoben</small>.*'
        . 'Setzt auch ' . $weekday . ' Mittag zurück\.~s', $day)
    && !str_contains($day, 'Eltern kochen'));
check('move to a slot with a meal is rejected', redirects_to(request('POST', '/swap/move', $slotInput('dinner')
    + ['partner_date' => $todayKey, 'partner_meal_time' => 'lunch']), $swapUrl('/swap', 'dinner'))
    && str_contains(request('GET', $swapUrl('/swap', 'dinner'))['body'], 'Der Plan hat sich inzwischen geändert.'));
check('move with an invalid partner is rejected', request('POST', '/swap/move', $slotInput('lunch')
    + ['partner_date' => $todayKey, 'partner_meal_time' => 'brunch'])['status'] === 400);
request('POST', '/swap/reset', $slotInput('lunch'));
check('reset of the emptied slot restores both', !str_contains($day = request('GET', '/')['body'], 'verschoben')
    && str_contains($day, 'Eltern kochen') && str_contains($day, '<p class="meal-name">Rösti</p>'));
$skip = request('GET', $swapUrl('/swap/skip', 'lunch'));
check('skip page shows the meal, the warning and a note field', $skip['status'] === 200
    && str_contains($skip['body'], 'Rösti') && str_contains($skip['body'], 'bleiben übrig') && str_contains($skip['body'], 'name="note"'));
check('skip page for a slot without a meal goes back to the day', redirects_to(request('GET', $swapUrl('/swap/skip', 'dinner')), "/?date=$todayKey"));
request('POST', '/swap/skip', $slotInput('lunch') + ['note' => ' Besuch <b>bei</b>  Grosseltern ']);
$day = request('GET', '/')['body'];
check('skip with a note (HTML-escaped)', str_contains($day, 'Ausgelassen.')
    && str_contains($day, '<em class="note">Besuch &lt;b&gt;bei&lt;/b&gt; Grosseltern</em>') && !str_contains($day, 'meal-name'));
request('POST', '/swap/skip', $slotInput('dinner') + ['note' => '']);
check('skip without a note', str_contains(request('GET', '/')['body'], '<em class="skipped">Ausgelassen</em>'));
check('skip with a too long note is rejected', redirects_to(request('POST', '/swap/skip', $slotInput('dinner')
    + ['note' => str_repeat('x', 101)]), $swapUrl('/swap/skip', 'dinner')));
check('swap to an unknown meal shows an error', redirects_to(request('POST', '/swap/meal', $slotInput('dinner') + ['meal_id' => 999]),
    "/?date=$todayKey") && str_contains(request('GET', '/')['body'], 'Dieses Gericht gibt es nicht mehr.'));
preg_match('~href="/meals/edit\?id=(\d+)">\s*<span class="avatar[^"]*"[^>]*></span>\s*<span>\s*<span class="meal-list-head">\s*<strong>Pasta~',
    request('GET', '/meals')['body'], $match);
$pastaId = $match[1] ?? '0';
request('POST', '/swap/meal', $slotInput('dinner') + ['meal_id' => $pastaId]);
check('meal swapped in this week cannot be deleted',
    redirects_to(request('POST', '/meals/delete', ['csrf' => $token, 'id' => $pastaId]), "/meals/edit?id=$pastaId")
    && str_contains(request('GET', "/meals/edit?id=$pastaId")['body'], 'ab heute eingeplant'));
check('swap POST without CSRF token is rejected', request('POST', '/swap/reset', ['date' => $todayKey, 'meal_time' => 'lunch'])['status'] === 400);
request('POST', '/swap/reset', $slotInput('lunch'));
request('POST', '/swap/reset', $slotInput('dinner'));

// Planning the next week (Woche 2): Saturday lunch Rösti, Monday lunch Pasta.
$nextSat = add_days($weekStart, 7);
$nextMon = add_days($weekStart, 9);
$satKey = $nextSat->format('Y-m-d');
$monKey = $nextMon->format('Y-m-d');
$blueprintInput = fn(int $day, string $text): array => ['csrf' => $token, 'blueprint' => 2, 'day' => $day, 'meal_time' => 'lunch',
    'type' => 'meal', 'text' => $text];
request('POST', '/week-blueprints/slot', $blueprintInput(1, 'Rösti'));
request('POST', '/week-blueprints/slot', $blueprintInput(3, 'Pasta'));
$planning = request('GET', '/planning');
check('Planen: the next week from Woche 2 with a ⋯ menu per slot, the other week blueprint, links', $planning['status'] === 200
    && str_contains($planning['body'], '<h1>Nächste Woche planen</h1>')
    && str_contains($planning['body'], 'Woche 2 · ' . format_week_dates($nextSat))
    && substr_count($planning['body'], '<details class="slot-menu">') === 14
    && str_contains($planning['body'], '<summary aria-label="Samstag Mittag ändern">')
    && str_contains($planning['body'], 'href="' . e(swap_url('/swap', $nextSat, 'lunch', false)) . '"')
    && str_contains($planning['body'], 'Stattdessen Woche 1 verwenden') && !str_contains($planning['body'], 'Verwirft')
    && str_contains($planning['body'], 'href="/planning/complete"') && str_contains($planning['body'], 'href="/week-blueprints"')
    && str_contains($planning['body'], 'href="/planning" aria-current="page"'));
$planInput = fn(string $date, string $mealTime): array => ['csrf' => $token, 'date' => $date, 'meal_time' => $mealTime, 'planning' => 1];
$swapPage = request('GET', swap_url('/swap', $nextSat, 'lunch', false))['body'];
check('planning swap page: forms send planning, no warning about unused ingredients, back to Planen',
    substr_count($swapPage, 'name="planning" value="1"') === 3 && !str_contains($swapPage, 'bleiben übrig')
    && str_contains($swapPage, 'name="partner_date" value="' . $monKey . '"')
    && str_contains($swapPage, 'href="' . e(swap_url('/meals/new', $nextSat, 'lunch', false)) . '"')
    && str_contains($swapPage, 'href="/planning" role="button"'));
check('the next week cannot be swapped while it is not planned',
    redirects_to(request('GET', swap_url('/swap', $nextSat, 'lunch')), "/?date=$satKey")
    && str_contains(request('GET', "/?date=$satKey")['body'], 'Diese Mahlzeit kannst du hier nicht ändern.'));
check('exchange while planning returns to Planen', redirects_to(request('POST', '/swap/exchange', $planInput($satKey, 'lunch')
    + ['partner_date' => $monKey, 'partner_meal_time' => 'lunch']), '/planning'));
request('POST', '/swap/skip', $planInput($monKey, 'lunch') + ['note' => '']);
$planning = request('GET', '/planning')['body'];
check('planning changes are marked, with the message at the top and reset warnings', str_contains($planning, 'Ausgelassen.')
    && str_contains($planning, '<p class="meal-name">Pasta <small class="swapped">getauscht</small></p>')
    && str_contains($planning, '<em class="skipped">Ausgelassen</em>') && str_contains($planning, 'Setzt auch Montag Mittag zurück.')
    && str_contains($planning, 'Verwirft deine Änderungen dieser Woche.'));
$nextDay = request('GET', "/?date=$satKey")['body'];
check('day view of the next week shows the planning, marked, without a ⋯ menu',
    str_contains($nextDay, 'Pasta <small class="swapped">getauscht</small>') && str_contains($nextDay, 'planning-open')
    && !str_contains($nextDay, 'slot-menu'));
check('reset while planning restores the week blueprint of both slots',
    redirects_to(request('POST', '/swap/reset', $planInput($monKey, 'lunch')), '/planning')
    && !str_contains($planning = request('GET', '/planning')['body'], 'getauscht') && !str_contains($planning, 'Ausgelassen</em>'));
request('POST', '/swap/skip', $planInput($monKey, 'lunch') + ['note' => '']);
check('choosing the other week blueprint discards the changes',
    redirects_to(request('POST', '/planning/blueprint', ['csrf' => $token, 'blueprint' => 1]), '/planning')
    && str_contains($planning = request('GET', '/planning')['body'], 'Woche 1 wird verwendet.')
    && str_contains($planning, 'Woche 1 · ') && str_contains($planning, 'Stattdessen Woche 2 verwenden')
    && !str_contains($planning, 'Ausgelassen</em>'));
check('invalid week blueprint is rejected', request('POST', '/planning/blueprint', ['csrf' => $token, 'blueprint' => 3])['status'] === 400);
request('POST', '/planning/blueprint', ['csrf' => $token, 'blueprint' => 2]);
request('POST', '/swap/skip', $planInput($monKey, 'lunch') + ['note' => '']);
check('no grocery list for a week not planned', redirects_to(request('GET', grocery_list_url($nextSat)), '/planning')
    && str_contains(request('GET', '/planning')['body'], 'sobald die Woche geplant ist')
    && !str_contains(request('GET', "/week?date=$satKey")['body'], 'Einkaufsliste'));
$complete = request('GET', '/planning/complete')['body'];
check('Planung abschliessen asks for confirmation', str_contains($complete, 'nicht wieder öffnen')
    && str_contains($complete, 'action="/planning/complete"'));
check('complete planning', redirects_to(request('POST', '/planning/complete', ['csrf' => $token]), '/planning')
    && str_contains($planning = request('GET', '/planning')['body'], 'Planung abgeschlossen.')
    && str_contains($planning, 'Die nächste Woche ist geplant.') && !str_contains($planning, 'slot-menu')
    && str_contains($planning, 'href="' . e(grocery_list_url($nextSat)) . '" role="button">Einkaufsliste</a>')
    && redirects_to(request('GET', '/planning/complete'), '/planning'));
check('completing again is refused', redirects_to(request('POST', '/planning/complete', ['csrf' => $token]), '/planning')
    && str_contains(request('GET', '/planning')['body'], WEEK_PLANNED));
check('a planning form after completion is refused',
    redirects_to(request('POST', '/swap/skip', $planInput($satKey, 'lunch') + ['note' => '']), '/planning')
    && str_contains(request('GET', '/planning')['body'], WEEK_PLANNED));
request('POST', '/week-blueprints/slot', $blueprintInput(1, 'Pasta'));
$nextWeek = request('GET', "/week?date=$satKey")['body'];
check('planned week: no marks, no notice, not affected by the week blueprints',
    !str_contains($nextWeek, 'swapped') && !str_contains($nextWeek, 'Ausgelassen') && !str_contains($nextWeek, 'planning-open')
    && str_contains($nextWeek, '<p class="meal-name">Rösti</p>') && !str_contains($nextWeek, 'Pasta'));
$nextDay = request('GET', "/?date=$satKey")['body'];
check('the next week can be swapped in the day view once planned', substr_count($nextDay, '<details class="slot-menu">') === 2
    && str_contains($nextDay, 'href="' . e(swap_url('/swap', $nextSat, 'lunch')) . '"') && !str_contains($nextDay, 'planning-open'));
check('swap in the planned next week', redirects_to(request('POST', '/swap/meal', $slotInput('lunch', $satKey)
    + ['meal_id' => $pastaId]), "/?date=$satKey")
    && str_contains(request('GET', "/?date=$satKey")['body'], 'Pasta <small class="swapped">getauscht</small>'));
$groceries = request('GET', grocery_list_url($nextSat))['body'];
check('grocery list includes swaps, excludes skipped slots, names meals without ingredients',
    !str_contains($groceries, 'Kartoffeln') && str_contains($groceries, 'Ohne Zutaten: Pasta</p>'));
check('reset of the swap restores the planned week',
    redirects_to(request('POST', '/swap/reset', $slotInput('lunch', $satKey)), "/?date=$satKey")
    && str_contains(request('GET', "/?date=$satKey")['body'], '<p class="meal-name">Rösti</p>'));

check('unknown page returns 404', request('GET', '/nope')['status'] === 404);
check('wrong method returns 405', request('GET', '/week-blueprints/slot')['status'] === 405);
foreach (['/manifest.webmanifest', '/sw.js', '/offline.html', '/assets/app.css', '/assets/app.js',
             '/assets/icons/icon.svg', '/assets/icons/icon-192.png', '/assets/icons/icon-512.png',
             '/assets/icons/icon-maskable-512.png', '/assets/icons/apple-touch-icon.png'] as $file) {
    check("static file $file", request('GET', $file)['status'] === 200);
}

check('no logout', request('POST', '/logout', ['csrf' => $token])['status'] === 404
    && !str_contains(request('GET', '/')['body'], 'Abmelden'));
$cookies = [];

for ($i = 0; $i < 5; $i++) {
    request('POST', '/login', ['csrf' => csrf('/login'), 'password' => 'wrong']);
}
request('POST', '/login', ['csrf' => csrf('/login'), 'password' => 'test-password']);
check('throttled after 5 failures, even with the correct password',
    str_contains(request('GET', '/login')['body'], 'Zu viele Fehlversuche') && redirects_to(request('GET', '/'), '/login'));
$writeConfig = function (bool $loadTestData) use ($config, $tmp): void {
    file_put_contents($config, '<?php return ' . var_export([
        'password_hash' => password_hash('test-password', PASSWORD_DEFAULT),
        'data_dir' => "$tmp/http-testdata",
        'load_test_data' => $loadTestData,
    ], true) . ';');
    sleep(3); // opcache.revalidate_freq: the server sees a changed config.php only after 2 s
};
$writeConfig(false);
request('GET', '/login');
check('no test data without the config setting', is_file("$tmp/http-testdata/db.sqlite")
    && $count(open_db("$tmp/http-testdata"), 'blueprint_slot') === 0);
$writeConfig(true);
request('GET', '/login');
check('config setting loads test data on the next request', $count(open_db("$tmp/http-testdata"), 'blueprint_slot') === 27);

check('no PHP errors in server log', !preg_match('/(Warning|Notice|Deprecated|Fatal)/', file_get_contents("$tmp/server.log")));

proc_terminate($server);
exec('rm -rf ' . escapeshellarg($tmp));
echo PHP_EOL . ($failures === 0 ? 'All tests passed.' : "$failures test(s) failed.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
