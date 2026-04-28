<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id     = (int)($_GET['id']     ?? $_POST['id']     ?? 0);
$token  = (string)($_GET['token']  ?? $_POST['token']  ?? '');
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

$state = 'idle'; // idle | done_confirm | done_decline | invalid | expired | already
$firstName = ''; $position = '';

if ($id > 0 && $token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $pdo = DB::conn();
    $st = $pdo->prepare('SELECT * FROM applications WHERE id = ? AND pool_token = ?');
    $st->execute([$id, $token]);
    $row = $st->fetch();

    if (!$row) {
        $state = 'invalid';
    } elseif ((int)$row['pool_token_expires'] < time()) {
        $state = 'expired';
    } elseif (($row['pool_status'] ?? '') === 'confirmed') {
        $state = 'already';
    } else {
        try {
            $firstName = Crypto::decrypt($row['first_name_enc']);
            $position  = Crypto::decrypt($row['position_enc']);
            $email     = Crypto::decrypt($row['email_enc']);
        } catch (Throwable $e) {
            $state = 'invalid';
        }
    }
}

// POST = endgültige Bestätigung / Ablehnung
if ($state === 'idle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfCheck($_POST['_csrf'] ?? null)) {
        $state = 'invalid';
    } elseif ($action === 'confirm') {
        $pdo->prepare(
            "UPDATE applications
                SET pool_status = 'confirmed',
                    pool_confirmed_at = ?,
                    pool_token = NULL,
                    pool_token_expires = NULL
              WHERE id = ?"
        )->execute([time(), $id]);
        Audit::log('application.pool_confirm', 'application', (string)$id);
        try {
            Mailer::send([$email], 'Du bist im Bewerber-Pool', mail_pool_confirmed($firstName));
        } catch (Throwable $e) {
            error_log('[pool confirm mail] ' . $e->getMessage());
        }
        $state = 'done_confirm';
    } elseif ($action === 'decline') {
        Audit::log('application.pool_decline', 'application', (string)$id);
        Applications::delete($id, 'pool_decline');
        $state = 'done_decline';
    }
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Bewerber-Pool · <?= e(cfg('company_name')) ?></title>
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
<body class="bg-soft">
<main class="container container-sm">
  <div class="card form fade-in centered-card">
    <img class="logo-sm" src="<?= e(cfg('logo_path')) ?>" alt="">

    <?php if ($state === 'invalid'): ?>
      <h2>Link ungültig</h2>
      <p class="muted">Dieser Link ist nicht (mehr) gültig. Falls du Fragen hast, melde dich gerne unter
        <a href="mailto:<?= e(cfg('contact_email')) ?>"><?= e(cfg('contact_email')) ?></a>.</p>
    <?php elseif ($state === 'expired'): ?>
      <h2>Link abgelaufen</h2>
      <p class="muted">Die Einladung ist nicht mehr gültig. Bitte melde dich kurz bei uns – wir senden dir
        gerne eine neue Einladung.</p>
    <?php elseif ($state === 'already'): ?>
      <h2>Schon bestätigt</h2>
      <p>Du bist bereits in unserem Bewerber-Pool. Danke!</p>
    <?php elseif ($state === 'done_confirm'): ?>
      <h2>Vielen Dank!</h2>
      <p>Du bist jetzt Teil unseres Bewerber-Pools. Wir melden uns, sobald eine passende Position frei wird.</p>
    <?php elseif ($state === 'done_decline'): ?>
      <h2>Daten gelöscht</h2>
      <p>Wir haben deine Bewerbungsdaten unwiderruflich gelöscht. Alles Gute für deinen weiteren Weg!</p>
    <?php else: /* idle */ ?>
      <h2>Aufnahme in den Bewerber-Pool</h2>
      <p>Hallo <strong><?= e($firstName) ?></strong>,</p>
      <p>möchtest du, dass wir deine Bewerbung als <strong><?= e($position) ?></strong> in unserem
        Bewerber-Pool bei <?= e(cfg('company_name')) ?> speichern? Wir melden uns, sobald eine
        passende Position frei wird. Du kannst diese Einwilligung jederzeit formlos widerrufen.</p>

      <form method="post" style="margin-top:1.2rem">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="confirm">
        <div class="actions">
          <button class="btn btn-primary" type="submit">Ja, bitte aufnehmen</button>
        </div>
      </form>

      <form method="post" style="margin-top:.6rem" data-confirm="Wirklich ablehnen? Deine Bewerbungsdaten werden dann sofort und unwiderruflich gelöscht.">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="decline">
        <div class="actions">
          <button class="btn btn-ghost" type="submit">Nein – Daten löschen</button>
        </div>
      </form>
    <?php endif ?>

    <p style="margin-top:1.5rem;text-align:center">
      <a href="index.php" class="muted" style="font-size:.85rem">← Zur Startseite</a>
    </p>
  </div>
</main>
<script src="assets/admin.js" defer></script>
</body>
</html>
