<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Einkaufsliste</h1>
    <p>Woche <?= e($week['blueprint']) ?> · <?= e(format_week_dates($weekStart)) ?></p>
</hgroup>
<?php if ($list['categories'] === [] && $list['without'] === []): ?>
    <p>In dieser Woche ist kein Gericht geplant.</p>
<?php endif ?>
<div class="grocery-list" data-week="<?= e($weekStart->format('Y-m-d')) ?>">
    <?php foreach ($list['categories'] as $category => $items): ?>
        <section>
            <h2><?= e(INGREDIENT_CATEGORIES[$category]) ?></h2>
            <ul>
                <?php foreach ($items as $item): ?>
                    <li>
                        <label>
                            <input type="checkbox" value="<?= e($item['name_key']) ?>" autocomplete="off">
                            <span><?= e($item['name']) ?> <small><?= e(grocery_meals_text($item['meals'])) ?></small></span>
                        </label>
                    </li>
                <?php endforeach ?>
            </ul>
        </section>
    <?php endforeach ?>
</div>
<?php if ($list['without'] !== []): ?>
    <p class="warning">Ohne Zutaten: <?= e(grocery_meals_text($list['without'])) ?></p>
<?php endif ?>
<p><a href="/week?date=<?= e($weekStart->format('Y-m-d')) ?>" role="button" class="secondary outline">Zur Woche</a></p>
