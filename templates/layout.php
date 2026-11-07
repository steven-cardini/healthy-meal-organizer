<?php
declare(strict_types=1);

$menu = ['day' => ['/', 'Heute'], 'week' => ['/week', 'Woche'], 'plan' => ['/plan', 'Plan bearbeiten'], 'meals' => ['/meals', 'Gerichte']];
?>
<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#007a50">
    <title><?= e($title) ?> – Menüplan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.jade.min.css" integrity="sha384-9eWBRFENYDQF0oSd2bdDvnE6+jPkxkGjSXpeEJVzODxNnyhyd3wh5T+/3KJ+NHOo" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<?php if ($nav !== null): ?>
    <header class="container">
        <nav>
            <ul><li><strong>Menüplan</strong></li></ul>
            <ul>
                <?php foreach ($menu as $key => [$href, $label]): ?>
                    <li><a href="<?= e($href) ?>"<?= $key === $nav ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach ?>
            </ul>
        </nav>
    </header>
<?php endif ?>
<main class="container">
    <?= flash_html(($flash['target'] ?? null) === null ? $flash : null) ?>
    <?= $content ?>
</main>
<?php if ($nav !== null): ?>
    <footer class="container">
        <form method="post" action="/logout">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="secondary outline">Abmelden</button>
        </form>
    </footer>
<?php endif ?>
</body>
</html>
