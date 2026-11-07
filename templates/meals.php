<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Gerichte</h1>
    <p><?= count($meals) ?> Gerichte. Neue Gerichte fügst du im Plan hinzu.</p>
</hgroup>
<?php foreach ($meals as $meal): ?>
    <?php $target = 'meal-' . $meal['id'] ?>
    <div class="meal-row" id="<?= e($target) ?>">
        <form method="post" action="/meals/rename">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e($meal['id']) ?>">
            <fieldset role="group">
                <input type="text" name="name" value="<?= e($meal['name']) ?>" maxlength="<?= MAX_TEXT_LENGTH ?>"
                       required aria-label="Name">
                <button type="submit" class="secondary">Umbenennen</button>
            </fieldset>
        </form>
        <?php if ($meal['uses'] === 0): ?>
            <form method="post" action="/meals/delete">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e($meal['id']) ?>">
                <button type="submit" class="secondary outline">Löschen</button>
            </form>
        <?php else: ?>
            <small class="muted"><?= e($meal['uses']) ?>× im Plan</small>
        <?php endif ?>
        <?= flash_html(($flash['target'] ?? null) === $target ? $flash : null) ?>
    </div>
<?php endforeach ?>
