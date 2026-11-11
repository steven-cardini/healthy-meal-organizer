<?php declare(strict_types=1) ?>
<h1>Woche <?= e($week['blueprint']) ?> · <?= e(format_week_dates($weekStart)) ?></h1>
<?= planning_open_html($weekStart, $week) ?>
<?php foreach ($days as $day): ?>
    <?php $isToday = $day == $today ?>
    <article class="week-day"<?= $isToday ? ' aria-current="date"' : '' ?>>
        <h2>
            <a href="/?date=<?= e($day->format('Y-m-d')) ?>"><?= e(weekday($day)) ?>
                <span><?= e($day->format('j.n.')) ?></span></a>
            <?php if ($isToday): ?><span class="relative-day">Heute</span><?php endif ?>
        </h2>
        <?php foreach (MEAL_TIMES as $mealTime => $label): ?>
            <?php $past = is_past($day, $mealTime) ?>
            <div class="week-slot <?= e($mealTime) ?><?= $past ? ' past' : '' ?>">
                <h3><span class="visually-hidden"><?= e($label) ?><?= $past ? ' (vorbei)' : '' ?></span></h3>
                <div><?= slot_html($entries, $day, $mealTime) ?></div>
            </div>
        <?php endforeach ?>
    </article>
<?php endforeach ?>
<nav class="pager">
    <a href="/week?date=<?= e($previous->format('Y-m-d')) ?>" rel="prev">← Vorwoche</a>
    <?php if (!$isCurrentWeek): ?>
        <a href="/week" class="current">Diese Woche</a>
    <?php endif ?>
    <a href="/week?date=<?= e($next->format('Y-m-d')) ?>" rel="next">Nächste →</a>
</nav>
