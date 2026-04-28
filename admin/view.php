<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pdo = DB::conn();

$notice = null;
$errors = [];

// --- POST-Aktionen (Löschen, Pool einladen, Pool entfernen) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfCheck($_POST['_csrf'] ?? null)) {
        http_response_code(400); exit('CSRF');
    }
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        if (Applications::delete($id, 'manual')) {
            header('Location: index.php?msg=deleted');
            exit;
        }
        $errors[] = 'Bewerbung konnte nicht gelöscht werden.';
    }

    if ($action === 'pool_invite') {
        $rowI = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
        $rowI->execute([$id]);
        $r = $rowI->fetch();
        if ($r) {
            $token = bin2hex(random_bytes(16));
            $ttl   = max(1, (int)cfg('gdpr.pool_invite_ttl_days', 14)) * 86400;
            $exp   = time() + $ttl;
            $pdo->prepare(
                "UPDATE applications
                    SET pool_status = 'pending', pool_token = ?, pool_token_expires = ?, pool_invited_at = ?
                  WHERE id = ?"
            )->execute([$token, $exp, time(), $id]);

            try {
                $firstName = Crypto::decrypt($r['first_name_enc']);
                $email     = Crypto::decrypt($r['email_enc']);
                $position  = Crypto::decrypt($r['position_enc']);
                $confirm = public_url('pool.php?id=' . $id . '&token=' . $token . '&action=confirm');
                $decline = public_url('pool.php?id=' . $id . '&token=' . $token . '&action=decline');
                Mailer::send([$email],
                    'Bewerber-Pool – kurze Bestätigung erbeten',
                    mail_pool_invitation($firstName, $position, $confirm, $decline));
                Audit::log('application.pool_invite', 'application', (string)$id, ['ttl_days' => $ttl/86400]);
                $notice = 'Pool-Einladung wurde versendet.';
            } catch (Throwable $e) {
                error_log('[pool invite] ' . $e->getMessage());
                $errors[] = 'Einladung konnte nicht versendet werden.';
            }
        }
    }

    if ($action === 'pool_remove') {
        $pdo->prepare(
            "UPDATE applications
                SET pool_status = 'none', pool_token = NULL, pool_token_expires = NULL, pool_confirmed_at = NULL
              WHERE id = ?"
        )->execute([$id]);
        Audit::log('application.pool_remove', 'application', (string)$id);
        $notice = 'Aus dem Pool entfernt.';
    }
}

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

$status = (string)($row['status'] ?? 'new');
$statusChanged = (int)($row['status_changed_at'] ?? 0);
$poolStatus = (string)($row['pool_status'] ?? 'none');
$poolConfirmedAt = (int)($row['pool_confirmed_at'] ?? 0);
$poolInvitedAt   = (int)($row['pool_invited_at'] ?? 0);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Bewerbung <?= e($data['first'] . ' ' . $data['last']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
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
<?php $nav = 'apps'; include __DIR__ . '/_topbar.php'; ?>

<main class="container container-wide">
  <p class="breadcrumb"><a href="index.php">← Bewerbungen</a></p>

  <?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif ?>
  <?php if ($errors): ?>
    <div class="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach ?></ul></div>
  <?php endif ?>

  <div class="card detail-view">
    <header class="detail-head">
      <div>
        <h1><?= e($data['first'] . ' ' . $data['last']) ?></h1>
        <p class="muted">Eingegangen am <?= e(date('d.m.Y H:i', (int)$row['created_at'])) ?></p>
      </div>
      <div class="detail-status">
        <span class="status-badge <?= e(AppStatus::cssClass($status)) ?>"><?= e(AppStatus::label($status)) ?></span>
        <?php if ($statusChanged > 0): ?>
          <small class="muted">geändert <?= e(date('d.m.Y H:i', $statusChanged)) ?></small>
        <?php endif ?>
        <?php if ($poolStatus !== 'none'): ?>
          <span class="status-badge st-pool-<?= e($poolStatus) ?>"><?= e(PoolStatus::label($poolStatus)) ?></span>
        <?php endif ?>
      </div>
    </header>

    <dl class="meta">
      <div><dt>E-Mail</dt><dd><a href="mailto:<?= e($data['email']) ?>"><?= e($data['email']) ?></a></dd></div>
      <div><dt>Telefon</dt><dd><?= $data['phone'] !== '' ? e($data['phone']) : '<span class="muted">–</span>' ?></dd></div>
      <div><dt>Position</dt><dd><?= e($data['position']) ?></dd></div>
    </dl>

    <h2>Status ändern</h2>
    <div class="status-actions">
      <?php
        $actions = [
          ['contacted', 'Als kontaktiert markieren', 'btn-ghost', null],
          ['accepted',  'Annehmen',                  'btn-success',
              'Bewerbung von ' . $data['first'] . ' ' . $data['last'] . ' wirklich annehmen?\nEs wird eine Bestätigungsmail an den Bewerber gesendet.'],
          ['rejected',  'Ablehnen',                  'btn-danger',
              'Bewerbung von ' . $data['first'] . ' ' . $data['last'] . ' wirklich ablehnen?\nEs wird eine Absagemail an den Bewerber gesendet.'],
          ['new',       'Auf "Neu" zurücksetzen',    'btn-ghost', null],
        ];
        foreach ($actions as [$nextStatus, $label, $cls, $confirm]):
          if ($nextStatus === $status) continue;
      ?>
        <form method="post" action="status.php"
              <?= $confirm !== null ? 'data-confirm="' . e($confirm) . '"' : '' ?>>
          <?= Auth::csrfField() ?>
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
          <button class="btn btn-sm <?= e($cls) ?>" type="submit"><?= e($label) ?></button>
        </form>
      <?php endforeach ?>
    </div>

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

    <h2>Bewerber-Pool</h2>
    <?php if ($poolStatus === 'confirmed'): ?>
      <p>
        <strong>Im Pool</strong> –
        <span class="muted">bestätigt am <?= e(date('d.m.Y H:i', $poolConfirmedAt)) ?></span>.
        Diese Bewerbung ist von der automatischen Löschung ausgenommen.
      </p>
      <form method="post" data-confirm="Bewerber wirklich aus dem Pool entfernen?&#10;Die Bewerbung wird bei der nächsten Cleanup-Runde wieder gelöscht.">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="pool_remove">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <button class="btn btn-sm btn-ghost" type="submit">Aus Pool entfernen</button>
      </form>
    <?php elseif ($poolStatus === 'pending'): ?>
      <p>
        <span class="muted">Einladung versendet am <?= e(date('d.m.Y H:i', $poolInvitedAt)) ?>.</span>
        Wartet auf Bestätigung durch den Bewerber.
      </p>
      <form method="post" data-confirm="Einladung erneut versenden? Der alte Link wird ungültig.">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="pool_invite">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <button class="btn btn-sm btn-ghost" type="submit">Einladung erneut senden</button>
      </form>
    <?php else: ?>
      <p class="muted">
        Mit Einverständnis des Bewerbers können die Daten dauerhaft im Pool gespeichert werden,
        um bei einer passenden Position erneut Kontakt aufzunehmen. Der Bewerber erhält dazu eine
        Opt-in-Mail und muss explizit zustimmen.
      </p>
      <form method="post" data-confirm="Pool-Einladung wirklich an den Bewerber versenden?">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="pool_invite">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <button class="btn btn-sm btn-primary" type="submit">In Pool einladen</button>
      </form>
    <?php endif ?>

    <h2>Löschen</h2>
    <p class="muted">Bewerbung und alle Anhänge werden unwiderruflich entfernt. Die Aktion wird im Audit-Log festgehalten.</p>
    <form method="post" data-confirm="Bewerbung von <?= e($data['first'] . ' ' . $data['last']) ?> unwiderruflich löschen?&#10;Diese Aktion kann nicht rückgängig gemacht werden.">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
      <button class="btn btn-sm btn-danger" type="submit">Bewerbung löschen</button>
    </form>
  </div>
</main>
<script src="../assets/admin.js" defer></script>
</body>
</html>
