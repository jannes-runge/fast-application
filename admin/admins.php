<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$pdo = DB::conn();
$errors = [];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfCheck($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sitzung abgelaufen. Bitte neu laden.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create') {
            $u  = trim((string)($_POST['username'] ?? ''));
            $p  = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $u)) $errors[] = 'Benutzername: 3–32 Zeichen, A–Z a–z 0–9 . _ -';
            if (strlen($p) < 12)                              $errors[] = 'Passwort muss mindestens 12 Zeichen haben.';
            if ($p !== $p2)                                   $errors[] = 'Passwörter stimmen nicht überein.';
            if (!$errors) {
                $check = $pdo->prepare('SELECT 1 FROM admins WHERE username = ?');
                $check->execute([$u]);
                if ($check->fetchColumn()) {
                    $errors[] = 'Benutzername existiert bereits.';
                } else {
                    $hash = password_hash($p, PASSWORD_BCRYPT);
                    $ins = $pdo->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?,?,?)');
                    $ins->execute([$u, $hash, time()]);
                    $notice = 'Admin "' . $u . '" wurde angelegt.';
                }
            }
        }

        if ($action === 'delete') {
            $delId = (int)($_POST['id'] ?? 0);
            $count = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($delId === (int)($_SESSION['admin_id'] ?? 0)) {
                $errors[] = 'Du kannst dich nicht selbst löschen.';
            } elseif ($count <= 1) {
                $errors[] = 'Der letzte Admin kann nicht gelöscht werden.';
            } else {
                $del = $pdo->prepare('DELETE FROM admins WHERE id = ?');
                $del->execute([$delId]);
                $notice = 'Admin gelöscht.';
            }
        }
    }
}

$admins = $pdo->query('SELECT id, username, created_at FROM admins ORDER BY created_at ASC')->fetchAll();
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Admin-Verwaltung</title>
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
<?php $nav = 'admins'; include __DIR__ . '/_topbar.php'; ?>

<main class="container container-wide">
  <h1>Admin-Verwaltung</h1>

  <?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif ?>
  <?php if ($errors): ?>
    <div class="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach ?></ul></div>
  <?php endif ?>

  <div class="card table-wrap" style="margin-bottom:1.5rem">
    <table class="tbl tbl-cards tbl-admins">
      <thead><tr><th>Benutzername</th><th>Angelegt am</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($admins as $a): ?>
          <tr>
            <td data-label="Benutzer">
              <?= e($a['username']) ?>
              <?php if ((int)$a['id'] === (int)($_SESSION['admin_id'] ?? 0)): ?>
                <span class="badge" style="background:var(--c-text-soft);font-size:.7rem">du</span>
              <?php endif ?>
            </td>
            <td data-label="Angelegt"><?= e(date('d.m.Y', (int)$a['created_at'])) ?></td>
            <td data-label="">
              <?php if ((int)$a['id'] !== (int)($_SESSION['admin_id'] ?? 0) && count($admins) > 1): ?>
                <form method="post" data-confirm="Admin <?= e($a['username']) ?> wirklich löschen?">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Löschen</button>
                </form>
              <?php else: ?>
                <span class="muted">–</span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <div class="card form">
    <h2>Neuen Admin anlegen</h2>
    <form method="post" autocomplete="off">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="grid-2">
        <label class="field">
          <span>Benutzername</span>
          <input type="text" name="username" required pattern="[A-Za-z0-9._\-]{3,32}">
        </label>
        <label class="field">
          <span>Passwort <small>(min. 12 Zeichen)</small></span>
          <input type="password" name="password" required minlength="12">
        </label>
      </div>
      <label class="field">
        <span>Passwort wiederholen</span>
        <input type="password" name="password2" required minlength="12">
      </label>
      <div class="actions">
        <button class="btn btn-primary" type="submit">Admin anlegen</button>
      </div>
    </form>
  </div>
</main>
<script src="../assets/admin.js" defer></script>
</body>
</html>
