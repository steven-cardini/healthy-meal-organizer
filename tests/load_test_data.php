<?php

declare(strict_types=1);

// Fills an empty database with a full 14-day test plan. Usage: php tests/load_test_data.php

require __DIR__ . '/../src/app.php';

// [cycle_day => [lunch, dinner]]; a string is a meal, ['note' => ...] a note, null an empty slot.
const TEST_PLAN = [
    1 => ['Älplermagronen mit Apfelmus', 'Gemüsesuppe mit Brot'],
    2 => ['Spaghetti Bolognese', 'Salat mit Ei'],
    3 => [['note' => 'Eltern kochen'], 'Rösti mit Spiegelei'],
    4 => ['Linsencurry mit Reis', 'Birchermüesli'],
    5 => ['Fischstäbchen mit Kartoffeln und Rüebli', 'Pizza'],
    6 => ['Hörnli mit Ghackets', 'Raclette'],
    7 => ['Zopf-Brunch', null],
    8 => ['Gschwellti mit Käse', 'Minestrone'],
    9 => ['Poulet-Geschnetzeltes mit Reis', 'Gemüse-Wraps'],
    10 => ['Spaghetti Bolognese', 'Omelette mit Salat'],
    11 => ['Kürbisrisotto', 'Gemüsesuppe mit Brot'],
    12 => ['Lachs mit Ofengemüse', 'Pizza'],
    13 => ['Käsefondue', ['note' => 'Auswärts essen']],
    14 => ['Zürcher Geschnetzeltes mit Rösti', 'Birchermüesli'],
];
const UNUSED_MEALS = ['Chili sin Carne', 'Crêpes'];

$config = load_config(getenv('MENUPLAN_CONFIG') ?: ROOT_DIR . '/config.php');
$db = open_db($config['data_dir']);
if ((int)$db->query('SELECT COUNT(*) FROM meal')->fetchColumn() > 0) {
    fwrite(STDERR, "The database already contains meals. Delete {$config['data_dir']}/db.sqlite first.\n");
    exit(1);
}
foreach (TEST_PLAN as $cycleDay => $slots) {
    foreach (array_combine(array_keys(MEAL_TIMES), $slots) as $mealTime => $slot) {
        if ($slot !== null) {
            $type = is_array($slot) ? 'note' : 'meal';
            save_slot($db, $cycleDay, $mealTime, $type, is_array($slot) ? $slot['note'] : $slot);
        }
    }
}
foreach (UNUSED_MEALS as $name) {
    find_or_create_meal($db, $name);
}
echo "Test data loaded.\n";
