<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Gerichte</h1>
    <p><?= count($meals) ?> Gerichte</p>
</hgroup>
<p><a href="/meals/new" role="button">+ Neues Gericht</a></p>
<ul class="meal-list">
    <?php foreach ($meals as $meal): ?>
        <li id="<?= e('meal-' . $meal['id']) ?>">
            <a href="/meals/edit?id=<?= e($meal['id']) ?>">
                <?= avatar_html($meal['protein_source']) ?>
                <span>
                    <span class="meal-list-head">
                        <strong><?= e($meal['name']) ?></strong>
                        <small class="uses<?= $meal['uses'] === 0 ? ' unused' : '' ?>"><?= $meal['uses'] === 0 ? 'Nicht in Vorlagen' : e($meal['uses']) . '× in Vorlagen' ?></small>
                    </span>
                    <?= tags_html($meal) ?>
                </span>
            </a>
            <?= flash_html(($flash['target'] ?? null) === 'meal-' . $meal['id'] ? $flash : null) ?>
        </li>
    <?php endforeach ?>
</ul>
