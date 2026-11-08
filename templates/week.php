<?php declare(strict_types=1) ?>
<hgroup>
    <h1>KW <?= e($calendarWeek) ?> · Woche <?= e($cycleWeek) ?></h1>
    <p><?= e($days[0]['date']->format('j.n.Y')) ?> – <?= e($days[6]['date']->format('j.n.Y')) ?></p>
</hgroup>
<div class="overflow-auto">
    <table class="week">
        <thead>
        <tr>
            <th scope="col">Tag</th>
            <?php foreach (MEAL_TIMES as $label): ?>
                <th scope="col"><?= e($label) ?></th>
            <?php endforeach ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($days as $day): ?>
            <tr<?= $day['date'] == $today ? ' class="today" aria-current="date"' : '' ?>>
                <th scope="row"><a href="/?date=<?= e($day['date']->format('Y-m-d')) ?>"><?= e(format_short_date($day['date'])) ?></a></th>
                <?php foreach (array_keys(MEAL_TIMES) as $mealTime): ?>
                    <td><?= slot_html($plan, $day['cycleDay'], $mealTime) ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<nav class="pager">
    <a href="/week?date=<?= e($previous->format('Y-m-d')) ?>" role="button" class="secondary outline">← Vorwoche</a>
    <?php if (!$isCurrentWeek): ?>
        <a href="/week" role="button" class="secondary">Diese Woche</a>
    <?php endif ?>
    <a href="/week?date=<?= e($next->format('Y-m-d')) ?>" role="button" class="secondary outline">Nächste →</a>
</nav>
