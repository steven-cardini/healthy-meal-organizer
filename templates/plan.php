<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Plan bearbeiten</h1>
    <p>Feld leeren und speichern: nicht geplant.</p>
</hgroup>
<datalist id="meal-names">
    <?php foreach ($meals as $meal): ?>
        <option value="<?= e($meal['name']) ?>"></option>
    <?php endforeach ?>
</datalist>
<?php foreach ([1, 2] as $cycleWeek): ?>
    <section>
        <h2>Woche <?= e($cycleWeek) ?></h2>
        <?php foreach (WEEKDAYS as $weekday => $weekdayName): ?>
            <?php $cycleDay = ($cycleWeek - 1) * 7 + $weekday ?>
            <article>
                <header><strong><?= e($weekdayName) ?></strong></header>
                <div class="grid">
                    <?php foreach (MEAL_TIMES as $mealTime => $label): ?>
                        <?php
                        $slot = $plan[$cycleDay][$mealTime] ?? null;
                        $isNote = $slot !== null && $slot['note'] !== null;
                        $target = "slot-$cycleDay-$mealTime";
                        ?>
                        <form method="post" action="/plan/slot" id="<?= e($target) ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="cycle_day" value="<?= e($cycleDay) ?>">
                            <input type="hidden" name="meal_time" value="<?= e($mealTime) ?>">
                            <fieldset class="choice">
                                <legend><?= e($label) ?></legend>
                                <label><input type="radio" name="type" value="meal"<?= $isNote ? '' : ' checked' ?>> Gericht</label>
                                <label><input type="radio" name="type" value="note"<?= $isNote ? ' checked' : '' ?>> Notiz</label>
                            </fieldset>
                            <fieldset role="group">
                                <input type="text" name="text" value="<?= e($slot['meal'] ?? $slot['note'] ?? '') ?>"
                                       maxlength="<?= MAX_TEXT_LENGTH ?>" list="meal-names" placeholder="Nicht geplant"
                                       aria-label="<?= e("$weekdayName, $label") ?>">
                                <button type="submit">Speichern</button>
                            </fieldset>
                            <?= flash_html(($flash['target'] ?? null) === $target ? $flash : null) ?>
                        </form>
                    <?php endforeach ?>
                </div>
            </article>
        <?php endforeach ?>
    </section>
<?php endforeach ?>
