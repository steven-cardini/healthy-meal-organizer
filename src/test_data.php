<?php

declare(strict_types=1);

// Test data, loaded into an empty database when config.php has 'load_test_data' => true.

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

// [category => ingredient names]
const TEST_INGREDIENT_CATEGORIES = [
    'fruit_vegetables' => ['Äpfel', 'Beeren', 'Champignons', 'Gurke', 'Karotten', 'Kartoffeln', 'Knoblauch', 'Kürbis',
        'Lauch', 'Peperoni', 'Salat', 'Sellerie', 'Tomaten', 'Zitronen', 'Zucchetti', 'Zwiebeln'],
    'meat_fish' => ['Hackfleisch', 'Kalbsgeschnetzeltes', 'Lachs', 'Pouletbrust'],
    'dairy_eggs_ready_meals' => ['Butter', 'Eier', 'Fonduemischung', 'Gruyère', 'Joghurt', 'Milch', 'Mozzarella',
        'Pizzateig', 'Rahm', 'Reibkäse', 'Tofu'],
    'bread_pastries' => ['Brot', 'Tortillas'],
    'pasta_condiments_canned' => ['Apfelmus', 'Bouillon', 'Currypaste', 'Haferflocken', 'Hörnli', 'Kidneybohnen',
        'Kokosmilch', 'Linsen', 'Mehl', 'Pelati', 'Reis', 'Risottoreis', 'Spaghetti'],
    'frozen' => ['Fischstäbchen'],
    'wine_beer_spirits' => ['Weisswein'],
];

// [meal => ingredient names]; Raclette and Zopf-Brunch have none (FR-33).
const TEST_MEAL_INGREDIENTS = [
    'Älplermagronen mit Apfelmus' => ['Hörnli', 'Kartoffeln', 'Zwiebeln', 'Rahm', 'Gruyère', 'Apfelmus'],
    'Birchermüesli' => ['Haferflocken', 'Joghurt', 'Milch', 'Äpfel', 'Beeren'],
    'Chili sin Carne' => ['Kidneybohnen', 'Pelati', 'Peperoni', 'Zwiebeln', 'Knoblauch', 'Reis'],
    'Crêpes' => ['Mehl', 'Eier', 'Milch', 'Butter'],
    'Fischstäbchen mit Kartoffeln und Rüebli' => ['Fischstäbchen', 'Kartoffeln', 'Karotten'],
    'Gemüse-Wraps' => ['Tortillas', 'Tofu', 'Peperoni', 'Gurke', 'Salat'],
    'Gemüsesuppe mit Brot' => ['Karotten', 'Lauch', 'Sellerie', 'Kartoffeln', 'Bouillon', 'Brot'],
    'Gschwellti mit Käse' => ['Kartoffeln', 'Gruyère', 'Butter'],
    'Hörnli mit Ghackets' => ['Hörnli', 'Hackfleisch', 'Zwiebeln', 'Apfelmus'],
    'Käsefondue' => ['Fonduemischung', 'Brot', 'Weisswein', 'Knoblauch'],
    'Kürbisrisotto' => ['Risottoreis', 'Kürbis', 'Zwiebeln', 'Bouillon', 'Reibkäse', 'Weisswein'],
    'Lachs mit Ofengemüse' => ['Lachs', 'Zucchetti', 'Peperoni', 'Kartoffeln', 'Zitronen'],
    'Linsencurry mit Reis' => ['Linsen', 'Kokosmilch', 'Currypaste', 'Zwiebeln', 'Reis'],
    'Minestrone' => ['Karotten', 'Lauch', 'Sellerie', 'Zucchetti', 'Pelati', 'Hörnli', 'Bouillon'],
    'Omelette mit Salat' => ['Eier', 'Milch', 'Salat', 'Champignons'],
    'Pizza' => ['Pizzateig', 'Pelati', 'Mozzarella', 'Champignons'],
    'Poulet-Geschnetzeltes mit Reis' => ['Pouletbrust', 'Rahm', 'Champignons', 'Reis'],
    'Rösti mit Spiegelei' => ['Kartoffeln', 'Eier', 'Butter'],
    'Salat mit Ei' => ['Salat', 'Eier', 'Gurke', 'Tomaten'],
    'Spaghetti Bolognese' => ['Spaghetti', 'Hackfleisch', 'Pelati', 'Zwiebeln', 'Karotten', 'Reibkäse'],
    'Zürcher Geschnetzeltes mit Rösti' => ['Kalbsgeschnetzeltes', 'Rahm', 'Champignons', 'Kartoffeln', 'Weisswein'],
];

const TEST_RECIPE_URLS = [
    'Kürbisrisotto' => 'https://example.com/rezepte/kuerbisrisotto',
    'Linsencurry mit Reis' => 'https://example.com/rezepte/linsencurry',
    'Spaghetti Bolognese' => 'https://example.com/rezepte/spaghetti-bolognese',
];

// [blueprint => [day => [lunch, dinner]]], day 1 = Saturday; a string is a meal from TEST_MEALS, ['note' => ...] a note,
// null an empty slot.
const TEST_BLUEPRINTS = [
    1 => [
        1 => ['Hörnli mit Ghackets', 'Raclette'],
        2 => ['Zopf-Brunch', null],
        3 => ['Älplermagronen mit Apfelmus', 'Gemüsesuppe mit Brot'],
        4 => ['Spaghetti Bolognese', 'Salat mit Ei'],
        5 => [['note' => 'Eltern kochen'], 'Rösti mit Spiegelei'],
        6 => ['Linsencurry mit Reis', 'Birchermüesli'],
        7 => ['Fischstäbchen mit Kartoffeln und Rüebli', 'Pizza'],
    ],
    2 => [
        1 => ['Käsefondue', ['note' => 'Auswärts essen']],
        2 => ['Zürcher Geschnetzeltes mit Rösti', 'Birchermüesli'],
        3 => ['Gschwellti mit Käse', 'Minestrone'],
        4 => ['Minestrone', 'Poulet-Geschnetzeltes mit Reis'],
        5 => ['Spaghetti Bolognese', 'Omelette mit Salat'],
        6 => ['Kürbisrisotto', 'Gemüsesuppe mit Brot'],
        7 => ['Lachs mit Ofengemüse', 'Pizza'],
    ],
];

/** Loads the test data if the database has no meals and no blueprint slots. Returns whether it loaded. */
function load_test_data_if_empty(PDO $db): bool
{
    $rows = (int)$db->query('SELECT (SELECT COUNT(*) FROM meal) + (SELECT COUNT(*) FROM blueprint_slot)')->fetchColumn();
    if ($rows > 0) {
        return false;
    }
    $db->beginTransaction();
    $categories = [];
    foreach (TEST_INGREDIENT_CATEGORIES as $category => $names) {
        $categories += array_fill_keys($names, $category);
    }
    $ids = [];
    foreach (TEST_MEALS as $name => $tags) {
        $ingredients = array_map(fn(string $ingredient): array => ['name' => $ingredient, 'category' => $categories[$ingredient]],
            TEST_MEAL_INGREDIENTS[$name] ?? []);
        $ids[$name] = create_meal($db, ['name' => $name, 'recipe_url' => TEST_RECIPE_URLS[$name] ?? '',
            'ingredients' => $ingredients] + array_combine(array_keys(MEAL_TAGS), $tags));
    }
    foreach (TEST_BLUEPRINTS as $blueprint => $days) {
        foreach ($days as $day => $slots) {
            foreach (array_combine(array_keys(MEAL_TIMES), $slots) as $mealTime => $slot) {
                if ($slot !== null) {
                    save_blueprint_slot($db, $blueprint, $day, $mealTime, is_string($slot) ? $ids[$slot] : null, $slot['note'] ?? null);
                }
            }
        }
    }
    $db->commit();
    return true;
}
