<?php
declare(strict_types=1);

$calendar = '<rect x="3" y="4.5" width="18" height="16" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>';
$menu = [
    'day' => ['/', 'Heute', $calendar . '<rect x="7" y="13" width="4" height="4" rx="1" fill="currentColor"/>'],
    'week' => ['/week', 'Woche', $calendar . '<path d="M7 13.5h10M7 17h6"/>'],
    'plan' => ['/plan', 'Plan', '<path d="M4 20h4L19.5 8.5a2.8 2.8 0 0 0-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>'],
    'meals' => ['/meals', 'Gerichte', '<path d="M3 12h18a9 7 0 0 1-18 0z"/><path d="M9 21.5h6M8 3.5c-1 1.5 1 2.5 0 4M12 2.5c-1 1.5 1 2.5 0 4M16 3.5c-1 1.5 1 2.5 0 4"/>'],
];
?>
<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#007a50">
    <title><?= e($title) ?> – Menüplan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.1.1/css/pico.jade.min.css" integrity="sha384-9eWBRFENYDQF0oSd2bdDvnE6+jPkxkGjSXpeEJVzODxNnyhyd3wh5T+/3KJ+NHOo" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/app.css')) ?>">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <script src="<?= e(asset_url('/assets/app.js')) ?>"></script>
</head>
<body>
<?php if ($nav !== null): ?>
    <header class="container app-header">
        <a href="/" class="brand"><img src="/assets/icons/icon.svg" alt="" width="32" height="32">Menüplan</a>
        <nav class="tabs" aria-label="Hauptmenü">
            <?php foreach ($menu as $key => [$href, $label, $icon]): ?>
                <a href="<?= e($href) ?>"<?= $key === $nav ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg><span><?= e($label) ?></span></a>
            <?php endforeach ?>
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
            <button type="submit" class="secondary outline logout">Abmelden</button>
        </form>
    </footer>
<?php endif ?>
</body>
</html>
