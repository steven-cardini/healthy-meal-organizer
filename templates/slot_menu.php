<?php
declare(strict_types=1);

// The ⋯ menu of a slot (D-36, D-54). Needs $date, $mealTime, $entry, $swap (false: planning), $menuLabel, $resetWarning,
// $actions (show the swap or planning actions), $recipeUrl, $mealIngredients (ingredients_by_category(), day view only).
?>
<details class="slot-menu">
    <summary aria-label="<?= e($menuLabel) ?>"></summary>
    <div class="slot-actions">
        <?php if ($mealIngredients !== []): ?>
            <button type="button" class="ingredients" popovertarget="ingredients-<?= e($mealTime) ?>">Zutaten</button>
        <?php endif ?>
        <?php if ($recipeUrl !== null): ?>
            <a href="<?= e($recipeUrl) ?>" class="recipe" target="_blank" rel="noopener noreferrer">Rezept</a>
        <?php endif ?>
        <?php if ($actions): ?>
            <a href="<?= e(swap_url('/swap', $date, $mealTime, $swap)) ?>" class="swap"><?= $entry['meal'] === null ? 'Gericht planen' : 'Anderes Gericht' ?></a>
            <?php if ($entry['meal'] !== null): ?>
                <a href="<?= e(swap_url('/swap/skip', $date, $mealTime, $swap)) ?>" class="skip">Auslassen</a>
            <?php endif ?>
            <?php if ($entry['changed']): ?>
                <form method="post" action="/swap/reset">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="date" value="<?= e($date->format('Y-m-d')) ?>">
                    <input type="hidden" name="meal_time" value="<?= e($mealTime) ?>">
                    <?php if (!$swap): ?>
                        <input type="hidden" name="planning" value="1">
                    <?php endif ?>
                    <button type="submit" class="reset">Zurücksetzen</button>
                </form>
                <?php if ($resetWarning !== null): ?>
                    <p class="warning"><?= e($resetWarning) ?></p>
                <?php endif ?>
            <?php endif ?>
        <?php endif ?>
    </div>
</details>
<?php if ($mealIngredients !== []): ?>
    <div id="ingredients-<?= e($mealTime) ?>" class="ingredients-popup" popover>
        <h3>Zutaten für <?= e($entry['meal']) ?></h3>
        <?php foreach ($mealIngredients as $category => $names): ?>
            <h4><?= e(INGREDIENT_CATEGORIES[$category]) ?></h4>
            <ul>
                <?php foreach ($names as $name): ?>
                    <li><?= e($name) ?></li>
                <?php endforeach ?>
            </ul>
        <?php endforeach ?>
        <button type="button" popovertarget="ingredients-<?= e($mealTime) ?>" popovertargetaction="hide">Schliessen</button>
    </div>
<?php endif ?>
