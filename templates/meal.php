<?php
declare(strict_types=1);

$cancel = $target['cancel'] ?? ($id === null ? '/meals' : "/meals#meal-$id");
?>
<hgroup>
    <h1><?= $id === null ? 'Neues Gericht' : 'Gericht bearbeiten' ?></h1>
    <?php if ($target !== null): ?>
        <p><?= e($target['text']) ?></p>
    <?php elseif ($id !== null): ?>
        <p><?= $uses === 0 ? 'Nicht im Plan' : e($uses) . '× im Plan' ?></p>
    <?php else: ?>
        <p>Wähle bei jeder Eigenschaft einen Wert.</p>
    <?php endif ?>
</hgroup>
<?= flash_html($error === null ? null : ['text' => $error, 'error' => true]) ?>
<form method="post" action="<?= $id === null ? '/meals/new' : '/meals/edit' ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($id !== null): ?>
        <input type="hidden" name="id" value="<?= e($id) ?>">
    <?php endif ?>
    <?php foreach ($target['fields'] ?? [] as $field => $value): ?>
        <input type="hidden" name="<?= e($field) ?>" value="<?= e($value) ?>">
    <?php endforeach ?>
    <label>
        Name
        <input type="text" name="name" value="<?= e($meal['name']) ?>" maxlength="<?= MAX_TEXT_LENGTH ?>" required
            <?= $meal['name'] === '' ? 'autofocus' : '' ?>>
    </label>
    <?php foreach (MEAL_TAGS as $tag => $definition): ?>
        <fieldset class="tag">
            <legend>
                <?= e($definition['label']) ?>
                <?php if (isset($definition['hint'])): ?><small class="muted"><?= e($definition['hint']) ?></small><?php endif ?>
            </legend>
            <?php foreach ($definition['values'] as $value => $label): ?>
                <label class="<?= e(tag_class($tag, $value)) ?>">
                    <input type="radio" name="<?= e($tag) ?>" value="<?= e($value) ?>" required
                        <?= ($meal[$tag] ?? null) === $value ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach ?>
        </fieldset>
    <?php endforeach ?>
    <div class="actions">
        <button type="submit">Speichern</button>
        <a href="<?= e($cancel) ?>" role="button" class="secondary outline">Abbrechen</a>
    </div>
</form>
<?php if ($id !== null): ?>
    <hr>
    <?php if ($uses === 0): ?>
        <form method="post" action="/meals/delete">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <button type="submit" class="secondary outline">Gericht löschen</button>
        </form>
    <?php else: ?>
        <p><small class="muted">Löschen ist nur möglich, wenn das Gericht nicht im Plan eingetragen ist.</small></p>
    <?php endif ?>
<?php endif ?>
