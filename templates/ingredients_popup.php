<?php
declare(strict_types=1);

// Popup of *Zutaten* (FR-29, D-54). Needs $mealTime, $entry, $mealIngredients (ingredients_by_category()).
?>
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
