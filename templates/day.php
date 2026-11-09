<?php declare(strict_types=1) ?>
<hgroup class="day-title">
    <?php if ($relativeDay !== null): ?>
        <p class="relative-day"><?= e($relativeDay) ?></p>
    <?php endif ?>
    <h1><?= e(weekday($date)) ?></h1>
    <p><?= e(format_date($date)) ?> · Woche <?= e($cycleWeek) ?></p>
</hgroup>
<?php foreach (MEAL_TIMES as $mealTime => $label): ?>
    <?php $entry = $entries[$date->format('Y-m-d')][$mealTime] ?>
    <?php $past = is_past($date, $mealTime) ?>
    <?php $swappable = is_swappable($date, $mealTime) ?>
    <article class="meal-time <?= e($mealTime) ?><?= $past ? ' past' : '' ?>">
        <h2><?= e($label) ?><?php if ($past): ?><span class="visually-hidden"> (vorbei)</span><?php endif ?></h2>
        <?= slot_html($entries, $date, $mealTime, true, !$swappable) ?>
        <?php if ($swappable): ?>
            <details class="slot-menu">
                <summary aria-label="<?= e($label) ?> ändern"></summary>
                <div class="slot-actions">
                    <a href="<?= e(swap_url('/swap', $date, $mealTime)) ?>" class="swap"><?= $entry['meal'] === null ? 'Gericht planen' : 'Anderes Gericht' ?></a>
                    <?php if ($entry['meal'] !== null): ?>
                        <a href="<?= e(swap_url('/swap/skip', $date, $mealTime)) ?>" class="skip">Auslassen</a>
                    <?php endif ?>
                    <?php if ($entry['swapped']): ?>
                        <form method="post" action="/swap/reset">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="date" value="<?= e($date->format('Y-m-d')) ?>">
                            <input type="hidden" name="meal_time" value="<?= e($mealTime) ?>">
                            <button type="submit" class="reset">Zurücksetzen</button>
                        </form>
                        <?php if ($resetWarnings[$mealTime] !== null): ?>
                            <p class="warning"><?= e($resetWarnings[$mealTime]) ?></p>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </details>
        <?php endif ?>
    </article>
<?php endforeach ?>
<nav class="pager">
    <a href="/?date=<?= e($previous->format('Y-m-d')) ?>" rel="prev">← Vortag</a>
    <?php if (!$isToday): ?>
        <a href="/" class="current">Heute</a>
    <?php endif ?>
    <a href="/?date=<?= e($next->format('Y-m-d')) ?>" rel="next">Folgetag →</a>
</nav>
