<?php

declare(strict_types=1);

function day_page(PDO $db, array $config): never
{
    $date = date_param('/');
    $today = today();
    $cycleDay = cycle_day($date, parse_date($config['cycle_start_date']));
    render('day', format_long_date($date), [
        'date' => $date,
        'isToday' => $date == $today,
        'cycleWeek' => cycle_week($cycleDay),
        'slots' => load_plan($db)[$cycleDay] ?? [],
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
        $days[] = ['date' => $date, 'slots' => $plan[cycle_day($date, $start)] ?? []];
    }
    render('week', 'Woche', [
        'days' => $days,
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
    $cycleDay = filter_var($_POST['cycle_day'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => CYCLE_DAYS]]);
    $mealTime = $_POST['meal_time'] ?? null;
    $type = $_POST['type'] ?? null;
    if ($cycleDay === false || !in_array($mealTime, array_keys(MEAL_TIMES), true)
        || !in_array($type, ['meal', 'note'], true)) {
        render_error(400, 'Ungültige Anfrage.');
    }
    $target = "slot-$cycleDay-$mealTime";
    try {
        save_slot($db, $cycleDay, $mealTime, $type, clean_text($_POST['text'] ?? null));
        set_flash('Gespeichert.', false, $target);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true, $target);
    }
    redirect("/plan#$target");
}

function meals_page(PDO $db): never
{
    render('meals', 'Gerichte', ['meals' => list_meals($db)], 'meals');
}

function rename_meal_action(PDO $db): never
{
    $id = meal_id_param();
    $target = "meal-$id";
    try {
        rename_meal($db, $id, clean_text($_POST['name'] ?? null));
        set_flash('Gespeichert.', false, $target);
    } catch (UserError $e) {
        set_flash($e->getMessage(), true, $target);
    }
    redirect("/meals#$target");
}

function delete_meal_action(PDO $db): never
{
    $id = meal_id_param();
    try {
        delete_meal($db, $id);
        set_flash('Gericht gelöscht.', false);
        redirect('/meals');
    } catch (UserError $e) {
        set_flash($e->getMessage(), true, "meal-$id");
        redirect("/meals#meal-$id");
    }
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
