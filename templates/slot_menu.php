<?php
declare(strict_types=1);

// The ⋯ menu of a slot (D-36). Needs $date, $mealTime, $entry, $swap (false: planning), $menuLabel, $resetWarning.
?>
<details class="slot-menu">
    <summary aria-label="<?= e($menuLabel) ?> ändern"></summary>
    <div class="slot-actions">
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
    </div>
</details>
