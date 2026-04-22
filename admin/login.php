<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$errors = [];
if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfCheck($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session abgelaufen. Bitte neu laden.';
    } elseif (Auth::isLockedOut()) {
        $errors[] = 'Zu viele Fehlversuche. Bitte später erneut versuchen.';
    } else {
        $u = trim((string)($_POST['username'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        if (Auth::login($u, $p)) {
            header('Location: index.php');
            exit;
        }
        $errors[] = 'Login fehlgeschlagen.';
        usleep(500000);
    }
}

$title = 'Admin-Login';
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= e($title) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="../assets/style.css">
<style>
  :root{
    --c-primary: <?= e(cfg('colors.primary')) ?>;
    --c-primary-dk: <?= e(cfg('colors.primary_dk')) ?>;
    --c-accent: <?= e(cfg('colors.accent')) ?>;
    --c-bg: <?= e(cfg('colors.bg')) ?>;
    --c-bg-soft: <?= e(cfg('colors.bg_soft')) ?>;
    --c-surface: <?= e(cfg('colors.surface')) ?>;
    --c-text: <?= e(cfg('colors.text')) ?>;
    --c-text-soft: <?= e(cfg('colors.text_soft')) ?>;
    --c-danger: <?= e(cfg('colors.danger')) ?>;
    --c-success: <?= e(cfg('colors.success')) ?>;
  }
</style>
</head>
<body class="bg-gradient">
<main class="container container-sm">
  <div class="card form fade-in" style="margin-top:5rem">
    <img class="logo-sm" src="../<?= e(cfg('logo_path')) ?>" alt="">
    <h2>Admin-Login</h2>
    <p class="muted">Zugang nur für autorisiertes Personal.</p>
    <?php if ($errors): ?>
      <div class="alert"><?= e(implode(' ', $errors)) ?></div>
    <?php endif ?>
    <form method="post" autocomplete="off">
      <?= Auth::csrfField() ?>
      <label class="field">
        <span>Benutzername</span>
        <input type="text" name="username" required autofocus>
      </label>
      <label class="field">
        <span>Passwort</span>
        <input type="password" name="password" required>
      </label>
      <div class="actions">
        <button type="submit" class="btn btn-primary"><span>Anmelden</span></button>
      </div>
    </form>
  </div>
</main>
</body>
</html>
