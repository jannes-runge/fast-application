<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$pdo = DB::conn();
$rows = $pdo->query('SELECT id, created_at, status, first_name_enc, last_name_enc, email_enc, position_enc, attachments_enc FROM applications ORDER BY created_at DESC')->fetchAll();

$apps = [];
foreach ($rows as $r) {
    try {
        $attCount = 0;
        if ($r['attachments_enc']) {
            $meta = json_decode(Crypto::decrypt($r['attachments_enc']), true);
            if (is_array($meta)) $attCount = count($meta);
        }
        $apps[] = [
            'id'        => (int)$r['id'],
            'created'   => (int)$r['created_at'],
            'status'    => (string)($r['status'] ?? 'new'),
            'first'     => Crypto::decrypt($r['first_name_enc']),
            'last'      => Crypto::decrypt($r['last_name_enc']),
            'email'     => Crypto::decrypt($r['email_enc']),
            'position'  => Crypto::decrypt($r['position_enc']),
            'att_count' => $attCount,
        ];
    } catch (Throwable $e) {
        $apps[] = ['id'=>(int)$r['id'],'created'=>(int)$r['created_at'],'status'=>(string)($r['status'] ?? 'new'),'first'=>'?','last'=>'?','email'=>'?','position'=>'[Entschlüsselung fehlgeschlagen]','att_count'=>0];
    }
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin · Bewerbungen</title>
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
  <div class="page-header">
    <h1>Bewerbungen <span class="badge"><?= count($apps) ?></span></h1>
    <?php if ($apps): ?>
      <div class="filter-bar">
        <input type="search" id="filter" placeholder="Suchen (Name, Mail, Position)…" autocomplete="off">
        <select id="filterStatus">
          <option value="">Alle Status</option>
          <?php foreach (AppStatus::ALL as $key => $info): ?>
            <option value="<?= e($key) ?>"><?= e($info['label']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
    <?php endif ?>
  </div>

  <?php if (!$apps): ?>
    <div class="card"><p class="muted">Noch keine Bewerbungen eingegangen.</p></div>
  <?php else: ?>
    <div class="card table-wrap" id="appList">
      <table class="tbl tbl-cards tbl-apps">
        <thead>
          <tr>
            <th>Eingang</th>
            <th>Name</th>
            <th>E-Mail</th>
            <th>Position</th>
            <th>Status</th>
            <th>Anhänge</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $a): ?>
          <?php $search = mb_strtolower($a['first'].' '.$a['last'].' '.$a['email'].' '.$a['position'], 'UTF-8'); ?>
          <tr data-search="<?= e($search) ?>"
              data-status="<?= e($a['status']) ?>"
              data-href="view.php?id=<?= $a['id'] ?>">
            <td data-col="date" data-label="Eingang"><?= e(date('d.m.Y H:i', $a['created'])) ?></td>
            <td data-col="name" data-label="Name"><strong><?= e($a['first'] . ' ' . $a['last']) ?></strong></td>
            <td data-col="email" data-label="E-Mail"><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></td>
            <td data-col="position" data-label="Position"><?= e($a['position']) ?></td>
            <td data-col="status" data-label="Status"><span class="status-badge <?= e(AppStatus::cssClass($a['status'])) ?>"><?= e(AppStatus::label($a['status'])) ?></span></td>
            <td data-col="att" data-label="Anhänge"><?= $a['att_count'] ?></td>
            <td data-col="action" data-label=""><a class="btn btn-sm btn-primary" href="view.php?id=<?= $a['id'] ?>">Öffnen</a></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
      <p class="muted empty-hint row-hidden" id="emptyHint">Keine Treffer.</p>
    </div>
  <?php endif ?>
</main>

<script>
(() => {
  const q  = document.getElementById('filter');
  const fs = document.getElementById('filterStatus');
  const list = document.getElementById('appList');
  if (!q || !fs || !list) return;
  const rows  = list.querySelectorAll('tbody tr');
  const empty = document.getElementById('emptyHint');

  const apply = () => {
    const term   = q.value.trim().toLowerCase();
    const status = fs.value;
    let visible = 0;
    rows.forEach(r => {
      const text  = (r.dataset.search || '');
      const match = (!term || text.includes(term))
                 && (!status || r.dataset.status === status);
      r.classList.toggle('row-hidden', !match);
      if (match) visible++;
    });
    if (empty) empty.classList.toggle('row-hidden', visible !== 0);
  };
  q.addEventListener('input', apply);
  fs.addEventListener('change', apply);

  // Ganze Zeile klickbar (außer auf inneren Links/Buttons)
  rows.forEach(r => {
    const href = r.dataset.href;
    if (!href) return;
    r.addEventListener('click', e => {
      if (e.target.closest('a, button, form')) return;
      window.location = href;
    });
  });
})();
</script>
</body>
</html>
