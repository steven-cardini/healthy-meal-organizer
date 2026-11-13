<?php declare(strict_types=1) ?>
<hgroup class="day-title">
    <?php if ($relativeDay !== null): ?>
        <p class="relative-day"><?= e($relativeDay) ?></p>
    <?php endif ?>
    <h1><?= e(weekday($date)) ?></h1>
    <p><?= e(format_date($date)) ?> · Woche <?= e($week['blueprint']) ?></p>
</hgroup>
<?= planning_open_html($weekStart, $week) ?>
<?php foreach (MEAL_TIMES as $mealTime => $label): ?>
    <?php $entry = $entries[$date->format('Y-m-d')][$mealTime] ?>
    <?php $past = is_past($date, $mealTime) ?>
    <article class="meal-time <?= e($mealTime) ?><?= $past ? ' past' : '' ?>">
        <h2><?= e($label) ?><?php if ($past): ?><span class="visually-hidden"> (vorbei)</span><?php endif ?></h2>
        <?= slot_html($entries, $date, $mealTime, true) ?>
        <?php
        $actions = $swappable[$mealTime];
        $recipeUrl = $entry['recipe_url'] ?? null;
        $mealIngredients = ingredients_by_category($entry['meal_id'] === null ? [] : $ingredients[$entry['meal_id']]);
        ?>
        <?php if ($actions || $recipeUrl !== null || $mealIngredients !== []): ?>
            <?php
            $swap = true;
            $menuLabel = "Mehr zum $label";
            $resetWarning = $resetWarnings[$mealTime] ?? null;
            require __DIR__ . '/slot_menu.php';
            ?>
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
