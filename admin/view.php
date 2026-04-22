<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$id = (int)($_GET['id'] ?? 0);
$pdo = DB::conn();
$st = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    http_response_code(404);
    echo 'Nicht gefunden.';
    exit;
}

try {
    $data = [
        'first'    => Crypto::decrypt($row['first_name_enc']),
        'last'     => Crypto::decrypt($row['last_name_enc']),
        'email'    => Crypto::decrypt($row['email_enc']),
        'phone'    => $row['phone_enc'] ? Crypto::decrypt($row['phone_enc']) : '',
        'position' => Crypto::decrypt($row['position_enc']),
        'message'  => Crypto::decrypt($row['message_enc']),
    ];
    $attachments = [];
    if ($row['attachments_enc']) {
        $attachments = json_decode(Crypto::decrypt($row['attachments_enc']), true) ?: [];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Fehler beim Entschlüsseln: ' . e($e->getMessage());
    exit;
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Bewerbung <?= e($data['first'] . ' ' . $data['last']) ?></title>
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
<body>
<header class="topbar">
  <div class="topbar-inner">
    <img class="logo-sm" src="../<?= e(cfg('logo_path')) ?>" alt="">
    <strong><?= e(cfg('company_name')) ?> · Admin</strong>
    <span class="spacer"></span>
    <a class="btn btn-ghost btn-sm" href="index.php">← Zurück</a>
    <a class="btn btn-ghost btn-sm" href="logout.php">Abmelden</a>
  </div>
</header>

<main class="container">
  <div class="card">
    <p class="muted">Eingegangen am <?= e(date('d.m.Y H:i', (int)$row['created_at'])) ?></p>
    <h1><?= e($data['first'] . ' ' . $data['last']) ?></h1>
    <p>
      <strong>E-Mail:</strong> <a href="mailto:<?= e($data['email']) ?>"><?= e($data['email']) ?></a><br>
      <strong>Telefon:</strong> <?= $data['phone'] !== '' ? e($data['phone']) : '<span class="muted">–</span>' ?><br>
      <strong>Position:</strong> <?= e($data['position']) ?>
    </p>

    <h2>Anschreiben</h2>
    <div class="message-box"><?= nl2br(e($data['message'])) ?></div>

    <h2>Anhänge</h2>
    <?php if (!$attachments): ?>
      <p class="muted">Keine Anhänge.</p>
    <?php else: ?>
      <ul class="files">
        <?php foreach ($attachments as $a): ?>
          <li>
            <span class="file-ico">PDF</span>
            <a href="download.php?id=<?= (int)$row['id'] ?>&f=<?= urlencode($a['id']) ?>"><?= e($a['name']) ?></a>
            <span class="muted"><?= number_format(((int)$a['size']) / 1024, 0, ',', '.') ?> KB</span>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </div>
</main>
</body>
</html>
