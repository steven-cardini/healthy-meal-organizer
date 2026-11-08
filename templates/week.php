<?php declare(strict_types=1) ?>
<hgroup>
    <h1>KW <?= e($calendarWeek) ?> · Woche <?= e($cycleWeek) ?></h1>
    <p><?= e($days[0]['date']->format('j.n.Y')) ?> – <?= e($days[6]['date']->format('j.n.Y')) ?></p>
</hgroup>
<?php foreach ($days as $day): ?>
    <?php $isToday = $day['date'] == $today ?>
    <article class="week-day"<?= $isToday ? ' aria-current="date"' : '' ?>>
        <h2>
            <a href="/?date=<?= e($day['date']->format('Y-m-d')) ?>"><?= e(weekday($day['date'])) ?>
                <span><?= e($day['date']->format('j.n.')) ?></span></a>
            <?php if ($isToday): ?><span class="relative-day">Heute</span><?php endif ?>
        </h2>
        <?php foreach (MEAL_TIMES as $mealTime => $label): ?>
            <div class="week-slot <?= e($mealTime) ?>">
                <h3><span class="visually-hidden"><?= e($label) ?></span></h3>
                <div><?= slot_html($plan, $day['cycleDay'], $mealTime) ?></div>
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
