<?php

declare(strict_types=1);

function day_page(PDO $db, array $config): never
{
    $date = date_param('/');
    $today = today();
    $cycleDay = cycle_day($date, parse_date($config['cycle_start_date']));
    render('day', format_long_date($date), [
        'date' => $date,
        'relativeDay' => match (true) {
            $date == $today => 'Heute',
            $date == add_days($today, 1) => 'Morgen',
            $date == add_days($today, -1) => 'Gestern',
            default => null,
        },
        'cycleWeek' => cycle_week($cycleDay),
        'cycleDay' => $cycleDay,
        'plan' => load_plan($db),
        'previous' => add_days($date, -1),
        'next' => add_days($date, 1),
    ], 'day');
}

function week_page(PDO $db, array $config): never
{
    $monday = monday_of(date_param('/week'));
    $start = parse_date($config['cycle_start_date']);
    $plan = load_plan($db);
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $date = add_days($monday, $i);
        $days[] = ['date' => $date, 'cycleDay' => cycle_day($date, $start)];
    }
    render('week', 'Woche', [
        'days' => $days,
        'plan' => $plan,
        'today' => today(),
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

function meals_page(PDO $db): never
{
    render('meals', 'Gerichte', ['meals' => list_meals($db)], 'meals');
}

/** Optional ?name= and ?cycle_day=&meal_time= (from the plan editor: the slot to fill after saving). */
function new_meal_page(): never
{
    try {
        $name = clean_text($_GET['name'] ?? '');
    } catch (UserError) {
        $name = '';
    }
    render_meal_form(['name' => $name], null, slot_param($_GET));
}

function new_meal_action(PDO $db): never
{
    $slot = slot_param($_POST);
    $meal = meal_form_input();
    try {
        $meal['name'] = clean_text($meal['name']);
        $id = create_meal($db, $meal);
    } catch (UserError $e) {
        render_meal_form($meal, null, $slot, 0, $e->getMessage());
    }
    if ($slot !== null) {
        [$cycleDay, $mealTime] = $slot;
        save_slot($db, $cycleDay, $mealTime, $id, null);
        set_flash('Gespeichert.', false, "slot-$cycleDay-$mealTime");
        redirect("/plan#slot-$cycleDay-$mealTime");
    }
    set_flash('Gespeichert.', false, "meal-$id");
    redirect("/meals#meal-$id");
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
        delete_meal($db, $id);
        set_flash('Gericht gelöscht.', false);
        redirect('/meals');
    } catch (UserError $e) {
        set_flash($e->getMessage(), true);
        redirect("/meals/edit?id=$id");
    }
}

/** Errors are shown in the form with the entered values (no redirect, see docs/decisions.md D-22). */
function render_meal_form(array $meal, ?int $id, ?array $slot, int $uses = 0, ?string $error = null): never
{
    render('meal', $id === null ? 'Neues Gericht' : 'Gericht bearbeiten',
        ['meal' => $meal, 'id' => $id, 'slot' => $slot, 'uses' => $uses, 'error' => $error],
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

function logout_action(): never
{
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', ['expires' => 1] + cookie_options());
    redirect('/login');
}
