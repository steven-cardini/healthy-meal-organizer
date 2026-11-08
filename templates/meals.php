<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Gerichte</h1>
    <p><?= count($meals) ?> Gerichte</p>
</hgroup>
<p><a href="/meals/new" role="button">Neues Gericht</a></p>
<ul class="meal-list">
    <?php foreach ($meals as $meal): ?>
        <li id="<?= e('meal-' . $meal['id']) ?>">
            <a href="/meals/edit?id=<?= e($meal['id']) ?>"><?= e($meal['name']) ?></a>
            <small class="muted"><?= $meal['uses'] === 0 ? 'Nicht im Plan' : e($meal['uses']) . '× im Plan' ?></small>
            <?= flash_html(($flash['target'] ?? null) === 'meal-' . $meal['id'] ? $flash : null) ?>
        </li>
    <?php endforeach ?>
</ul>
