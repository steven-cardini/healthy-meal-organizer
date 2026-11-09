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

/** Columns of an entry: what a slot holds. The tags are null if there is no meal. */
const ENTRY_COLUMNS = 's.meal_id, s.note, m.name AS meal, m.protein_source, m.prep_time, m.child_factor,
    m.shelf_life, m.leftovers, m.vegetable_level';
const EMPTY_ENTRY = ['meal_id' => null, 'note' => null, 'meal' => null];

/** @return array<int, array<string, array{meal_id: ?int, note: ?string, meal: ?string, ...}>> [cycle_day][meal_time] */
function load_plan(PDO $db): array
{
    $plan = [];
    $rows = $db->query('SELECT s.cycle_day, s.meal_time, ' . ENTRY_COLUMNS
        . ' FROM plan_slot s LEFT JOIN meal m ON m.id = s.meal_id');
    foreach ($rows as $row) {
        $plan[$row['cycle_day']][$row['meal_time']] = array_diff_key($row, ['cycle_day' => 0, 'meal_time' => 0]);
    }
    return $plan;
}

/**
 * What is cooked from $from to $to: the swap of a date if there is one, otherwise the plan slot.
 * moved: the meal was moved here; moved_away: the meal was moved to another slot, moved_to: to which one, while
 * that slot still holds it (FR-16).
 * @return array<string, array<string, array{cycle_day: int, swapped: bool, moved: bool, moved_away: bool,
 *     moved_to: ?array{DateTimeImmutable, string}, meal_id: ?int, note: ?string, meal: ?string, ...}>> [Y-m-d][meal_time]
 */
function load_entries(PDO $db, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $cycleStart): array
{
    $stmt = $db->prepare('SELECT s.date, s.meal_time, s.partner_date, s.partner_meal_time, s.moved,
            p.partner_date = s.date AND p.partner_meal_time = s.meal_time AS partner_linked, ' . ENTRY_COLUMNS
        . ' FROM swap s LEFT JOIN meal m ON m.id = s.meal_id
            LEFT JOIN swap p ON p.date = s.partner_date AND p.meal_time = s.partner_meal_time
            WHERE s.date BETWEEN ? AND ?');
    $stmt->execute([$from->format('Y-m-d'), $to->format('Y-m-d')]);
    $swaps = [];
    foreach ($stmt as $row) {
        $movedAway = $row['meal_id'] === null && $row['partner_date'] !== null;
        $swaps[$row['date']][$row['meal_time']] = [
            'moved' => $row['moved'] === 1,
            'moved_away' => $movedAway,
            'moved_to' => $movedAway && $row['partner_linked'] === 1
                ? [parse_date($row['partner_date']), $row['partner_meal_time']] : null,
        ] + array_diff_key($row, array_flip(['date', 'meal_time', 'partner_date', 'partner_meal_time', 'partner_linked']));
    }
    $plan = load_plan($db);
    $entries = [];
    for ($date = $from; $date <= $to; $date = add_days($date, 1)) {
        $key = $date->format('Y-m-d');
        $cycleDay = cycle_day($date, $cycleStart);
        foreach (array_keys(MEAL_TIMES) as $mealTime) {
            $swap = $swaps[$key][$mealTime] ?? null;
            $entries[$key][$mealTime] = ['cycle_day' => $cycleDay, 'swapped' => $swap !== null]
                + ($swap ?? ['moved' => false, 'moved_away' => false, 'moved_to' => null]
                    + ($plan[$cycleDay][$mealTime] ?? EMPTY_ENTRY));
        }
    }
    return $entries;
}

/**
 * Sets the slot of a date to a meal, a note, or neither (skipped).
 * @param ?array{DateTimeImmutable, string} $partner [date, meal_time] of the other slot of an exchange or a move
 * @param bool $moved the meal was moved here from $partner
 */
function save_swap(PDO $db, DateTimeImmutable $date, string $mealTime, ?int $mealId, ?string $note,
    ?array $partner = null, bool $moved = false): void
{
    try {
        $db->prepare('INSERT INTO swap (date, meal_time, meal_id, note, partner_date, partner_meal_time, moved)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (date, meal_time) DO UPDATE SET meal_id = excluded.meal_id, note = excluded.note,
                partner_date = excluded.partner_date, partner_meal_time = excluded.partner_meal_time,
                moved = excluded.moved')
            ->execute([$date->format('Y-m-d'), $mealTime, $mealId, $note,
                $partner === null ? null : $partner[0]->format('Y-m-d'), $partner[1] ?? null, (int)$moved]);
    } catch (PDOException $e) {
        if ($e->getCode() === SQLSTATE_CONSTRAINT) {
            throw new UserError('Dieses Gericht gibt es nicht mehr.');
        }
        throw $e;
    }
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

/** Exchanges the meals of two slots. $a, $b: [date, meal_time, meal_id]. */
function exchange_meals(PDO $db, array $a, array $b): void
{
    in_transaction($db, function () use ($db, $a, $b): void {
        save_swap($db, $a[0], $a[1], $b[2], null, [$b[0], $b[1]]);
        save_swap($db, $b[0], $b[1], $a[2], null, [$a[0], $a[1]]);
    });
}

/** Moves the meal of $from to $to; $from is then empty. $to: [date, meal_time], $from: [date, meal_time, meal_id]. */
function move_meal(PDO $db, array $to, array $from): void
{
    in_transaction($db, function () use ($db, $to, $from): void {
        save_swap($db, $to[0], $to[1], $from[2], null, [$from[0], $from[1]], true);
        save_swap($db, $from[0], $from[1], null, null, [$to[0], $to[1]]);
    });
}

/**
 * The swapped slots that *Zurücksetzen* of a slot restores: the slot and every slot linked to it by an exchange or a move
 * (in either direction), directly or via other linked slots, except past ones (FR-19). Sorted by time.
 * @return list<array{DateTimeImmutable, string}> [date, meal_time]
 */
function reset_slots(PDO $db, DateTimeImmutable $date, string $mealTime): array
{
    // Exchanges and moves stay within one planning week.
    $start = planning_week_start($date);
    $stmt = $db->prepare('SELECT date, meal_time, partner_date, partner_meal_time FROM swap WHERE date BETWEEN ? AND ?');
    $stmt->execute([$start->format('Y-m-d'), add_days($start, 6)->format('Y-m-d')]);
    $partners = [];
    foreach ($stmt as $row) {
        $partners["$row[date] $row[meal_time]"] = $row['partner_date'] === null ? null : "$row[partner_date] $row[partner_meal_time]";
    }
    $reset = [];
    $slot = $date->format('Y-m-d') . " $mealTime";
    $queue = array_key_exists($slot, $partners) ? [$slot] : [];
    while ($queue !== []) {
        $slot = array_pop($queue);
        $reset[$slot] = true;
        foreach ($partners as $other => $partner) {
            if (($partner === $slot || $other === $partners[$slot]) && !isset($reset[$other])
                && !is_past(parse_date(substr($other, 0, 10)), substr($other, 11))) {
                $queue[] = $other;
            }
        }
    }
    $slots = array_map(fn(string $slot): array => [parse_date(substr($slot, 0, 10)), substr($slot, 11)], array_keys($reset));
    usort($slots, fn(array $a, array $b): int => [$a[0], $a[1] === 'dinner'] <=> [$b[0], $b[1] === 'dinner']);
    return $slots;
}

/** Restores the plan for the slots of reset_slots(). */
function reset_swap(PDO $db, DateTimeImmutable $date, string $mealTime): void
{
    in_transaction($db, function () use ($db, $date, $mealTime): void {
        $delete = $db->prepare('DELETE FROM swap WHERE date = ? AND meal_time = ?');
        foreach (reset_slots($db, $date, $mealTime) as [$slotDate, $slotMealTime]) {
            $delete->execute([$slotDate->format('Y-m-d'), $slotMealTime]);
        }
    });
}

function in_transaction(PDO $db, callable $fn): void
{
    $db->beginTransaction();
    try {
        $fn();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
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

/** @return list<array{id: int, name: string, protein_source: string, prep_time: string, child_factor: string, uses: int}> sorted by name */
function list_meals(PDO $db): array
{
    $meals = $db->query('SELECT m.id, m.name, m.protein_source, m.prep_time, m.child_factor, COUNT(s.cycle_day) AS uses
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

/** Also deletes the meal's swaps before $today; swaps from $today on block the deletion. */
function delete_meal(PDO $db, int $id, DateTimeImmutable $today): void
{
    in_transaction($db, function () use ($db, $id, $today): void {
        $stmt = $db->prepare('SELECT COUNT(*) FROM swap WHERE meal_id = ? AND date >= ?');
        $stmt->execute([$id, $today->format('Y-m-d')]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new UserError('Das Gericht ist diese Woche eingetauscht und kann erst danach gelöscht werden.');
        }
        $db->prepare('DELETE FROM swap WHERE meal_id = ?')->execute([$id]);
        try {
            $db->prepare('DELETE FROM meal WHERE id = ?')->execute([$id]);
        } catch (PDOException $e) {
            if ($e->getCode() === SQLSTATE_CONSTRAINT) {
                throw new UserError('Das Gericht ist im Plan eingetragen und kann nicht gelöscht werden.');
            }
            throw $e;
        }
    });
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
