<?php declare(strict_types=1) ?>
<hgroup>
    <h1><?= e(format_long_date($date)) ?></h1>
    <p><?= $isToday ? 'Heute · ' : '' ?>Woche <?= e($cycleWeek) ?></p>
</hgroup>
<?php foreach (MEAL_TIMES as $mealTime => $label): ?>
    <article>
        <header><?= e($label) ?></header>
        <p class="meal"><?= slot_html($slots[$mealTime] ?? null) ?></p>
    </article>
<?php endforeach ?>
<nav class="pager">
    <a href="/?date=<?= e($previous->format('Y-m-d')) ?>" role="button" class="secondary outline">← Vortag</a>
    <a href="/" role="button" class="secondary">Heute</a>
    <a href="/?date=<?= e($next->format('Y-m-d')) ?>" role="button" class="secondary outline">Folgetag →</a>
</nav>
