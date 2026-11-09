<?php

declare(strict_types=1);

function day_page(PDO $db, array $config): never
{
    $date = date_param('/');
    $today = today();
    $cycleStart = parse_date($config['cycle_start_date']);
    $isToday = $date == $today;
    render('day', format_long_date($date), [
        'date' => $date,
        'isToday' => $isToday,
        'relativeDay' => match (true) {
            $isToday => 'Heute',
            $date == add_days($today, 1) => 'Morgen',
            $date == add_days($today, -1) => 'Gestern',
            default => null,
        },
        'cycleWeek' => cycle_week(cycle_day($date, $cycleStart)),
        'entries' => load_entries($db, add_days($date, -1), add_days($date, 1), $cycleStart),
        'previous' => add_days($date, -1),
        'next' => add_days($date, 1),
    ], $isToday ? 'day' : '');
}

function week_page(PDO $db, array $config): never
{
    $monday = monday_of(date_param('/week'));
    $start = parse_date($config['cycle_start_date']);
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = add_days($monday, $i);
    }
    render('week', 'Woche', [
        'days' => $days,
        'entries' => load_entries($db, add_days($monday, -1), add_days($monday, 7), $start),
        'today' => today(),
        'isCurrentWeek' => $monday == monday_of(today()),
        'calendarWeek' => (int)$monday->format('W'),
        'cycleWeek' => cycle_week(cycle_day($monday, $start)),
        'previous' => add_days($monday, -7),
        'next' => add_days($monday, 7),
    ], 'week');
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

function plan_page(PDO $db): never
{
    render('plan', 'Plan bearbeiten', [
        'plan' => load_plan($db),
        'meals' => list_meals($db),
    ], 'plan');
}

function save_slot_action(PDO $db): never
{
    [$cycleDay, $mealTime] = slot_param($_POST) ?? render_error(400, 'Ungültige Anfrage.');
    $type = $_POST['type'] ?? null;
    if (!in_array($type, ['meal', 'note'], true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    $target = "slot-$cycleDay-$mealTime";
    try {
        $text = clean_text($_POST['text'] ?? null);
        $mealId = null;
        if ($type === 'meal' && $text !== '') {
            $mealId = find_meal_id($db, $text) ?? redirect('/meals/new?'
                . http_build_query(['name' => $text, 'cycle_day' => $cycleDay, 'meal_time' => $mealTime]));
        }
        save_slot($db, $cycleDay, $mealTime, $mealId, $type === 'note' && $text !== '' ? $text : null);
        set_flash('Gespeichert.', false, $target);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true, $target);
    }
    redirect("/plan#$target");
}

/** @return ?array{int, string} [cycle_day, meal_time], or null if both are missing. Invalid values: 400. */
function slot_param(array $input): ?array
{
    if (!isset($input['cycle_day']) && !isset($input['meal_time'])) {
        return null;
    }
    $cycleDay = filter_var($input['cycle_day'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => CYCLE_DAYS]]);
    $mealTime = $input['meal_time'] ?? null;
    if ($cycleDay === false || !in_array($mealTime, array_keys(MEAL_TIMES), true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    return [$cycleDay, $mealTime];
}

/** @return ?array{DateTimeImmutable, string} [date, meal_time], or null if both are missing. Invalid: 400; not swappable: back to the day view. */
function swap_slot_param(array $input): ?array
{
    if (!isset($input['date']) && !isset($input['meal_time'])) {
        return null;
    }
    $date = is_string($input['date'] ?? null) ? parse_date($input['date']) : null;
    $mealTime = $input['meal_time'] ?? null;
    if ($date === null || !in_array($mealTime, array_keys(MEAL_TIMES), true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    if (!is_swappable($date, $mealTime)) {
        set_flash('Ändern geht nur von jetzt bis Freitag.', true);
        redirect(day_url($date));
    }
    return [$date, $mealTime];
}

function day_url(DateTimeImmutable $date): string
{
    return '/?date=' . $date->format('Y-m-d');
}

function swap_url(string $path, DateTimeImmutable $date, string $mealTime): string
{
    return $path . '?' . http_build_query(['date' => $date->format('Y-m-d'), 'meal_time' => $mealTime]);
}

/** Entries from today until Friday of the planning week; today's lunch may be past (is_swappable()). */
function load_swappable_entries(PDO $db, array $config): array
{
    return load_entries($db, today(), add_days(planning_week_start(today()), 6), parse_date($config['cycle_start_date']));
}

/** "Anderes Gericht": exchange with a meal until Friday, or a meal from the meal list (FR-17). */
function swap_page(PDO $db, array $config): never
{
    [$date, $mealTime] = swap_slot_param($_GET) ?? render_error(400, 'Ungültige Anfrage.');
    $entries = load_swappable_entries($db, $config);
    $entry = $entries[$date->format('Y-m-d')][$mealTime];
    $partners = [];
    foreach ($entries as $key => $slots) {
        foreach ($slots as $partnerMealTime => $partner) {
            $partnerDate = parse_date($key);
            if ($entry['meal'] !== null && $partner['meal'] !== null && $partner['meal_id'] !== $entry['meal_id']
                && !is_past($partnerDate, $partnerMealTime)) {
                $partners[] = ['date' => $partnerDate, 'meal_time' => $partnerMealTime,
                    'warnings' => array_filter([move_warning($entry, $date, $partnerDate),
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
    render('swap', $entry['meal'] === null ? 'Gericht wählen' : 'Anderes Gericht', [
        'date' => $date,
        'mealTime' => $mealTime,
        'entry' => $entry,
        'partners' => $partners,
        'filters' => $filters,
        'meals' => $meals,
        'warning' => unused_warning($entry),
    ], '');
}

function swap_exchange_action(PDO $db, array $config): never
{
    [$date, $mealTime] = swap_slot_param($_POST) ?? render_error(400, 'Ungültige Anfrage.');
    [$partnerDate, $partnerMealTime] = swap_slot_param(['date' => $_POST['partner_date'] ?? null,
        'meal_time' => $_POST['partner_meal_time'] ?? null]) ?? render_error(400, 'Ungültige Anfrage.');
    $entries = load_swappable_entries($db, $config);
    $mealId = $entries[$date->format('Y-m-d')][$mealTime]['meal_id'];
    $partnerMealId = $entries[$partnerDate->format('Y-m-d')][$partnerMealTime]['meal_id'];
    if ($mealId === null || $partnerMealId === null || $mealId === $partnerMealId) {
        set_flash('Der Plan hat sich inzwischen geändert. Wähle nochmals.', true);
        redirect(swap_url('/swap', $date, $mealTime));
    }
    exchange_meals($db, [$date, $mealTime, $mealId], [$partnerDate, $partnerMealTime, $partnerMealId]);
    set_flash('Getauscht.', false);
    redirect(day_url($date));
}

function swap_meal_action(PDO $db): never
{
    [$date, $mealTime] = swap_slot_param($_POST) ?? render_error(400, 'Ungültige Anfrage.');
    $mealId = filter_var($_POST['meal_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($mealId === false) {
        render_error(400, 'Ungültige Anfrage.');
    }
    try {
        save_swap($db, $date, $mealTime, $mealId, null);
        set_flash('Getauscht.', false);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true);
    }
    redirect(day_url($date));
}

/** "Auslassen" with an optional note (FR-18). */
function skip_page(PDO $db, array $config): never
{
    [$date, $mealTime] = swap_slot_param($_GET) ?? render_error(400, 'Ungültige Anfrage.');
    $entry = load_swappable_entries($db, $config)[$date->format('Y-m-d')][$mealTime];
    if ($entry['meal'] === null) {
        redirect(day_url($date));
    }
    render('skip', 'Auslassen', [
        'date' => $date,
        'mealTime' => $mealTime,
        'entry' => $entry,
        'warning' => unused_warning($entry),
    ], '');
}

function skip_action(PDO $db): never
{
    [$date, $mealTime] = swap_slot_param($_POST) ?? render_error(400, 'Ungültige Anfrage.');
    try {
        $note = clean_text($_POST['note'] ?? null);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true);
        redirect(swap_url('/swap/skip', $date, $mealTime));
    }
    save_swap($db, $date, $mealTime, null, $note === '' ? null : $note);
    set_flash('Ausgelassen.', false);
    redirect(day_url($date));
}

function reset_swap_action(PDO $db): never
{
    [$date, $mealTime] = swap_slot_param($_POST) ?? render_error(400, 'Ungültige Anfrage.');
    reset_swap($db, $date, $mealTime);
    set_flash('Zurückgesetzt.', false);
    redirect(day_url($date));
}

function meals_page(PDO $db): never
{
    render('meals', 'Gerichte', ['meals' => list_meals($db)], 'meals');
}

/** Optional ?name=, and the slot to fill after saving (see new_meal_target()). */
function new_meal_page(): never
{
    try {
        $name = clean_text($_GET['name'] ?? '');
    } catch (UserError) {
        $name = '';
    }
    render_meal_form(['name' => $name], null, new_meal_target($_GET));
}

function new_meal_action(PDO $db): never
{
    $target = new_meal_target($_POST);
    $meal = meal_form_input();
    try {
        $meal['name'] = clean_text($meal['name']);
        $id = create_meal($db, $meal);
    } catch (UserError $e) {
        render_meal_form($meal, null, $target, 0, $e->getMessage());
    }
    if ($target !== null) {
        $target['fill']($db, $id);
    }
    set_flash('Gespeichert.', false, "meal-$id");
    redirect("/meals#meal-$id");
}

/**
 * Slot to fill after saving a new meal: from the plan editor (cycle_day, meal_time) or from "Anderes Gericht" (date, meal_time).
 * @return ?array{fields: array, text: string, cancel: string, fill: callable(PDO, int): never}
 */
function new_meal_target(array $input): ?array
{
    $swapSlot = isset($input['date']) ? swap_slot_param($input) : null;
    if ($swapSlot !== null) {
        [$date, $mealTime] = $swapSlot;
        return [
            'fields' => ['date' => $date->format('Y-m-d'), 'meal_time' => $mealTime],
            'text' => 'Nach dem Speichern wird es eingetragen: ' . format_short_date($date) . ', ' . MEAL_TIMES[$mealTime] . '.',
            'cancel' => swap_url('/swap', $date, $mealTime),
            'fill' => function (PDO $db, int $id) use ($date, $mealTime): never {
                save_swap($db, $date, $mealTime, $id, null);
                set_flash('Getauscht.', false);
                redirect(day_url($date));
            },
        ];
    }
    $slot = slot_param($input);
    if ($slot === null) {
        return null;
    }
    [$cycleDay, $mealTime] = $slot;
    return [
        'fields' => ['cycle_day' => $cycleDay, 'meal_time' => $mealTime],
        'text' => 'Noch nicht in der Liste. Nach dem Speichern wird es eingetragen: '
            . WEEKDAYS[($cycleDay - 1) % 7 + 1] . ' (Woche ' . cycle_week($cycleDay) . '), ' . MEAL_TIMES[$mealTime] . '.',
        'cancel' => "/plan#slot-$cycleDay-$mealTime",
        'fill' => function (PDO $db, int $id) use ($cycleDay, $mealTime): never {
            save_slot($db, $cycleDay, $mealTime, $id, null);
            set_flash('Gespeichert.', false, "slot-$cycleDay-$mealTime");
            redirect("/plan#slot-$cycleDay-$mealTime");
        },
    ];
}

function edit_meal_page(PDO $db): never
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $meal = ($id === false ? null : find_meal($db, $id)) ?? render_error(404, 'Dieses Gericht gibt es nicht.');
    render_meal_form($meal, $id, null, $meal['uses']);
}

function edit_meal_action(PDO $db): never
{
    $id = meal_id_param();
    $meal = meal_form_input();
    try {
        $meal['name'] = clean_text($meal['name']);
        update_meal($db, $id, $meal);
    } catch (UserError $e) {
        render_meal_form($meal, $id, null, find_meal($db, $id)['uses'] ?? 0, $e->getMessage());
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
function render_meal_form(array $meal, ?int $id, ?array $target, int $uses = 0, ?string $error = null): never
{
    render('meal', $id === null ? 'Neues Gericht' : 'Gericht bearbeiten',
        ['meal' => $meal, 'id' => $id, 'target' => $target, 'uses' => $uses, 'error' => $error],
        'meals', $error === null ? 200 : 422);
}

/** @return array{name: string, ...} name and tags as posted; unchecked by design (see execute_meal_write()). */
function meal_form_input(): array
{
    $meal = ['name' => is_string($_POST['name'] ?? null) ? $_POST['name'] : ''];
    foreach (array_keys(MEAL_TAGS) as $tag) {
        $meal[$tag] = is_string($_POST[$tag] ?? null) ? $_POST[$tag] : null;
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
