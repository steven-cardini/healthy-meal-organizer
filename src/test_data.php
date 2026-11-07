<?php

declare(strict_types=1);

// Test plan, loaded into an empty database when config.php has 'load_test_data' => true.

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
const TEST_UNUSED_MEALS = ['Chili sin Carne', 'Crêpes'];

/** Loads the test plan if the database has no meals and no slots. Returns whether it loaded. */
function load_test_data_if_empty(PDO $db): bool
{
    $rows = (int)$db->query('SELECT (SELECT COUNT(*) FROM meal) + (SELECT COUNT(*) FROM plan_slot)')->fetchColumn();
    if ($rows > 0) {
        return false;
    }
    foreach (TEST_PLAN as $cycleDay => $slots) {
        foreach (array_combine(array_keys(MEAL_TIMES), $slots) as $mealTime => $slot) {
            if ($slot !== null) {
                save_slot($db, $cycleDay, $mealTime, is_array($slot) ? 'note' : 'meal', is_array($slot) ? $slot['note'] : $slot);
            }
        }
    }
    foreach (TEST_UNUSED_MEALS as $name) {
        find_or_create_meal($db, $name);
    }
    return true;
}
