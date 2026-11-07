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

/** @return array<int, array<string, array{meal: ?string, note: ?string}>> [cycle_day][meal_time] */
function load_plan(PDO $db): array
{
    $plan = [];
    $rows = $db->query('SELECT s.cycle_day, s.meal_time, m.name AS meal, s.note
        FROM plan_slot s LEFT JOIN meal m ON m.id = s.meal_id');
    foreach ($rows as $row) {
        $plan[$row['cycle_day']][$row['meal_time']] = ['meal' => $row['meal'], 'note' => $row['note']];
    }
    return $plan;
}

/** $type: 'meal' or 'note'. An empty $text empties the slot. */
function save_slot(PDO $db, int $cycleDay, string $mealTime, string $type, string $text): void
{
    $db->beginTransaction();
    try {
        if ($text === '') {
            $db->prepare('DELETE FROM plan_slot WHERE cycle_day = ? AND meal_time = ?')
                ->execute([$cycleDay, $mealTime]);
        } else {
            $mealId = $type === 'meal' ? find_or_create_meal($db, $text) : null;
            $db->prepare('INSERT INTO plan_slot (cycle_day, meal_time, meal_id, note) VALUES (?, ?, ?, ?)
                ON CONFLICT (cycle_day, meal_time) DO UPDATE SET meal_id = excluded.meal_id, note = excluded.note')
                ->execute([$cycleDay, $mealTime, $mealId, $mealId === null ? $text : null]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function find_or_create_meal(PDO $db, string $name): int
{
    $db->prepare('INSERT INTO meal (name, name_key) VALUES (?, ?) ON CONFLICT (name_key) DO NOTHING')
        ->execute([$name, meal_name_key($name)]);
    $stmt = $db->prepare('SELECT id FROM meal WHERE name_key = ?');
    $stmt->execute([meal_name_key($name)]);
    return (int)$stmt->fetchColumn();
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

function rename_meal(PDO $db, int $id, string $name): void
{
    if ($name === '') {
        throw new UserError('Gib einen Namen ein.');
    }
    try {
        $db->prepare('UPDATE meal SET name = ?, name_key = ? WHERE id = ?')
            ->execute([$name, meal_name_key($name), $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === SQLSTATE_CONSTRAINT) {
            throw new UserError('Es gibt bereits ein Gericht mit diesem Namen.');
        }
        throw $e;
    }
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
