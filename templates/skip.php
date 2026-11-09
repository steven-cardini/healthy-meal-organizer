<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Auslassen</h1>
    <p><?= e(format_short_date($date) . ' · ' . MEAL_TIMES[$mealTime] . ' · ' . $entry['meal']) ?></p>
</hgroup>
<?php if ($warning !== null): ?>
    <p class="warning"><?= e($warning) ?></p>
<?php endif ?>
<form method="post" action="/swap/skip">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="date" value="<?= e($date->format('Y-m-d')) ?>">
    <input type="hidden" name="meal_time" value="<?= e($mealTime) ?>">
    <label>
        Notiz (optional)
        <input type="text" name="note" maxlength="<?= MAX_TEXT_LENGTH ?>" placeholder="z.B. Besuch bei den Grosseltern">
    </label>
    <div class="actions">
        <button type="submit">Auslassen</button>
        <a href="<?= e(day_url($date)) ?>" role="button" class="secondary outline">Abbrechen</a>
    </div>
</form>
