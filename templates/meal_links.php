<?php
declare(strict_types=1);

// *Zutaten* and *Rezept* of a meal in the day view (FR-29). Needs $mealTime, $recipeUrl, $mealIngredients.
?>
<?php if ($mealIngredients !== []): ?>
    <button type="button" class="ingredients" popovertarget="ingredients-<?= e($mealTime) ?>">Zutaten</button>
<?php endif ?>
<?php if ($recipeUrl !== null): ?>
    <a href="<?= e($recipeUrl) ?>" class="recipe" target="_blank" rel="noopener noreferrer">Rezept</a>
<?php endif ?>
