<?php declare(strict_types=1) ?>
<hgroup class="day-title">
    <?php if ($relativeDay !== null): ?>
        <p class="relative-day"><?= e($relativeDay) ?></p>
    <?php endif ?>
    <h1><?= e(weekday($date)) ?></h1>
    <p><?= e(format_date($date)) ?> · Woche <?= e($cycleWeek) ?></p>
</hgroup>
<?php foreach (MEAL_TIMES as $mealTime => $label): ?>
    <article class="meal-time <?= e($mealTime) ?>">
        <header><h2><?= e($label) ?></h2></header>
        <div class="meal"><?= slot_html($plan, $cycleDay, $mealTime) ?></div>
    </article>
<?php endforeach ?>
<nav class="pager">
    <a href="/?date=<?= e($previous->format('Y-m-d')) ?>" role="button" class="secondary outline">← Vortag</a>
    <a href="/" role="button" class="secondary">Heute</a>
    <a href="/?date=<?= e($next->format('Y-m-d')) ?>" role="button" class="secondary outline">Folgetag →</a>
</nav>
