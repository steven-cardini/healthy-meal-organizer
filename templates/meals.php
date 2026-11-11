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
                        <span class="meal-list-badges">
                            <?php if ($meal['ingredients'] === 0): ?><small class="uses unused">Keine Zutaten</small><?php endif ?>
                            <small class="uses<?= $meal['uses'] === 0 ? ' unused' : '' ?>"><?= $meal['uses'] === 0 ? 'Nicht in Vorlagen' : e($meal['uses']) . '× in Vorlagen' ?></small>
                        </span>
                    </span>
                    <?= tags_html($meal) ?>
                </span>
            </a>
            <?= flash_html(($flash['target'] ?? null) === 'meal-' . $meal['id'] ? $flash : null) ?>
        </li>
    <?php endforeach ?>
</ul>
