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
const UNCHANGED = ['changed' => false, 'moved' => false, 'moved_away' => false, 'moved_to' => null];

/** @return array<int, array<int, array<string, array{meal_id: ?int, note: ?string, meal: ?string, ...}>>> [blueprint][day][meal_time] */
function load_blueprints(PDO $db): array
{
    $blueprints = [];
    $rows = $db->query('SELECT s.blueprint, s.day, s.meal_time, ' . ENTRY_COLUMNS
        . ' FROM blueprint_slot s LEFT JOIN meal m ON m.id = s.meal_id');
    foreach ($rows as $row) {
        $blueprints[$row['blueprint']][$row['day']][$row['meal_time']]
            = array_diff_key($row, array_flip(['blueprint', 'day', 'meal_time']));
    }
    return $blueprints;
}

/**
 * The week starting on $start: its week blueprint and whether it is planned. A week without a row takes the other week
 * blueprint than the week before, counted from the nearest earlier week with a row, or the nearest later one (FR-3).
 * @return array{blueprint: int, planned: bool}
 */
function load_week(PDO $db, DateTimeImmutable $start): array
{
    $key = $start->format('Y-m-d');
    $row = false;
    foreach ([
        'SELECT start_date, blueprint, planned FROM week WHERE start_date <= ? ORDER BY start_date DESC LIMIT 1',
        'SELECT start_date, blueprint, planned FROM week WHERE start_date > ? ORDER BY start_date LIMIT 1',
    ] as $sql) {
        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row !== false) {
            break;
        }
    }
    if ($row === false) {
        return ['blueprint' => 1, 'planned' => false];
    }
    return [
        'blueprint' => weeks_between(parse_date($row['start_date']), $start) % 2 === 0 ? $row['blueprint'] : 3 - $row['blueprint'],
        'planned' => $row['start_date'] === $key && $row['planned'] === 1,
    ];
}

/**
 * What is cooked from $from to $to: the swap of a date if there is one, otherwise the planned week or the planning of
 * the week, otherwise its week blueprint (docs/architecture.md#planning-and-swap).
 * changed: a swap, or a planning change while the week is not planned; only these are marked and can be reset (FR-23).
 * moved: the meal was moved here; moved_away: the meal was moved to another slot, moved_to: to which one, while
 * that slot still holds it (FR-16).
 * @return array<string, array<string, array{changed: bool, moved: bool, moved_away: bool,
 *     moved_to: ?array{DateTimeImmutable, string}, meal_id: ?int, note: ?string, meal: ?string, ...}>> [Y-m-d][meal_time]
 */
function load_entries(PDO $db, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $stmt = $db->prepare('SELECT s.date, s.meal_time, s.swap, s.partner_date, s.partner_meal_time, s.moved,
            p.partner_date = s.date AND p.partner_meal_time = s.meal_time AS partner_linked, ' . ENTRY_COLUMNS
        . ' FROM date_slot s LEFT JOIN meal m ON m.id = s.meal_id
            LEFT JOIN date_slot p ON p.date = s.partner_date AND p.meal_time = s.partner_meal_time AND p.swap = s.swap
            WHERE s.date BETWEEN ? AND ?');
    $stmt->execute([$from->format('Y-m-d'), $to->format('Y-m-d')]);
    $rows = [];
    foreach ($stmt as $row) {
        $rows[$row['date']][$row['meal_time']][$row['swap']] = $row;
    }
    $blueprints = load_blueprints($db);
    $weeks = [];
    $entries = [];
    for ($date = $from; $date <= $to; $date = add_days($date, 1)) {
        $key = $date->format('Y-m-d');
        $week = $weeks[week_start($date)->format('Y-m-d')] ??= load_week($db, week_start($date));
        foreach (array_keys(MEAL_TIMES) as $mealTime) {
            $row = $rows[$key][$mealTime][1] ?? $rows[$key][$mealTime][0] ?? null;
            if ($row === null) {
                $entries[$key][$mealTime] = UNCHANGED
                    + ($blueprints[$week['blueprint']][week_day($date)][$mealTime] ?? EMPTY_ENTRY);
                continue;
            }
            $changed = $row['swap'] === 1 || !$week['planned'];
            $movedAway = $changed && $row['meal_id'] === null && $row['partner_date'] !== null;
            $entries[$key][$mealTime] = [
                'changed' => $changed,
                'moved' => $changed && $row['moved'] === 1,
                'moved_away' => $movedAway,
                'moved_to' => $movedAway && $row['partner_linked'] === 1
                    ? [parse_date($row['partner_date']), $row['partner_meal_time']] : null,
            ] + array_diff_key($row, array_flip(['date', 'meal_time', 'swap', 'partner_date', 'partner_meal_time', 'partner_linked']));
        }
    }
    return $entries;
}

/**
 * Sets the slot of a date to a meal, a note, or neither (empty, skipped). $swap: a swap (FR-16), otherwise planning (FR-23).
 * @param ?array{DateTimeImmutable, string} $partner [date, meal_time] of the other slot of an exchange or a move
 * @param bool $moved the meal was moved here from $partner
 */
function save_date_slot(PDO $db, bool $swap, DateTimeImmutable $date, string $mealTime, ?int $mealId, ?string $note,
    ?array $partner = null, bool $moved = false): void
{
    try {
        $db->prepare('INSERT INTO date_slot (date, meal_time, swap, meal_id, note, partner_date, partner_meal_time, moved)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (date, meal_time, swap) DO UPDATE SET meal_id = excluded.meal_id, note = excluded.note,
                partner_date = excluded.partner_date, partner_meal_time = excluded.partner_meal_time,
                moved = excluded.moved')
            ->execute([$date->format('Y-m-d'), $mealTime, (int)$swap, $mealId, $note,
                $partner === null ? null : $partner[0]->format('Y-m-d'), $partner[1] ?? null, (int)$moved]);
    } catch (PDOException $e) {
        if ($e->getCode() === SQLSTATE_CONSTRAINT) {
            throw new UserError('Dieses Gericht gibt es nicht mehr.');
        }
        throw $e;
    }
}

/** Exactly one of $mealId / $note, or neither to empty the slot. */
function save_blueprint_slot(PDO $db, int $blueprint, int $day, string $mealTime, ?int $mealId, ?string $note): void
{
    if ($mealId === null && $note === null) {
        $db->prepare('DELETE FROM blueprint_slot WHERE blueprint = ? AND day = ? AND meal_time = ?')
            ->execute([$blueprint, $day, $mealTime]);
        return;
    }
    $db->prepare('INSERT INTO blueprint_slot (blueprint, day, meal_time, meal_id, note) VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (blueprint, day, meal_time) DO UPDATE SET meal_id = excluded.meal_id, note = excluded.note')
        ->execute([$blueprint, $day, $mealTime, $mealId, $note]);
}

/** Exchanges the meals of two slots. $a, $b: [date, meal_time, meal_id]. $swap: see save_date_slot(). */
function exchange_meals(PDO $db, bool $swap, array $a, array $b): void
{
    in_transaction($db, function () use ($db, $swap, $a, $b): void {
        save_date_slot($db, $swap, $a[0], $a[1], $b[2], null, [$b[0], $b[1]]);
        save_date_slot($db, $swap, $b[0], $b[1], $a[2], null, [$a[0], $a[1]]);
    });
}

/** Moves the meal of $from to $to; $from is then empty. $to: [date, meal_time], $from: [date, meal_time, meal_id]. */
function move_meal(PDO $db, bool $swap, array $to, array $from): void
{
    in_transaction($db, function () use ($db, $swap, $to, $from): void {
        save_date_slot($db, $swap, $to[0], $to[1], $from[2], null, [$from[0], $from[1]], true);
        save_date_slot($db, $swap, $from[0], $from[1], null, null, [$to[0], $to[1]]);
    });
}

/**
 * The changed slots that *Zurücksetzen* of a slot restores: the slot and every slot linked to it by an exchange or a move
 * (in either direction), directly or via other linked slots, except past ones (FR-19). Sorted by time.
 * $swap: swaps, otherwise planning changes (see save_date_slot()).
 * @return list<array{DateTimeImmutable, string}> [date, meal_time]
 */
function reset_slots(PDO $db, bool $swap, DateTimeImmutable $date, string $mealTime): array
{
    // Exchanges and moves stay within one week.
    $start = week_start($date);
    $stmt = $db->prepare('SELECT date, meal_time, partner_date, partner_meal_time FROM date_slot
        WHERE swap = ? AND date BETWEEN ? AND ?');
    $stmt->execute([(int)$swap, $start->format('Y-m-d'), add_days($start, 6)->format('Y-m-d')]);
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

/** Restores the planned week (swap) or the week blueprint (planning) for the slots of reset_slots(). */
function reset_date_slot(PDO $db, bool $swap, DateTimeImmutable $date, string $mealTime): void
{
    in_transaction($db, function () use ($db, $swap, $date, $mealTime): void {
        $delete = $db->prepare('DELETE FROM date_slot WHERE date = ? AND meal_time = ? AND swap = ?');
        foreach (reset_slots($db, $swap, $date, $mealTime) as [$slotDate, $slotMealTime]) {
            $delete->execute([$slotDate->format('Y-m-d'), $slotMealTime, (int)$swap]);
        }
    });
}

/** Plans the week starting on $start from the other week blueprint: discards its planning (D-40). False if it is planned. */
function choose_blueprint(PDO $db, DateTimeImmutable $start, int $blueprint): bool
{
    return in_transaction($db, function () use ($db, $start, $blueprint): bool {
        if (load_week($db, $start)['planned']) {
            return false;
        }
        $db->prepare('DELETE FROM date_slot WHERE swap = 0 AND date BETWEEN ? AND ?')
            ->execute([$start->format('Y-m-d'), add_days($start, 6)->format('Y-m-d')]);
        $db->prepare('INSERT INTO week (start_date, blueprint, planned) VALUES (?, ?, 0)
            ON CONFLICT (start_date) DO UPDATE SET blueprint = excluded.blueprint')
            ->execute([$start->format('Y-m-d'), $blueprint]);
        return true;
    });
}

/**
 * Makes the week starting on $start a planned week (FR-24, FR-25): copies every slot of its week blueprint without
 * a planning row, empty ones too, so later changes to the week blueprints do not affect it. False if it is planned.
 */
function complete_week(PDO $db, DateTimeImmutable $start): bool
{
    return in_transaction($db, function () use ($db, $start): bool {
        $week = load_week($db, $start);
        if ($week['planned']) {
            return false;
        }
        $blueprint = load_blueprints($db)[$week['blueprint']] ?? [];
        $insert = $db->prepare('INSERT INTO date_slot (date, meal_time, swap, meal_id, note) VALUES (?, ?, 0, ?, ?)
            ON CONFLICT (date, meal_time, swap) DO NOTHING');
        for ($day = 1; $day <= 7; $day++) {
            foreach (array_keys(MEAL_TIMES) as $mealTime) {
                $slot = $blueprint[$day][$mealTime] ?? EMPTY_ENTRY;
                $insert->execute([add_days($start, $day - 1)->format('Y-m-d'), $mealTime, $slot['meal_id'], $slot['note']]);
            }
        }
        $db->prepare('INSERT INTO week (start_date, blueprint, planned) VALUES (?, ?, 1)
            ON CONFLICT (start_date) DO UPDATE SET planned = 1')
            ->execute([$start->format('Y-m-d'), $week['blueprint']]);
        return true;
    });
}

/**
 * Plans the current week if it is not planned yet (FR-25). The first week of use is Woche 1 and keeps following the
 * week blueprints, as they are usually entered during that week (FR-3).
 */
function plan_current_week(PDO $db): void
{
    $start = week_start(today());
    if (load_week($db, $start)['planned']) {
        return;
    }
    $firstUse = in_transaction($db, function () use ($db, $start): bool {
        if ((int)$db->query('SELECT COUNT(*) FROM week')->fetchColumn() > 0) {
            return false;
        }
        $db->prepare('INSERT INTO week (start_date, blueprint, planned) VALUES (?, 1, 1)')->execute([$start->format('Y-m-d')]);
        return true;
    });
    if (!$firstUse) {
        complete_week($db, $start);
    }
}

/** Runs $fn in a transaction that locks the database for writing at once, so what $fn reads stays valid. */
function in_transaction(PDO $db, callable $fn): mixed
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $fn();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
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
            (SELECT COUNT(*) FROM blueprint_slot s WHERE s.meal_id = m.id) AS uses
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
    $meals = $db->query('SELECT m.id, m.name, m.protein_source, m.prep_time, m.child_factor, COUNT(s.blueprint) AS uses
        FROM meal m LEFT JOIN blueprint_slot s ON s.meal_id = m.id GROUP BY m.id')->fetchAll();
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

/** Also removes the meal from dates before $today; on a date from $today on, it blocks the deletion (FR-21). */
function delete_meal(PDO $db, int $id, DateTimeImmutable $today): void
{
    in_transaction($db, function () use ($db, $id, $today): void {
        $stmt = $db->prepare('SELECT COUNT(*) FROM date_slot WHERE meal_id = ? AND date >= ?');
        $stmt->execute([$id, $today->format('Y-m-d')]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new UserError('Das Gericht ist ab heute eingeplant und kann erst danach gelöscht werden.');
        }
        $db->prepare('DELETE FROM date_slot WHERE meal_id = ? AND swap = 1')->execute([$id]);
        $db->prepare('UPDATE date_slot SET meal_id = NULL, partner_date = NULL, partner_meal_time = NULL, moved = 0
            WHERE meal_id = ?')->execute([$id]);
        try {
            $db->prepare('DELETE FROM meal WHERE id = ?')->execute([$id]);
        } catch (PDOException $e) {
            if ($e->getCode() === SQLSTATE_CONSTRAINT) {
                throw new UserError('Das Gericht ist in den Wochenvorlagen eingetragen und kann nicht gelöscht werden.');
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
