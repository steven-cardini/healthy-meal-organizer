<?php

declare(strict_types=1);

// Dates are calendar days at midnight UTC, so day arithmetic ignores DST. Only "today" uses Europe/Zurich.

const TIME_ZONE = 'Europe/Zurich';
const CYCLE_DAYS = 14;
/** Days the uncooked ingredients keep after delivery: upper end of the shelf_life range; long: all week. */
const SHELF_LIFE_DAYS = ['short' => 2, 'medium' => 5];
const WEEKDAYS = [1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
const MONTHS = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

function parse_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
}

function today(): DateTimeImmutable
{
    $local = new DateTimeImmutable('now', new DateTimeZone(TIME_ZONE));
    return new DateTimeImmutable($local->format('Y-m-d'), new DateTimeZone('UTC'));
}

function add_days(DateTimeImmutable $date, int $days): DateTimeImmutable
{
    return $date->modify(sprintf('%+d days', $days));
}

function monday_of(DateTimeImmutable $date): DateTimeImmutable
{
    return add_days($date, 1 - (int)$date->format('N'));
}

/** 1–14, see docs/architecture.md#cycle-calculation. */
function cycle_day(DateTimeImmutable $date, DateTimeImmutable $cycleStart): int
{
    $days = intdiv($date->getTimestamp() - $cycleStart->getTimestamp(), 86400);
    return (($days % CYCLE_DAYS) + CYCLE_DAYS) % CYCLE_DAYS + 1;
}

function cycle_week(int $cycleDay): int
{
    return $cycleDay <= 7 ? 1 : 2;
}

/** @return array{DateTimeImmutable, string} [date, meal_time] of the slot before. */
function previous_slot(DateTimeImmutable $date, string $mealTime): array
{
    return $mealTime === 'dinner' ? [$date, 'lunch'] : [add_days($date, -1), 'dinner'];
}

/** @return array{DateTimeImmutable, string} [date, meal_time] of the slot after. */
function next_slot(DateTimeImmutable $date, string $mealTime): array
{
    return $mealTime === 'lunch' ? [$date, 'dinner'] : [add_days($date, 1), 'lunch'];
}

/** Saturday on which the planning week of $date starts (docs/requirements.md#swap). */
function planning_week_start(DateTimeImmutable $date): DateTimeImmutable
{
    return add_days($date, -(((int)$date->format('N') + 1) % 7));
}

/** Whether a slot on $date can be swapped: today until Friday of the current planning week. */
function is_swappable(DateTimeImmutable $date): bool
{
    return $date >= today() && $date <= add_days(planning_week_start(today()), 6);
}

/** Last day the uncooked ingredients keep, counted from the start of the planning week; null: all week (FR-20). */
function fresh_until(string $shelfLife, DateTimeImmutable $planningWeekStart): ?DateTimeImmutable
{
    return isset(SHELF_LIFE_DAYS[$shelfLife]) ? add_days($planningWeekStart, SHELF_LIFE_DAYS[$shelfLife]) : null;
}

/** e.g. "Samstag, 7. November 2026" */
function format_long_date(DateTimeImmutable $date): string
{
    return weekday($date) . ', ' . format_date($date);
}

/** e.g. "Samstag, 7. November" */
function format_short_date(DateTimeImmutable $date): string
{
    return weekday($date) . ', ' . $date->format('j') . '. ' . MONTHS[(int)$date->format('n')];
}

/** e.g. "7. November 2026" */
function format_date(DateTimeImmutable $date): string
{
    return $date->format('j') . '. ' . MONTHS[(int)$date->format('n')] . ' ' . $date->format('Y');
}

/** e.g. "Samstag" */
function weekday(DateTimeImmutable $date): string
{
    return WEEKDAYS[(int)$date->format('N')];
}
