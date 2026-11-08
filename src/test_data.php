<?php

declare(strict_types=1);

// Test plan, loaded into an empty database when config.php has 'load_test_data' => true.

// [name => tag values in the order of MEAL_TAGS]
const TEST_MEALS = [
    'Älplermagronen mit Apfelmus' => ['dairy_cheese', 'heavy', 'medium', 'high_flyer', 'long', 'reheats_well', 'raw_veggies_needed'],
    'Birchermüesli' => ['dairy_cheese', 'light', 'quick', 'high_flyer', 'short', 'eat_fresh', 'main_component'],
    'Chili sin Carne' => ['legumes', 'medium', 'medium', 'acquired_taste', 'long', 'reheats_well', 'main_component'],
    'Crêpes' => ['egg', 'medium', 'medium', 'high_flyer', 'long', 'eat_fresh', 'raw_veggies_needed'],
    'Fischstäbchen mit Kartoffeln und Rüebli' => ['fish', 'medium', 'quick', 'high_flyer', 'long', 'eat_fresh', 'main_component'],
    'Gemüse-Wraps' => ['vegan_tofu', 'light', 'quick', 'deconstructed', 'medium', 'eat_fresh', 'main_component'],
    'Gemüsesuppe mit Brot' => ['none', 'light', 'medium', 'high_flyer', 'medium', 'reheats_well', 'main_component'],
    'Gschwellti mit Käse' => ['dairy_cheese', 'medium', 'medium', 'deconstructed', 'long', 'eat_fresh', 'raw_veggies_needed'],
    'Hörnli mit Ghackets' => ['meat', 'medium', 'quick', 'high_flyer', 'short', 'reheats_well', 'side_dish'],
    'Käsefondue' => ['dairy_cheese', 'heavy', 'quick', 'high_flyer', 'long', 'eat_fresh', 'raw_veggies_needed'],
    'Kürbisrisotto' => ['dairy_cheese', 'medium', 'slow', 'acquired_taste', 'long', 'eat_fresh', 'main_component'],
    'Lachs mit Ofengemüse' => ['fish', 'light', 'medium', 'acquired_taste', 'short', 'eat_fresh', 'main_component'],
    'Linsencurry mit Reis' => ['legumes', 'medium', 'medium', 'acquired_taste', 'long', 'reheats_well', 'main_component'],
    'Minestrone' => ['legumes', 'light', 'slow', 'high_flyer', 'medium', 'reheats_well', 'main_component'],
    'Omelette mit Salat' => ['egg', 'light', 'quick', 'high_flyer', 'short', 'eat_fresh', 'main_component'],
    'Pizza' => ['dairy_cheese', 'heavy', 'slow', 'high_flyer', 'medium', 'eat_fresh', 'raw_veggies_needed'],
    'Poulet-Geschnetzeltes mit Reis' => ['poultry', 'medium', 'medium', 'high_flyer', 'medium', 'reheats_well', 'side_dish'],
    'Raclette' => ['dairy_cheese', 'heavy', 'quick', 'deconstructed', 'medium', 'eat_fresh', 'raw_veggies_needed'],
    'Rösti mit Spiegelei' => ['egg', 'heavy', 'medium', 'high_flyer', 'long', 'eat_fresh', 'side_dish'],
    'Salat mit Ei' => ['egg', 'light', 'quick', 'deconstructed', 'short', 'eat_fresh', 'main_component'],
    'Spaghetti Bolognese' => ['meat', 'medium', 'medium', 'high_flyer', 'short', 'reheats_well', 'raw_veggies_needed'],
    'Zopf-Brunch' => ['dairy_cheese', 'medium', 'quick', 'deconstructed', 'short', 'eat_fresh', 'raw_veggies_needed'],
    'Zürcher Geschnetzeltes mit Rösti' => ['meat', 'heavy', 'medium', 'high_flyer', 'short', 'eat_fresh', 'side_dish'],
];

// [cycle_day => [lunch, dinner]]; a string is a meal from TEST_MEALS, ['note' => ...] a note, null an empty slot.
const TEST_PLAN = [
    1 => ['Älplermagronen mit Apfelmus', 'Gemüsesuppe mit Brot'],
    2 => ['Spaghetti Bolognese', 'Salat mit Ei'],
    3 => [['note' => 'Eltern kochen'], 'Rösti mit Spiegelei'],
    4 => ['Linsencurry mit Reis', 'Birchermüesli'],
    5 => ['Fischstäbchen mit Kartoffeln und Rüebli', 'Pizza'],
    6 => ['Hörnli mit Ghackets', 'Raclette'],
    7 => ['Zopf-Brunch', null],
    8 => ['Gschwellti mit Käse', 'Minestrone'],
    9 => ['Minestrone', 'Poulet-Geschnetzeltes mit Reis'],
    10 => ['Spaghetti Bolognese', 'Omelette mit Salat'],
    11 => ['Kürbisrisotto', 'Gemüsesuppe mit Brot'],
    12 => ['Lachs mit Ofengemüse', 'Pizza'],
    13 => ['Käsefondue', ['note' => 'Auswärts essen']],
    14 => ['Zürcher Geschnetzeltes mit Rösti', 'Birchermüesli'],
];

/** Loads the test plan if the database has no meals and no slots. Returns whether it loaded. */
function load_test_data_if_empty(PDO $db): bool
{
    $rows = (int)$db->query('SELECT (SELECT COUNT(*) FROM meal) + (SELECT COUNT(*) FROM plan_slot)')->fetchColumn();
    if ($rows > 0) {
        return false;
    }
    $db->beginTransaction();
    $ids = [];
    foreach (TEST_MEALS as $name => $tags) {
        $ids[$name] = create_meal($db, ['name' => $name] + array_combine(array_keys(MEAL_TAGS), $tags));
    }
    foreach (TEST_PLAN as $cycleDay => $slots) {
        foreach (array_combine(array_keys(MEAL_TIMES), $slots) as $mealTime => $slot) {
            if ($slot !== null) {
                save_slot($db, $cycleDay, $mealTime, is_string($slot) ? $ids[$slot] : null, $slot['note'] ?? null);
            }
        }
    }
    $db->commit();
    return true;
}
