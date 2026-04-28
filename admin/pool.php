<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$pdo = DB::conn();

// Pool-Mitglieder + ausstehende Einladungen
$rows = $pdo->query(
    "SELECT id, created_at, status, pool_status, pool_invited_at, pool_confirmed_at,
            first_name_enc, last_name_enc, email_enc, position_enc
       FROM applications
      WHERE pool_status IN ('pending','confirmed')
      ORDER BY pool_status DESC, COALESCE(pool_confirmed_at, pool_invited_at, created_at) DESC"
)->fetchAll();

$apps = [];
foreach ($rows as $r) {
    try {
        $apps[] = [
            'id'        => (int)$r['id'],
            'created'   => (int)$r['created_at'],
            'status'    => (string)($r['status'] ?? 'new'),
            'pool'      => (string)$r['pool_status'],
            'invited'   => (int)($r['pool_invited_at'] ?? 0),
            'confirmed' => (int)($r['pool_confirmed_at'] ?? 0),
            'first'     => Crypto::decrypt($r['first_name_enc']),
            'last'      => Crypto::decrypt($r['last_name_enc']),
            'email'     => Crypto::decrypt($r['email_enc']),
            'position'  => Crypto::decrypt($r['position_enc']),
        ];
    } catch (Throwable $e) {
        $apps[] = [
            'id'=>(int)$r['id'],'created'=>(int)$r['created_at'],
            'status'=>(string)($r['status'] ?? 'new'),
            'pool'=>(string)$r['pool_status'],
            'invited'=>(int)($r['pool_invited_at'] ?? 0),
            'confirmed'=>(int)($r['pool_confirmed_at'] ?? 0),
            'first'=>'?','last'=>'?','email'=>'?','position'=>'[Entschlüsselung fehlgeschlagen]',
        ];
    }
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin · Bewerber-Pool</title>
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
<?php $nav = 'pool'; include __DIR__ . '/_topbar.php'; ?>

<main class="container container-wide">
  <h1>Bewerber-Pool <span class="badge"><?= count($apps) ?></span></h1>
  <p class="muted">Bewerber:innen, die der dauerhaften Speicherung ihrer Daten ausdrücklich zugestimmt haben (oder eine Einladung dazu erhalten haben). Pool-Bestätigte sind von der automatischen Löschung ausgenommen.</p>

  <?php if (!$apps): ?>
    <div class="card"><p class="muted">Aktuell sind keine Bewerber im Pool.</p></div>
  <?php else: ?>
    <div class="card table-wrap">
      <table class="tbl tbl-cards tbl-apps">
        <thead>
          <tr>
            <th>Pool-Status</th>
            <th>Name</th>
            <th>E-Mail</th>
            <th>Position</th>
            <th>Seit / eingeladen</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $a):
            $ts = $a['pool'] === 'confirmed' ? $a['confirmed'] : $a['invited'];
        ?>
          <tr data-href="view.php?id=<?= $a['id'] ?>">
            <td data-col="status" data-label="Pool"><span class="status-badge st-pool-<?= e($a['pool']) ?>"><?= e(PoolStatus::label($a['pool'])) ?></span></td>
            <td data-col="name" data-label="Name"><strong><?= e($a['first'] . ' ' . $a['last']) ?></strong></td>
            <td data-col="email" data-label="E-Mail"><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></td>
            <td data-col="position" data-label="Position"><?= e($a['position']) ?></td>
            <td data-col="date" data-label="Datum"><?= $ts ? e(date('d.m.Y', $ts)) : '–' ?></td>
            <td data-col="action" data-label=""><a class="btn btn-sm btn-primary" href="view.php?id=<?= $a['id'] ?>">Öffnen</a></td>
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
