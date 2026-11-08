<?php

declare(strict_types=1);

// Dates are calendar days at midnight UTC, so day arithmetic ignores DST. Only "today" uses Europe/Zurich.

const TIME_ZONE = 'Europe/Zurich';
const CYCLE_DAYS = 14;
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

/** @return array{int, string} [cycle_day, meal_time] of the slot before; wraps from day 1 to day 14. */
function previous_slot(int $cycleDay, string $mealTime): array
{
    return $mealTime === 'dinner' ? [$cycleDay, 'lunch'] : [($cycleDay + CYCLE_DAYS - 2) % CYCLE_DAYS + 1, 'dinner'];
}

/** @return array{int, string} [cycle_day, meal_time] of the slot after; wraps from day 14 to day 1. */
function next_slot(int $cycleDay, string $mealTime): array
{
    return $mealTime === 'lunch' ? [$cycleDay, 'dinner'] : [$cycleDay % CYCLE_DAYS + 1, 'lunch'];
}

/** e.g. "Samstag, 7. November 2026" */
function format_long_date(DateTimeImmutable $date): string
{
    return WEEKDAYS[(int)$date->format('N')] . ', ' . $date->format('j') . '. '
        . MONTHS[(int)$date->format('n')] . ' ' . $date->format('Y');
}

/** e.g. "Sa 7.11." */
function format_short_date(DateTimeImmutable $date): string
{
    return mb_substr(WEEKDAYS[(int)$date->format('N')], 0, 2) . ' ' . $date->format('j.n.');
}
