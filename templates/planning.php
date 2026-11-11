<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Nächste Woche planen</h1>
    <p>Woche <?= e($week['blueprint']) ?> · <?= e(format_week_dates($weekStart)) ?></p>
</hgroup>
<?php if ($week['planned']): ?>
    <p>Die nächste Woche ist geplant. Die Woche danach kannst du ab <?= e(weekday($weekStart)) ?> planen.</p>
    <p><a href="/week?date=<?= e($weekStart->format('Y-m-d')) ?>" role="button" class="secondary outline">Zur Woche</a></p>
<?php else: ?>
    <form method="post" action="/planning/blueprint" class="blueprint-choice">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="blueprint" value="<?= e(3 - $week['blueprint']) ?>">
        <button type="submit" class="secondary outline">Stattdessen Woche <?= e(3 - $week['blueprint']) ?> verwenden</button>
        <?php if ($resetWarnings !== []): ?>
            <p class="warning">Verwirft deine Änderungen dieser Woche.</p>
        <?php endif ?>
    </form>
    <?php foreach ($days as $date): ?>
        <article class="week-day planning-day">
            <h2><?= e(weekday($date)) ?> <span><?= e($date->format('j.n.')) ?></span></h2>
            <?php foreach (MEAL_TIMES as $mealTime => $label): ?>
                <div class="week-slot <?= e($mealTime) ?>">
                    <h3><span class="visually-hidden"><?= e($label) ?></span></h3>
                    <div><?= slot_html($entries, $date, $mealTime) ?></div>
                    <?php
                    $entry = $entries[$date->format('Y-m-d')][$mealTime];
                    $swap = false;
                    $menuLabel = slot_name($date, $mealTime);
                    $resetWarning = $resetWarnings[$date->format('Y-m-d')][$mealTime] ?? null;
                    require __DIR__ . '/slot_menu.php';
                    ?>
                </div>
            <?php endforeach ?>
        </article>
    <?php endforeach ?>
    <p><a href="/planning/complete" role="button">Planung abschliessen</a></p>
<?php endif ?>
<p><a href="/week-blueprints">Wochenvorlagen bearbeiten</a></p>
