<?php

declare(strict_types=1);

// Dates are calendar days at midnight UTC, so day arithmetic ignores DST. Only "today" uses Europe/Zurich.

const TIME_ZONE = 'Europe/Zurich';
/** First day of a week as ISO weekday: Saturday (docs/decisions.md D-11). Changing it needs a migration of week and date_slot. */
const WEEK_START = 6;
/** Days the uncooked ingredients keep after delivery: upper end of the shelf_life range; long: all week. */
const SHELF_LIFE_DAYS = ['short' => 2, 'medium' => 5];
const WEEKDAYS = [1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
/** Lunch is past from this time on, dinner from the next day on (docs/decisions.md D-37). */
const LUNCH_END = '14:00';
const MONTHS = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

function parse_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
}

/** Local time. The environment variable MENUPLAN_NOW (e.g. "2026-01-21 14:00") fixes it for the smoke tests. */
function now(): DateTimeImmutable
{
    return new DateTimeImmutable(getenv('MENUPLAN_NOW') ?: 'now', new DateTimeZone(TIME_ZONE));
}

function today(): DateTimeImmutable
{
    return new DateTimeImmutable(now()->format('Y-m-d'), new DateTimeZone('UTC'));
}

function add_days(DateTimeImmutable $date, int $days): DateTimeImmutable
{
    return $date->modify(sprintf('%+d days', $days));
}

/** First day of the week of $date (WEEK_START). */
function week_start(DateTimeImmutable $date): DateTimeImmutable
{
    return add_days($date, 1 - week_day($date));
}

/** Day within its week, 1–7 (1 = WEEK_START). */
function week_day(DateTimeImmutable $date): int
{
    return ((int)$date->format('N') - WEEK_START + 7) % 7 + 1;
}

/** Name of day 1–7 of a week, e.g. 1: "Samstag". */
function week_day_name(int $day): string
{
    return WEEKDAYS[(WEEK_START + $day - 2) % 7 + 1];
}

function weeks_between(DateTimeImmutable $from, DateTimeImmutable $to): int
{
    return intdiv($to->getTimestamp() - $from->getTimestamp(), 7 * 86400);
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

/** Whether a slot is over: an earlier date, or today's lunch from LUNCH_END on. */
function is_past(DateTimeImmutable $date, string $mealTime): bool
{
    $today = today();
    return $date < $today || ($date == $today && $mealTime === 'lunch' && now()->format('H:i') >= LUNCH_END);
}

/** Whether a slot can be swapped: not past, in the current week, or in the next week once it is planned (FR-17). */
function is_swappable(DateTimeImmutable $date, string $mealTime, bool $nextWeekPlanned): bool
{
    return !is_past($date, $mealTime) && $date < add_days(week_start(today()), $nextWeekPlanned ? 14 : 7);
}

/** Last day the uncooked ingredients keep, counted from the start of the week; null: all week (FR-20). */
function fresh_until(string $shelfLife, DateTimeImmutable $weekStart): ?DateTimeImmutable
{
    return isset(SHELF_LIFE_DAYS[$shelfLife]) ? add_days($weekStart, SHELF_LIFE_DAYS[$shelfLife]) : null;
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

/** e.g. "14.–20.11.", or "28.11.–4.12." across months */
function format_week_dates(DateTimeImmutable $weekStart): string
{
    $end = add_days($weekStart, 6);
    return $weekStart->format($weekStart->format('n') === $end->format('n') ? 'j.' : 'j.n.') . '–' . $end->format('j.n.');
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
