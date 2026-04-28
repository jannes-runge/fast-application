<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$page = Pages::get('datenschutz');
if (!$page) { http_response_code(404); echo 'Nicht gefunden.'; exit; }
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= e($page['title']) ?> · <?= e(cfg('company_name')) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="stylesheet" href="assets/style.css">
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
<body>
<main class="container container-sm">
  <p class="breadcrumb"><a href="index.php">← Zur Bewerbung</a></p>
  <article class="card content-page">
    <?= $page['content_html'] /* bereits beim Speichern saniert */ ?>
  </article>
  <footer class="footer">
    <small>© <?= date('Y') ?> <?= e(cfg('company_name')) ?> ·
      <a href="impressum.php">Impressum</a> ·
      <a href="datenschutz.php">Datenschutz</a>
    </small>
  </footer>
</main>
</body>
</html>
