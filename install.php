<?php
declare(strict_types=1);

/**
 * Einmaliges Setup:
 *  - Erzeugt includes/secrets.php mit frischem AES-256 Master-Key
 *  - Legt ersten Admin-Account an
 *  - Deaktiviert sich nach erfolgreichem Lauf via .install-lock
 *
 * Aus Sicherheitsgründen nach dem Setup DIESE DATEI LÖSCHEN.
 */

require __DIR__ . '/includes/bootstrap.php';

$lockFile = BASE_PATH . '/.install-lock';
$secretsFile = INCLUDES_PATH . '/secrets.php';
$alreadyDone = file_exists($lockFile) && file_exists($secretsFile);

// ---------- Umgebungs-Diagnose ----------
$diag = [];
$phpOK   = version_compare(PHP_VERSION, '8.1.0', '>=');
$extOpenssl = extension_loaded('openssl');
$extPdoSqlite = extension_loaded('pdo_sqlite');
$extFileinfo = extension_loaded('fileinfo');
$gcmOK = $extOpenssl && in_array('aes-256-gcm', array_map('strtolower', openssl_get_cipher_methods()), true);
$dataWritable = is_writable(DATA_PATH);
$incWritable = is_writable(INCLUDES_PATH);

$diag = [
    ['PHP ≥ 8.1',                    $phpOK,        'Aktuell: ' . PHP_VERSION],
    ['Extension: openssl',           $extOpenssl,   $extOpenssl ? 'geladen' : 'fehlt'],
    ['Extension: pdo_sqlite',        $extPdoSqlite, $extPdoSqlite ? 'geladen' : 'fehlt'],
    ['Extension: fileinfo',          $extFileinfo,  $extFileinfo ? 'geladen' : 'fehlt'],
    ['AES-256-GCM verfügbar',        $gcmOK,        $gcmOK ? 'ok' : 'nicht unterstützt'],
    ['data/ beschreibbar',           $dataWritable, $dataWritable ? 'ok' : DATA_PATH . ' nicht schreibbar'],
    ['includes/ beschreibbar',       $incWritable,  $incWritable ? 'ok' : INCLUDES_PATH . ' nicht schreibbar'],
];
$envOK = !in_array(false, array_column($diag, 1), true);

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyDone) {
    $user = trim((string)($_POST['username'] ?? ''));
    $pw   = (string)($_POST['password'] ?? '');
    $pw2  = (string)($_POST['password2'] ?? '');

    if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $user)) $errors[] = 'Benutzername: 3–32 Zeichen, Buchstaben/Zahlen/._-';
    if (strlen($pw) < 12) $errors[] = 'Passwort muss mindestens 12 Zeichen haben.';
    if ($pw !== $pw2) $errors[] = 'Passwörter stimmen nicht überein.';

    if (!$errors) {
        try {
            // Secret-Key schreiben, falls nicht vorhanden
            if (!file_exists($secretsFile)) {
                $key = base64_encode(random_bytes(32));
                $content = "<?php\nreturn [\n    'master_key' => '" . $key . "',\n];\n";
                if (file_put_contents($secretsFile, $content, LOCK_EX) === false) {
                    throw new RuntimeException('Konnte secrets.php nicht schreiben. Prüfe Schreibrechte auf includes/.');
                }
                @chmod($secretsFile, 0600);
                // Neu laden, damit DB & Crypto den Key kennen
                global $SECRETS;
                $SECRETS = require $secretsFile;
            }

            $pdo = DB::conn();
            $st = $pdo->prepare('SELECT COUNT(*) AS c FROM admins');
            $st->execute();
            if ((int)$st->fetch()['c'] > 0) {
                $errors[] = 'Es existiert bereits ein Admin-Account. Setup abgeschlossen.';
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?,?,?)');
                $ins->execute([$user, $hash, time()]);
                file_put_contents($lockFile, date('c'));
                @chmod($lockFile, 0600);
                $done = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Setup-Fehler: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Setup</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="assets/style.css">
<style>
  :root{
    --c-primary:#4f46e5;--c-primary-dk:#3730a3;--c-accent:#22d3ee;
    --c-bg:#0f172a;--c-bg-soft:#1e293b;--c-surface:#fff;
    --c-text:#0f172a;--c-text-soft:#475569;--c-danger:#ef4444;--c-success:#10b981;
  }
</style>
</head>
<body class="bg-gradient">
<main class="container container-sm">
  <div class="card form fade-in" style="margin-top:4rem">
    <h2>Erstinstallation</h2>
    <?php if ($alreadyDone): ?>
      <div class="alert alert-success">
        Setup ist bereits abgeschlossen. Aus Sicherheitsgründen <strong>lösche diese Datei</strong> (<code>install.php</code>) vom Server.
      </div>
      <a href="admin/login.php" class="btn btn-primary">Zum Admin-Login →</a>
    <?php elseif ($done): ?>
      <div class="alert alert-success">
        <strong>Fertig!</strong> Der Admin-Account wurde angelegt und der Verschlüsselungsschlüssel erzeugt.
        <br><br>
        Bitte jetzt:
        <ol>
          <li><code>install.php</code> vom Server löschen.</li>
          <li>In <code>includes/config.php</code> SMTP-Daten, Logo und Farben anpassen.</li>
          <li><code>includes/secrets.php</code> sichern – ohne den Key sind alle Daten unwiederbringlich!</li>
        </ol>
      </div>
      <a href="admin/login.php" class="btn btn-primary">Zum Admin-Login →</a>
    <?php else: ?>
      <p class="muted">Lege den ersten Admin-Account an. Es wird ein frischer AES-256-Schlüssel erzeugt und in
      <code>includes/secrets.php</code> gespeichert.</p>

      <h3 style="margin-top:1.5rem">Umgebungscheck</h3>
      <ul class="diag">
        <?php foreach ($diag as [$label, $ok, $detail]): ?>
          <li class="<?= $ok ? 'ok' : 'bad' ?>">
            <span class="diag-ico"><?= $ok ? '✓' : '✗' ?></span>
            <strong><?= e($label) ?></strong>
            <span class="muted"> — <?= e($detail) ?></span>
          </li>
        <?php endforeach ?>
      </ul>
      <?php if (!$envOK): ?>
        <div class="alert">Bitte zunächst die oben markierten Probleme beheben. Setup kann erst abgeschlossen werden, wenn alles grün ist.</div>
      <?php endif ?>

      <?php if ($errors): ?>
        <div class="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach ?></ul></div>
      <?php endif ?>
      <form method="post" autocomplete="off" <?= $envOK ? '' : 'style="opacity:.5;pointer-events:none"' ?>>
        <label class="field"><span>Benutzername</span>
          <input type="text" name="username" required pattern="[A-Za-z0-9._\-]{3,32}" value="<?= e($_POST['username'] ?? '') ?>">
        </label>
        <label class="field"><span>Passwort <small>(min. 12 Zeichen)</small></span>
          <input type="password" name="password" required minlength="12">
        </label>
        <label class="field"><span>Passwort wiederholen</span>
          <input type="password" name="password2" required minlength="12">
        </label>
        <div class="actions">
          <button class="btn btn-primary" type="submit"><span>Setup abschließen</span></button>
        </div>
      </form>
    <?php endif ?>
  </div>
</main>
</body>
</html>
