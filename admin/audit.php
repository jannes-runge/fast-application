<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$entries = Audit::recent(500);

$labels = [
    'login.success'              => 'Login erfolgreich',
    'login.failed'               => 'Login fehlgeschlagen',
    'logout'                     => 'Logout',
    'application.status_change'  => 'Status geändert',
    'application.delete'         => 'Bewerbung gelöscht',
    'application.pool_invite'    => 'Pool-Einladung versendet',
    'application.pool_confirm'   => 'Pool-Bestätigung erhalten',
    'application.pool_decline'   => 'Pool-Ablehnung (Daten gelöscht)',
    'application.pool_remove'    => 'Aus Pool entfernt',
    'admin.create'               => 'Admin angelegt',
    'admin.delete'               => 'Admin gelöscht',
    'page.update'                => 'Inhaltsseite bearbeitet',
    'cleanup.run'                => 'Auto-Cleanup ausgeführt',
];
$label = function (string $a) use ($labels): string {
    return $labels[$a] ?? $a;
};
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Audit-Log</title>
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
<?php $nav = 'audit'; include __DIR__ . '/_topbar.php'; ?>

<main class="container container-wide">
  <h1>Audit-Log <span class="badge"><?= count($entries) ?></span></h1>
  <p class="muted">Letzte 500 sicherheits- und DSGVO-relevante Aktionen. Wird automatisch nach 2 Jahren gekürzt.</p>

  <?php if (!$entries): ?>
    <div class="card"><p class="muted">Keine Einträge.</p></div>
  <?php else: ?>
    <div class="card table-wrap">
      <table class="tbl tbl-cards tbl-audit">
        <thead>
          <tr>
            <th>Zeitpunkt</th>
            <th>Wer</th>
            <th>Aktion</th>
            <th>Ziel</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $r): ?>
          <tr>
            <td data-col="ts" data-label="Zeitpunkt"><?= e(date('d.m.Y H:i:s', (int)$r['created_at'])) ?></td>
            <td data-col="who" data-label="Wer">
              <?= $r['admin_user'] !== null ? e($r['admin_user']) : '<span class="muted">System</span>' ?>
            </td>
            <td data-col="action" data-label="Aktion"><?= e($label((string)$r['action'])) ?></td>
            <td data-col="target" data-label="Ziel">
              <?php if ($r['target_type'] === 'application' && $r['target_id']): ?>
                <a href="view.php?id=<?= (int)$r['target_id'] ?>">#<?= (int)$r['target_id'] ?></a>
              <?php elseif ($r['target_type']): ?>
                <?= e((string)$r['target_type']) ?> <?= e((string)$r['target_id']) ?>
              <?php else: ?>
                <span class="muted">–</span>
              <?php endif ?>
            </td>
            <td data-col="details" data-label="Details">
              <?php if ($r['details']): ?>
                <code class="audit-details"><?= e((string)$r['details']) ?></code>
              <?php else: ?>
                <span class="muted">–</span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</main>
<script src="../assets/admin.js" defer></script>
</body>
</html>
