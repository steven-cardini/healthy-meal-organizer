<?php declare(strict_types=1) ?>
<article class="login">
    <hgroup>
        <h1>Menüplan</h1>
        <p>Melde dich mit dem Haushalts-Passwort an.</p>
    </hgroup>
    <form method="post" action="/login">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
            Passwort
            <input type="password" name="password" autocomplete="current-password" required autofocus>
        </label>
        <button type="submit">Anmelden</button>
    </form>
</article>
