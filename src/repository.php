<?php

declare(strict_types=1);

const LOGIN_MAX_FAILURES = 5;
const LOGIN_WINDOW_SECONDS = 15 * 60;
const SQLSTATE_CONSTRAINT = '23000';

/** Case-insensitive key that enforces unique meal names (SQLite NOCASE ignores umlauts). */
function meal_name_key(string $name): string
{
    return mb_strtolower($name);
}

/**
 * @return array<int, array<string, array{meal: ?string, note: ?string, meal_id: ?int, prep_time: ?string,
 *     vegetable_level: ?string, leftovers: ?string}>> [cycle_day][meal_time]
 */
function load_plan(PDO $db): array
{
    $plan = [];
    $rows = $db->query('SELECT s.cycle_day, s.meal_time, m.name AS meal, s.note, s.meal_id,
            m.prep_time, m.vegetable_level, m.leftovers
        FROM plan_slot s LEFT JOIN meal m ON m.id = s.meal_id');
    foreach ($rows as $row) {
        $plan[$row['cycle_day']][$row['meal_time']] = array_diff_key($row, ['cycle_day' => 0, 'meal_time' => 0]);
    }
    return $plan;
}

/** Exactly one of $mealId / $note, or neither to empty the slot. */
function save_slot(PDO $db, int $cycleDay, string $mealTime, ?int $mealId, ?string $note): void
{
    if ($mealId === null && $note === null) {
        $db->prepare('DELETE FROM plan_slot WHERE cycle_day = ? AND meal_time = ?')->execute([$cycleDay, $mealTime]);
        return;
    }
    $db->prepare('INSERT INTO plan_slot (cycle_day, meal_time, meal_id, note) VALUES (?, ?, ?, ?)
        ON CONFLICT (cycle_day, meal_time) DO UPDATE SET meal_id = excluded.meal_id, note = excluded.note')
        ->execute([$cycleDay, $mealTime, $mealId, $note]);
}

function find_meal_id(PDO $db, string $name): ?int
{
    $stmt = $db->prepare('SELECT id FROM meal WHERE name_key = ?');
    $stmt->execute([meal_name_key($name)]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

/** @return ?array{id: int, name: string, uses: int, ...} the meal with all tags */
function find_meal(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT m.id, m.name, m.protein_source, m.heaviness, m.prep_time, m.child_factor,
            m.shelf_life, m.leftovers, m.vegetable_level,
            (SELECT COUNT(*) FROM plan_slot s WHERE s.meal_id = m.id) AS uses
        FROM meal m WHERE m.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** $meal: name and one value per tag in MEAL_TAGS. Returns the new ID. */
function create_meal(PDO $db, array $meal): int
{
    execute_meal_write($db, 'INSERT INTO meal (name, name_key, protein_source, heaviness, prep_time, child_factor,
            shelf_life, leftovers, vegetable_level)
        VALUES (:name, :name_key, :protein_source, :heaviness, :prep_time, :child_factor,
            :shelf_life, :leftovers, :vegetable_level)', $meal);
    return (int)$db->lastInsertId();
}

/** $meal: see create_meal(). */
function update_meal(PDO $db, int $id, array $meal): void
{
    $stmt = execute_meal_write($db, 'UPDATE meal SET name = :name, name_key = :name_key,
            protein_source = :protein_source, heaviness = :heaviness, prep_time = :prep_time,
            child_factor = :child_factor, shelf_life = :shelf_life, leftovers = :leftovers,
            vegetable_level = :vegetable_level
        WHERE id = :id', $meal, ['id' => $id]);
    if ($stmt->rowCount() === 0) {
        throw new UserError('Dieses Gericht gibt es nicht mehr.');
    }
}

function execute_meal_write(PDO $db, string $sql, array $meal, array $params = []): PDOStatement
{
    if (($meal['name'] ?? '') === '') {
        throw new UserError('Gib einen Namen ein.');
    }
    foreach (MEAL_TAGS as $tag => $definition) {
        if (!is_string($meal[$tag] ?? null) || !isset($definition['values'][$meal[$tag]])) {
            throw new UserError('Wähle bei jeder Eigenschaft einen Wert.');
        }
        $params[$tag] = $meal[$tag];
    }
    $stmt = $db->prepare($sql);
    try {
        $stmt->execute($params + ['name' => $meal['name'], 'name_key' => meal_name_key($meal['name'])]);
    } catch (PDOException $e) {
        if ($e->getCode() === SQLSTATE_CONSTRAINT) {
            throw new UserError('Es gibt bereits ein Gericht mit diesem Namen.');
        }
        throw $e;
    }
    return $stmt;
}

/** @return list<array{id: int, name: string, uses: int}> sorted by name */
function list_meals(PDO $db): array
{
    $meals = $db->query('SELECT m.id, m.name, COUNT(s.cycle_day) AS uses
        FROM meal m LEFT JOIN plan_slot s ON s.meal_id = m.id GROUP BY m.id')->fetchAll();
    usort($meals, fn(array $a, array $b): int => strcmp(sort_key($a['name']), sort_key($b['name'])));
    return $meals;
}

/** Sorts accented letters like their base letter (no intl extension needed). */
function sort_key(string $name): string
{
    return strtr(mb_strtolower($name), [
        'ä' => 'a', 'à' => 'a', 'â' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'ö' => 'o', 'ô' => 'o', 'ü' => 'u', 'ù' => 'u', 'û' => 'u',
    ]);
}

function delete_meal(PDO $db, int $id): void
{
    try {
        $db->prepare('DELETE FROM meal WHERE id = ?')->execute([$id]);
    } catch (PDOException $e) {
        if ($e->getCode() === SQLSTATE_CONSTRAINT) {
            throw new UserError('Das Gericht ist im Plan eingetragen und kann nicht gelöscht werden.');
        }
        throw $e;
    }
}

function login_blocked(PDO $db, string $ip, int $now): bool
{
    $db->prepare('DELETE FROM login_failure WHERE failed_at <= ?')->execute([$now - LOGIN_WINDOW_SECONDS]);
    $stmt = $db->prepare('SELECT COUNT(*) FROM login_failure WHERE ip = ?');
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() >= LOGIN_MAX_FAILURES;
}

function record_login_failure(PDO $db, string $ip, int $now): void
{
    $db->prepare('INSERT INTO login_failure (ip, failed_at) VALUES (?, ?)')->execute([$ip, $now]);
}

function clear_login_failures(PDO $db, string $ip): void
{
    $db->prepare('DELETE FROM login_failure WHERE ip = ?')->execute([$ip]);
}
