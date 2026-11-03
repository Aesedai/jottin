<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Jottin') ?></title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/img/jottin-32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/img/jottin-180.png">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<?php $user = service('currentUser'); ?>
<header class="site-header">
    <a class="wordmark" href="<?= $user->isSignedIn() ? '/dashboard' : '/' ?>" aria-label="Jottin home"><span class="jott">jott</span><span class="in">In</span></a>
    <nav aria-label="Main">
        <?php if ($user->isSignedIn()): ?>
            <?php if ($user->hasRole('owner')): ?><a href="/members">Members</a><a href="/audit">Audit log</a><?php endif ?>
            <a href="/account/password">Password</a>
            <form action="/logout" method="post" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="link">Sign out</button>
            </form>
        <?php else: ?>
            <a href="/login">Sign in</a>
            <a href="/signup">Sign up</a>
        <?php endif ?>
    </nav>
</header>
<main class="<?= esc($mainClass ?? 'page', 'attr') ?>">
    <?php if (session()->has('notice') && session()->getFlashdata('notice') !== null): ?>
        <p class="alert alert-ok" role="status"><?= esc(session()->getFlashdata('notice')) ?></p>
    <?php endif ?>
    <?php if (session()->getFlashdata('error') !== null): ?>
        <p class="alert alert-error" role="alert"><?= esc(session()->getFlashdata('error')) ?></p>
    <?php endif ?>
    <?php if (! empty($error)): ?>
        <p class="alert alert-error" role="alert"><?= esc($error) ?></p>
    <?php endif ?>
    <?= $this->renderSection('content') ?>
</main>
</body>
</html>
