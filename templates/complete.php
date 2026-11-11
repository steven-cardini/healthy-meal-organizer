<?php declare(strict_types=1) ?>
<hgroup>
    <h1>Planung abschliessen</h1>
    <p>Woche <?= e($week['blueprint']) ?> · <?= e(format_week_dates($weekStart)) ?></p>
</hgroup>
<p>Die Planung lässt sich danach nicht wieder öffnen. Ändern geht dann nur noch spontan in der Tagesansicht.</p>
<form method="post" action="/planning/complete">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="actions">
        <button type="submit">Planung abschliessen</button>
        <a href="/planning" role="button" class="secondary outline">Abbrechen</a>
    </div>
</form>
