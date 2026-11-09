<?php
declare(strict_types=1);

$slotFields = '<input type="hidden" name="date" value="' . e($date->format('Y-m-d')) . '">'
    . '<input type="hidden" name="meal_time" value="' . e($mealTime) . '">';
?>
<?php $move = $entry['meal'] === null ?>
<hgroup>
    <h1><?= $move ? 'Gericht planen' : 'Anderes Gericht' ?></h1>
    <p><?= e(format_short_date($date) . ' · ' . MEAL_TIMES[$mealTime]) ?><?= $move ? '' : ' · statt ' . e($entry['meal']) ?></p>
</hgroup>
<section>
    <?php if ($move): ?>
        <h2>Gericht verschieben</h2>
        <p class="muted">Ein Gericht von jetzt bis Freitag hierher verschieben. Dort ist dann nichts mehr geplant.</p>
    <?php else: ?>
        <h2>Gericht tauschen</h2>
        <p class="muted">Mit einem Gericht von jetzt bis Freitag. Die beiden tauschen den Platz.</p>
    <?php endif ?>
    <?php if ($partners === []): ?>
        <p>Bis Freitag ist <?= $move ? 'kein' : 'kein anderes' ?> Gericht geplant.</p>
    <?php endif ?>
    <ul class="choice-list">
        <?php foreach ($partners as $partner): ?>
            <li>
                <form method="post" action="<?= $move ? '/swap/move' : '/swap/exchange' ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <?= $slotFields ?>
                    <input type="hidden" name="partner_date" value="<?= e($partner['date']->format('Y-m-d')) ?>">
                    <input type="hidden" name="partner_meal_time" value="<?= e($partner['meal_time']) ?>">
                    <button type="submit" class="choice">
                        <?= avatar_html($partner['protein_source']) ?>
                        <span>
                            <small class="<?= e($partner['meal_time']) ?>"><?= e(format_short_date($partner['date']) . ' · ' . MEAL_TIMES[$partner['meal_time']]) ?></small>
                            <strong><?= e($partner['meal']) ?></strong>
                            <?= tags_html($partner) ?>
                        </span>
                        <?php foreach ($partner['warnings'] as $moveWarning): ?>
                            <span class="warning"><?= e($moveWarning) ?></span>
                        <?php endforeach ?>
                    </button>
                </form>
            </li>
        <?php endforeach ?>
    </ul>
</section>
<section id="liste">
    <h2>Aus der Liste</h2>
    <p class="muted">Nur hier, die anderen Tage bleiben, wie sie sind.</p>
    <?php if ($warning !== null): ?>
        <p class="warning"><?= e($warning) ?></p>
    <?php endif ?>
    <form method="get" action="/swap#liste" class="filters">
        <?= $slotFields ?>
        <?php foreach (FILTER_TAGS as $tag): ?>
            <label>
                <?= e(MEAL_TAGS[$tag]['label']) ?>
                <select name="<?= e($tag) ?>">
                    <option value="">Alle</option>
                    <?php foreach (MEAL_TAGS[$tag]['values'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $filters[$tag] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
        <?php endforeach ?>
        <button type="submit" class="secondary outline">Filtern</button>
    </form>
    <?php if ($meals === []): ?>
        <p>Kein Gericht passt zu den Filtern.</p>
    <?php endif ?>
    <form method="post" action="/swap/meal">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?= $slotFields ?>
        <ul class="meal-list">
            <?php foreach ($meals as $meal): ?>
                <li>
                    <button type="submit" name="meal_id" value="<?= e($meal['id']) ?>" class="choice">
                        <?= avatar_html($meal['protein_source']) ?>
                        <span>
                            <strong><?= e($meal['name']) ?></strong>
                            <?= tags_html($meal) ?>
                        </span>
                    </button>
                </li>
            <?php endforeach ?>
        </ul>
    </form>
    <p><a href="<?= e(swap_url('/meals/new', $date, $mealTime)) ?>" role="button" class="outline">+ Neues Gericht</a></p>
</section>
<p><a href="<?= e(day_url($date)) ?>" role="button" class="secondary outline">Abbrechen</a></p>
