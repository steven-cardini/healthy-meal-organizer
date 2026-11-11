<?php

declare(strict_types=1);

function day_page(PDO $db): never
{
    $date = date_param('/');
    $today = today();
    $isToday = $date == $today;
    $entries = load_entries($db, add_days($date, -1), add_days($date, 1));
    $swappable = [];
    $resetWarnings = [];
    foreach (array_keys(MEAL_TIMES) as $mealTime) {
        $swappable[$mealTime] = slot_mode($db, $date, $mealTime) === true;
        if ($swappable[$mealTime] && $entries[$date->format('Y-m-d')][$mealTime]['changed']) {
            $resetWarnings[$mealTime] = reset_warning(reset_slots($db, true, $date, $mealTime), $date, $mealTime);
        }
    }
    render('day', format_long_date($date), [
        'date' => $date,
        'isToday' => $isToday,
        'relativeDay' => match (true) {
            $isToday => 'Heute',
            $date == add_days($today, 1) => 'Morgen',
            $date == add_days($today, -1) => 'Gestern',
            default => null,
        },
        'weekStart' => week_start($date),
        'week' => load_week($db, week_start($date)),
        'entries' => $entries,
        'swappable' => $swappable,
        'resetWarnings' => $resetWarnings,
        'previous' => add_days($date, -1),
        'next' => add_days($date, 1),
    ], $isToday ? 'day' : '');
}

function week_page(PDO $db): never
{
    $start = week_start(date_param('/week'));
    render('week', 'Woche', [
        'weekStart' => $start,
        'week' => load_week($db, $start),
        'days' => week_days($start),
        'entries' => load_entries($db, add_days($start, -1), add_days($start, 7)),
        'today' => today(),
        'isCurrentWeek' => $start == week_start(today()),
        'previous' => add_days($start, -7),
        'next' => add_days($start, 7),
    ], 'week');
}

/** *Einkaufsliste* of a planned week (FR-31 to FR-33). */
function grocery_list_page(PDO $db): never
{
    $start = week_start(date_param('/grocery-list'));
    $week = load_week($db, $start);
    if (!$week['planned']) {
        set_flash('Die Einkaufsliste gibt es, sobald die Woche geplant ist.', true);
        redirect('/planning');
    }
    render('grocery_list', 'Einkaufsliste', [
        'weekStart' => $start,
        'week' => $week,
        'list' => grocery_list(load_entries($db, $start, add_days($start, 6)), load_ingredients($db)),
    ], 'week');
}

function grocery_list_url(DateTimeImmutable $weekStart): string
{
    return '/grocery-list?date=' . $weekStart->format('Y-m-d');
}

/** @return list<DateTimeImmutable> the 7 days of the week starting on $start */
function week_days(DateTimeImmutable $start): array
{
    return array_map(fn(int $i): DateTimeImmutable => add_days($start, $i), range(0, 6));
}

/** The ?date= parameter, or today. An invalid date redirects to $path without it. */
function date_param(string $path): DateTimeImmutable
{
    $value = $_GET['date'] ?? null;
    if ($value === null) {
        return today();
    }
    return (is_string($value) ? parse_date($value) : null) ?? redirect($path);
}

/** *Nächste Woche planen* (FR-22 to FR-24). */
function planning_page(PDO $db): never
{
    $start = next_week_start();
    $week = load_week($db, $start);
    $entries = load_entries($db, add_days($start, -1), add_days($start, 7));
    $resetWarnings = [];
    foreach (week_days($start) as $day) {
        foreach (array_keys(MEAL_TIMES) as $mealTime) {
            if ($entries[$day->format('Y-m-d')][$mealTime]['changed']) {
                $resetWarnings[$day->format('Y-m-d')][$mealTime] = reset_warning(reset_slots($db, false, $day, $mealTime), $day, $mealTime);
            }
        }
    }
    render('planning', 'Nächste Woche planen', [
        'weekStart' => $start,
        'week' => $week,
        'days' => week_days($start),
        'entries' => $entries,
        'resetWarnings' => $resetWarnings,
    ], 'planning');
}

function next_week_start(): DateTimeImmutable
{
    return add_days(week_start(today()), 7);
}

/** Plans the next week from the other week blueprint (FR-22). */
function choose_blueprint_action(PDO $db): never
{
    $blueprint = filter_var($_POST['blueprint'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2]]);
    if ($blueprint === false) {
        render_error(400, 'Ungültige Anfrage.');
    }
    if (choose_blueprint($db, next_week_start(), $blueprint)) {
        set_flash("Woche $blueprint wird verwendet.", false);
    } else {
        set_flash(WEEK_PLANNED, true);
    }
    redirect('/planning');
}

/** Confirmation for *Planung abschliessen* (FR-24). */
function complete_page(PDO $db): never
{
    $start = next_week_start();
    $week = load_week($db, $start);
    if ($week['planned']) {
        redirect('/planning');
    }
    render('complete', 'Planung abschliessen', ['weekStart' => $start, 'week' => $week], 'planning');
}

function complete_action(PDO $db): never
{
    if (complete_week($db, next_week_start())) {
        set_flash('Planung abgeschlossen.', false);
    } else {
        set_flash(WEEK_PLANNED, true);
    }
    redirect('/planning');
}

function week_blueprints_page(PDO $db): never
{
    render('week_blueprints', 'Wochenvorlagen bearbeiten', [
        'blueprints' => load_blueprints($db),
        'meals' => list_meals($db),
    ], 'planning');
}

function save_blueprint_slot_action(PDO $db): never
{
    [$blueprint, $day, $mealTime] = blueprint_slot_param($_POST) ?? render_error(400, 'Ungültige Anfrage.');
    $type = $_POST['type'] ?? null;
    if (!in_array($type, ['meal', 'note'], true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    $target = "slot-$blueprint-$day-$mealTime";
    try {
        $text = clean_text($_POST['text'] ?? null);
        $mealId = null;
        if ($type === 'meal' && $text !== '') {
            $mealId = find_meal_id($db, $text) ?? redirect('/meals/new?' . http_build_query(
                ['name' => $text, 'blueprint' => $blueprint, 'day' => $day, 'meal_time' => $mealTime]));
        }
        save_blueprint_slot($db, $blueprint, $day, $mealTime, $mealId, $type === 'note' && $text !== '' ? $text : null);
        set_flash('Gespeichert.', false, $target);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true, $target);
    }
    redirect("/week-blueprints#$target");
}

/** @return ?array{int, int, string} [blueprint, day, meal_time], or null if all are missing. Invalid values: 400. */
function blueprint_slot_param(array $input): ?array
{
    if (!isset($input['blueprint']) && !isset($input['day']) && !isset($input['meal_time'])) {
        return null;
    }
    $blueprint = filter_var($input['blueprint'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2]]);
    $day = filter_var($input['day'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
    $mealTime = $input['meal_time'] ?? null;
    if ($blueprint === false || $day === false || !in_array($mealTime, array_keys(MEAL_TIMES), true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    return [$blueprint, $day, $mealTime];
}

/** How a slot can be changed: true: swap (FR-17), false: planning (FR-23), null: not at all. */
function slot_mode(PDO $db, DateTimeImmutable $date, string $mealTime): ?bool
{
    $next = next_week_start();
    $nextWeekPlanned = load_week($db, $next)['planned'];
    if (!$nextWeekPlanned && week_start($date) == $next) {
        return false;
    }
    return is_swappable($date, $mealTime, $nextWeekPlanned) ? true : null;
}

/**
 * @return ?array{DateTimeImmutable, string, bool} [date, meal_time, swap], or null if both are missing. Invalid: 400.
 * $input['planning'] is set by planning forms. A slot that cannot be changed that way (e.g. past, or the week was
 * planned in the meantime) goes back with a message. $swap: see save_date_slot().
 */
function date_slot_param(PDO $db, array $input): ?array
{
    if (!isset($input['date']) && !isset($input['meal_time'])) {
        return null;
    }
    $date = is_string($input['date'] ?? null) ? parse_date($input['date']) : null;
    $mealTime = $input['meal_time'] ?? null;
    if ($date === null || !in_array($mealTime, array_keys(MEAL_TIMES), true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    $planning = isset($input['planning']);
    $swap = slot_mode($db, $date, $mealTime);
    if ($swap === null || $swap === $planning) {
        set_flash($planning ? WEEK_PLANNED : 'Diese Mahlzeit kannst du hier nicht ändern.', true);
        redirect($planning ? '/planning' : day_url($date));
    }
    return [$date, $mealTime, $swap];
}

function day_url(DateTimeImmutable $date): string
{
    return '/?date=' . $date->format('Y-m-d');
}

/** URL of a swap page; for planning ($swap false) with planning=1 (see date_slot_param()). */
function swap_url(string $path, DateTimeImmutable $date, string $mealTime, bool $swap = true): string
{
    return $path . '?' . http_build_query(['date' => $date->format('Y-m-d'), 'meal_time' => $mealTime]
        + ($swap ? [] : ['planning' => 1]));
}

/** Where a change of a slot returns to: the day view (swap) or the *Planen* page. */
function return_url(bool $swap, DateTimeImmutable $date): string
{
    return $swap ? day_url($date) : '/planning';
}

/** Entries of the week of $date: exchanges and moves stay within one week (FR-17). */
function load_week_entries(PDO $db, DateTimeImmutable $date): array
{
    return load_entries($db, week_start($date), add_days(week_start($date), 6));
}

/**
 * "Anderes Gericht": exchange with a meal of the same week, or a meal from the meal list.
 * "Gericht planen" (slot without a meal): move a meal of the same week here, or a meal from the meal list (FR-17, FR-23).
 */
function swap_page(PDO $db): never
{
    [$date, $mealTime, $swap] = date_slot_param($db, $_GET) ?? render_error(400, 'Ungültige Anfrage.');
    $entries = load_week_entries($db, $date);
    $entry = $entries[$date->format('Y-m-d')][$mealTime];
    $partners = [];
    foreach ($entries as $key => $slots) {
        foreach ($slots as $partnerMealTime => $partner) {
            $partnerDate = parse_date($key);
            if ($partner['meal'] !== null && $partner['meal_id'] !== $entry['meal_id']
                && !is_past($partnerDate, $partnerMealTime)) {
                $partners[] = ['date' => $partnerDate, 'meal_time' => $partnerMealTime,
                    'warnings' => array_filter([$entry['meal'] === null ? null : move_warning($entry, $date, $partnerDate),
                        move_warning($partner, $partnerDate, $date)])] + $partner;
            }
        }
    }
    $filters = [];
    foreach (FILTER_TAGS as $tag) {
        $value = $_GET[$tag] ?? null;
        $filters[$tag] = is_string($value) && isset(MEAL_TAGS[$tag]['values'][$value]) ? $value : '';
    }
    $meals = array_filter(list_meals($db), fn(array $meal): bool =>
        array_filter($filters, fn(string $value, string $tag): bool => $value !== '' && $meal[$tag] !== $value,
            ARRAY_FILTER_USE_BOTH) === []);
    render('swap', $entry['meal'] === null ? 'Gericht planen' : 'Anderes Gericht', [
        'date' => $date,
        'mealTime' => $mealTime,
        'swap' => $swap,
        'entry' => $entry,
        'partners' => $partners,
        'filters' => $filters,
        'meals' => $meals,
        'warning' => $swap ? unused_warning($entry) : null,
    ], '');
}

/**
 * [date, meal_time] of the partner slot of an exchange or a move, or null if it cannot be changed the same way
 * ($swap) in the same week as $date (e.g. past in the meantime).
 */
function partner_slot_param(PDO $db, DateTimeImmutable $date, bool $swap): ?array
{
    $partnerDate = is_string($_POST['partner_date'] ?? null) ? parse_date($_POST['partner_date']) : null;
    $partnerMealTime = $_POST['partner_meal_time'] ?? null;
    if ($partnerDate === null || !in_array($partnerMealTime, array_keys(MEAL_TIMES), true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    return slot_mode($db, $partnerDate, $partnerMealTime) === $swap && week_start($partnerDate) == week_start($date)
        ? [$partnerDate, $partnerMealTime] : null;
}

function plan_changed(DateTimeImmutable $date, string $mealTime, bool $swap): never
{
    set_flash('Der Plan hat sich inzwischen geändert. Wähle nochmals.', true);
    redirect(swap_url('/swap', $date, $mealTime, $swap));
}

function swap_exchange_action(PDO $db): never
{
    [$date, $mealTime, $swap] = date_slot_param($db, $_POST) ?? render_error(400, 'Ungültige Anfrage.');
    $partner = partner_slot_param($db, $date, $swap) ?? plan_changed($date, $mealTime, $swap);
    [$partnerDate, $partnerMealTime] = $partner;
    $entries = load_week_entries($db, $date);
    $mealId = $entries[$date->format('Y-m-d')][$mealTime]['meal_id'];
    $partnerMealId = $entries[$partnerDate->format('Y-m-d')][$partnerMealTime]['meal_id'];
    if ($mealId === null || $partnerMealId === null || $mealId === $partnerMealId) {
        plan_changed($date, $mealTime, $swap);
    }
    exchange_meals($db, $swap, [$date, $mealTime, $mealId], [$partnerDate, $partnerMealTime, $partnerMealId]);
    set_flash('Getauscht.', false);
    redirect(return_url($swap, $date));
}

/** Moves the meal of the partner slot to this slot, which has no meal (FR-17). */
function swap_move_action(PDO $db): never
{
    [$date, $mealTime, $swap] = date_slot_param($db, $_POST) ?? render_error(400, 'Ungültige Anfrage.');
    [$partnerDate, $partnerMealTime] = partner_slot_param($db, $date, $swap) ?? plan_changed($date, $mealTime, $swap);
    $entries = load_week_entries($db, $date);
    $partnerMealId = $entries[$partnerDate->format('Y-m-d')][$partnerMealTime]['meal_id'];
    if ($entries[$date->format('Y-m-d')][$mealTime]['meal_id'] !== null || $partnerMealId === null) {
        plan_changed($date, $mealTime, $swap);
    }
    move_meal($db, $swap, [$date, $mealTime], [$partnerDate, $partnerMealTime, $partnerMealId]);
    set_flash('Verschoben.', false);
    redirect(return_url($swap, $date));
}

function swap_meal_action(PDO $db): never
{
    [$date, $mealTime, $swap] = date_slot_param($db, $_POST) ?? render_error(400, 'Ungültige Anfrage.');
    $mealId = filter_var($_POST['meal_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($mealId === false) {
        render_error(400, 'Ungültige Anfrage.');
    }
    try {
        save_date_slot($db, $swap, $date, $mealTime, $mealId, null);
        set_flash('Getauscht.', false);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true);
    }
    redirect(return_url($swap, $date));
}

/** "Auslassen" with an optional note (FR-18). */
function skip_page(PDO $db): never
{
    [$date, $mealTime, $swap] = date_slot_param($db, $_GET) ?? render_error(400, 'Ungültige Anfrage.');
    $entry = load_week_entries($db, $date)[$date->format('Y-m-d')][$mealTime];
    if ($entry['meal'] === null) {
        redirect(return_url($swap, $date));
    }
    render('skip', 'Auslassen', [
        'date' => $date,
        'mealTime' => $mealTime,
        'swap' => $swap,
        'entry' => $entry,
        'warning' => $swap ? unused_warning($entry) : null,
    ], '');
}

function skip_action(PDO $db): never
{
    [$date, $mealTime, $swap] = date_slot_param($db, $_POST) ?? render_error(400, 'Ungültige Anfrage.');
    try {
        $note = clean_text($_POST['note'] ?? null);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true);
        redirect(swap_url('/swap/skip', $date, $mealTime, $swap));
    }
    save_date_slot($db, $swap, $date, $mealTime, null, $note === '' ? null : $note);
    set_flash('Ausgelassen.', false);
    redirect(return_url($swap, $date));
}

function reset_action(PDO $db): never
{
    [$date, $mealTime, $swap] = date_slot_param($db, $_POST) ?? render_error(400, 'Ungültige Anfrage.');
    reset_date_slot($db, $swap, $date, $mealTime);
    set_flash('Zurückgesetzt.', false);
    redirect(return_url($swap, $date));
}

function meals_page(PDO $db): never
{
    render('meals', 'Gerichte', ['meals' => list_meals($db)], 'meals');
}

/** Optional ?name=, and the slot to fill after saving (see new_meal_target()). */
function new_meal_page(PDO $db): never
{
    try {
        $name = clean_text($_GET['name'] ?? '');
    } catch (UserError) {
        $name = '';
    }
    render_meal_form($db, ['name' => $name, 'recipe_url' => '', 'ingredients' => []], null, new_meal_target($db, $_GET));
}

function new_meal_action(PDO $db): never
{
    $target = new_meal_target($db, $_POST);
    $meal = meal_form_input();
    try {
        $meal['name'] = clean_text($meal['name']);
        $id = in_transaction($db, fn(): int => create_meal($db, $meal));
    } catch (UserError $e) {
        render_meal_form($db, $meal, null, $target, 0, $e->getMessage());
    }
    if ($target !== null) {
        $target['fill']($db, $id);
    }
    set_flash('Gespeichert.', false, "meal-$id");
    redirect("/meals#meal-$id");
}

/**
 * Slot to fill after saving a new meal: from the week blueprint editor (blueprint, day, meal_time) or from
 * "Anderes Gericht" (date, meal_time, and planning for planning).
 * @return ?array{fields: array, text: string, cancel: string, fill: callable(PDO, int): never}
 */
function new_meal_target(PDO $db, array $input): ?array
{
    $dateSlot = isset($input['date']) ? date_slot_param($db, $input) : null;
    if ($dateSlot !== null) {
        [$date, $mealTime, $swap] = $dateSlot;
        return [
            'fields' => ['date' => $date->format('Y-m-d'), 'meal_time' => $mealTime] + ($swap ? [] : ['planning' => 1]),
            'text' => 'Nach dem Speichern wird es eingetragen: ' . format_short_date($date) . ', ' . MEAL_TIMES[$mealTime] . '.',
            'cancel' => swap_url('/swap', $date, $mealTime, $swap),
            'fill' => function (PDO $db, int $id) use ($date, $mealTime, $swap): never {
                save_date_slot($db, $swap, $date, $mealTime, $id, null);
                set_flash('Getauscht.', false);
                redirect(return_url($swap, $date));
            },
        ];
    }
    $slot = blueprint_slot_param($input);
    if ($slot === null) {
        return null;
    }
    [$blueprint, $day, $mealTime] = $slot;
    $target = "slot-$blueprint-$day-$mealTime";
    return [
        'fields' => ['blueprint' => $blueprint, 'day' => $day, 'meal_time' => $mealTime],
        'text' => 'Noch nicht in der Liste. Nach dem Speichern wird es eingetragen: '
            . week_day_name($day) . " (Woche $blueprint), " . MEAL_TIMES[$mealTime] . '.',
        'cancel' => "/week-blueprints#$target",
        'fill' => function (PDO $db, int $id) use ($blueprint, $day, $mealTime, $target): never {
            save_blueprint_slot($db, $blueprint, $day, $mealTime, $id, null);
            set_flash('Gespeichert.', false, $target);
            redirect("/week-blueprints#$target");
        },
    ];
}

function edit_meal_page(PDO $db): never
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $meal = ($id === false ? null : find_meal($db, $id)) ?? render_error(404, 'Dieses Gericht gibt es nicht.');
    render_meal_form($db, $meal, $id, null, $meal['uses']);
}

function edit_meal_action(PDO $db): never
{
    $id = meal_id_param();
    $meal = meal_form_input();
    try {
        $meal['name'] = clean_text($meal['name']);
        in_transaction($db, fn() => update_meal($db, $id, $meal));
    } catch (UserError $e) {
        render_meal_form($db, $meal, $id, null, find_meal($db, $id)['uses'] ?? 0, $e->getMessage());
    }
    set_flash('Gespeichert.', false, "meal-$id");
    redirect("/meals#meal-$id");
}

function delete_meal_action(PDO $db): never
{
    $id = meal_id_param();
    try {
        delete_meal($db, $id, today());
        set_flash('Gericht gelöscht.', false);
        redirect('/meals');
    } catch (UserError $e) {
        set_flash($e->getMessage(), true);
        redirect("/meals/edit?id=$id");
    }
}

/** Errors are shown in the form with the entered values (no redirect, see docs/decisions.md D-22). $target: see new_meal_target(). */
function render_meal_form(PDO $db, array $meal, ?int $id, ?array $target, int $uses = 0, ?string $error = null): never
{
    render('meal', $id === null ? 'Neues Gericht' : 'Gericht bearbeiten',
        ['meal' => $meal, 'id' => $id, 'target' => $target, 'uses' => $uses, 'error' => $error,
            'ingredientCategories' => ingredient_categories($db)],
        'meals', $error === null ? 200 : 422);
}

/**
 * @return array{name: string, recipe_url: string, ingredients: list<array{name: string, category: string}>, ...}
 * as posted; unchecked by design (see meal_params()).
 */
function meal_form_input(): array
{
    $string = fn(mixed $value): string => is_string($value) ? $value : '';
    $meal = ['name' => $string($_POST['name'] ?? null), 'recipe_url' => $string($_POST['recipe_url'] ?? null), 'ingredients' => []];
    foreach (array_keys(MEAL_TAGS) as $tag) {
        $meal[$tag] = is_string($_POST[$tag] ?? null) ? $_POST[$tag] : null;
    }
    $names = is_array($_POST['ingredient_name'] ?? null) ? $_POST['ingredient_name'] : [];
    $categories = is_array($_POST['ingredient_category'] ?? null) ? $_POST['ingredient_category'] : [];
    foreach ($names as $i => $name) {
        $meal['ingredients'][] = ['name' => $string($name), 'category' => $string($categories[$i] ?? null)];
    }
    return $meal;
}

function meal_id_param(): int
{
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id !== false ? $id : render_error(400, 'Ungültige Anfrage.');
}

function login_page(): never
{
    if (logged_in()) {
        redirect('/');
    }
    render('login', 'Anmelden');
}

function login_action(PDO $db, array $config): never
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $now = time();
    if (login_blocked($db, $ip, $now)) {
        set_flash('Zu viele Fehlversuche. Versuche es in 15 Minuten nochmals.', true);
        redirect('/login');
    }
    $password = $_POST['password'] ?? null;
    if (!is_string($password) || !password_verify($password, $config['password_hash'])) {
        record_login_failure($db, $ip, $now);
        set_flash('Falsches Passwort.', true);
        redirect('/login');
    }
    clear_login_failures($db, $ip);
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    redirect('/');
}
