<?php declare(strict_types=1) ?>
<div class="ingredient" role="group">
    <input type="text" name="ingredient_name[]" value="<?= e($ingredient['name']) ?>" maxlength="<?= MAX_TEXT_LENGTH ?>"
        list="ingredient-names" placeholder="Zutat" aria-label="Zutat">
    <select name="ingredient_category[]" aria-label="Kategorie">
        <option value="">Kategorie</option>
        <?php foreach (INGREDIENT_CATEGORIES as $category => $label): ?>
            <option value="<?= e($category) ?>"<?= $ingredient['category'] === $category ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach ?>
    </select>
</div>
