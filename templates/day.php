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
        <?= slot_html($entries, $date, $mealTime, 'day') ?>
        <?php
        $actions = $swappable[$mealTime];
        $recipeUrl = $entry['recipe_url'] ?? null;
        $mealIngredients = ingredients_by_category($entry['meal_id'] === null ? [] : $ingredients[$entry['meal_id']]);
        // The next meal to cook today shows its Zutaten and Rezept outside the ⋯ menu (D-54).
        $hasLinks = $recipeUrl !== null || $mealIngredients !== [];
        $links = $mealTime !== $upcoming;
        ?>
        <?php if (!$links && $hasLinks): ?>
            <div class="slot-actions"><?php require __DIR__ . '/meal_links.php' ?></div>
        <?php endif ?>
        <?php if ($actions || ($links && $hasLinks)): ?>
            <?php
            $swap = true;
            $menuLabel = "Mehr zum $label";
            $resetWarning = $resetWarnings[$mealTime] ?? null;
            require __DIR__ . '/slot_menu.php';
            ?>
        <?php endif ?>
        <?php if ($mealIngredients !== []): ?>
            <?php require __DIR__ . '/ingredients_popup.php' ?>
        <?php endif ?>
    </article>
<?php endforeach ?>
<?php if ($advancePrep !== []): ?>
    <ul class="hints advance-prep" aria-label="Für morgen">
        <?php foreach ($advancePrep as $reminder): ?>
            <li class="hint-advance_prep"><?= e($reminder) ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
<nav class="pager">
    <a href="/?date=<?= e($previous->format('Y-m-d')) ?>" rel="prev">← Vortag</a>
    <?php if (!$isToday): ?>
        <a href="/" class="current">Heute</a>
    <?php endif ?>
    <a href="/?date=<?= e($next->format('Y-m-d')) ?>" rel="next">Folgetag →</a>
</nav>
