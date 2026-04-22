<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$success = false;

// Zeit-Stempel gegen Bots
if (empty($_SESSION['form_ts'])) {
    $_SESSION['form_ts'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Sicherheits-Checks ---
    if (!Auth::csrfCheck($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session abgelaufen. Bitte neu laden und erneut senden.';
    }
    if (!empty($_POST['website'])) {
        $errors[] = 'Spam erkannt.';
    }
    if (time() - (int)($_SESSION['form_ts'] ?? time()) < 2) {
        $errors[] = 'Das ging etwas zu schnell.';
    }
    if (!Auth::hitRateLimit('submit', $_SERVER['REMOTE_ADDR'] ?? 'x', 3600, (int)cfg('security.rate_limit_per_hour', 5))) {
        $errors[] = 'Zu viele Bewerbungen in kurzer Zeit. Bitte später erneut versuchen.';
    }

    // --- Felder validieren ---
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName  = trim((string)($_POST['last_name']  ?? ''));
    $email     = trim((string)($_POST['email']      ?? ''));
    $phone     = trim((string)($_POST['phone']      ?? ''));
    $position  = trim((string)($_POST['position']   ?? ''));
    $message   = trim((string)($_POST['message']    ?? ''));
    $consent   = !empty($_POST['consent']);

    if ($firstName === '' || mb_strlen($firstName) > 80) $errors[] = 'Vorname fehlt oder zu lang.';
    if ($lastName === ''  || mb_strlen($lastName)  > 80) $errors[] = 'Nachname fehlt oder zu lang.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errors[] = 'Bitte eine gültige E-Mail angeben.';
    if ($phone !== '' && !preg_match('/^[\d\s+\-\/()]{5,30}$/', $phone)) $errors[] = 'Telefonnummer hat ein ungültiges Format.';
    if (!in_array($position, cfg('positions', []), true)) $errors[] = 'Bitte eine Position auswählen.';
    if (mb_strlen($message) < 10 || mb_strlen($message) > 4000) $errors[] = 'Anschreiben muss 10–4000 Zeichen umfassen.';
    if (!$consent) $errors[] = 'Bitte der Datenverarbeitung zustimmen.';

    // --- Datei-Uploads prüfen ---
    $filesToStore = [];
    if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $max = (int)cfg('uploads.max_files', 3);
        $count = count($_FILES['attachments']['name']);
        if ($count > $max) {
            $errors[] = "Maximal $max Dateien erlaubt.";
        }
        for ($i = 0; $i < $count; $i++) {
            $err = (int)$_FILES['attachments']['error'][$i];
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            if ($err !== UPLOAD_ERR_OK) { $errors[] = 'Datei-Upload fehlgeschlagen.'; continue; }

            $tmp  = $_FILES['attachments']['tmp_name'][$i];
            $name = $_FILES['attachments']['name'][$i];
            $size = (int)$_FILES['attachments']['size'][$i];
            if ($size > (int)cfg('uploads.max_size_bytes', 8 * 1024 * 1024)) {
                $errors[] = "Datei '" . e($name) . "' überschreitet die Maximalgröße.";
                continue;
            }
            if (!is_uploaded_file($tmp)) { $errors[] = 'Ungültiger Upload.'; continue; }

            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($tmp) ?: 'application/octet-stream';
            if (!in_array($mime, cfg('uploads.allowed_mimes', []), true)) {
                $errors[] = "Dateityp von '" . e($name) . "' nicht erlaubt (nur PDF).";
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, cfg('uploads.allowed_ext', []), true)) {
                $errors[] = "Endung von '" . e($name) . "' nicht erlaubt.";
                continue;
            }
            // PDF-Magic
            $head = (string)file_get_contents($tmp, false, null, 0, 5);
            if ($head !== '%PDF-') {
                $errors[] = "Datei '" . e($name) . "' ist keine gültige PDF.";
                continue;
            }
            $filesToStore[] = ['tmp' => $tmp, 'name' => $name, 'size' => $size, 'mime' => $mime];
        }
    }

    // --- Speichern + Mails versenden ---
    if (!$errors) {
        try {
            $pdo = DB::conn();

            $attachmentsMeta = [];
            foreach ($filesToStore as $f) {
                $storedId = bin2hex(random_bytes(16));
                $storedPath = UPLOAD_PATH . '/' . $storedId . '.bin';
                Crypto::encryptFile($f['tmp'], $storedPath);
                $attachmentsMeta[] = [
                    'id'   => $storedId,
                    'name' => $f['name'],
                    'size' => $f['size'],
                    'mime' => $f['mime'],
                ];
            }

            $st = $pdo->prepare('INSERT INTO applications
                (created_at, first_name_enc, last_name_enc, email_enc, phone_enc, position_enc, message_enc, attachments_enc, ip_hash)
                VALUES (?,?,?,?,?,?,?,?,?)');
            $st->execute([
                time(),
                Crypto::encrypt($firstName),
                Crypto::encrypt($lastName),
                Crypto::encrypt($email),
                $phone !== '' ? Crypto::encrypt($phone) : null,
                Crypto::encrypt($position),
                Crypto::encrypt($message),
                $attachmentsMeta ? Crypto::encrypt(json_encode($attachmentsMeta, JSON_UNESCAPED_UNICODE)) : null,
                Auth::ipHash(),
            ]);

            // E-Mail an Bewerber
            $companyName = cfg('company_name', '');
            $contactMail = cfg('contact_email', '');
            $contactPhone = cfg('contact_phone', '');
            $applicantHtml = render_applicant_mail($firstName, $position, $companyName, $contactMail, $contactPhone);
            Mailer::send([$email], 'Deine Bewerbung bei ' . $companyName, $applicantHtml);

            // Admin-Mail
            $admins = cfg('admin_notify_emails', []);
            if ($admins) {
                $adminHtml = render_admin_mail($firstName, $lastName, $email, $phone, $position, $message, count($attachmentsMeta));
                Mailer::send($admins, '[Bewerbung] ' . $firstName . ' ' . $lastName . ' – ' . $position, $adminHtml);
            }

            unset($_SESSION['form_ts']);
            $success = true;

        } catch (Throwable $e) {
            error_log('[submit] ' . $e->getMessage());
            $errors[] = 'Interner Fehler beim Speichern. Bitte später erneut versuchen.';
        }
    }
}

function render_applicant_mail(string $firstName, string $position, string $company, string $mail, string $phone): string {
    $fn = e($firstName); $pos = e($position); $co = e($company); $m = e($mail); $ph = e($phone);
    $logo = e(cfg('company_name', ''));
    $primary = e(cfg('colors.primary', '#4f46e5'));
    return <<<HTML
    <div style="font-family:system-ui,Segoe UI,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:24px">
      <div style="max-width:560px;margin:auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.06)">
        <div style="background:{$primary};color:#fff;padding:28px 28px 20px 28px">
          <div style="font-size:20px;font-weight:700">{$logo}</div>
        </div>
        <div style="padding:28px;color:#0f172a;line-height:1.55">
          <h1 style="margin:0 0 12px;font-size:22px">Danke, {$fn}!</h1>
          <p>Wir haben deine Bewerbung für die Position <strong>{$pos}</strong> erhalten.</p>
          <p>Unser Team schaut sich deine Unterlagen sorgfältig an und meldet sich zeitnah bei dir zurück.</p>
          <p>Solltest du Fragen haben, erreichst du uns jederzeit unter
             <a href="mailto:{$m}" style="color:{$primary}">{$m}</a>
             oder telefonisch unter {$ph}.</p>
          <p style="margin-top:28px">Herzliche Grüße<br>dein Team von {$co}</p>
        </div>
        <div style="background:#f8fafc;padding:14px 28px;color:#64748b;font-size:12px">
          Diese Nachricht wurde automatisch generiert.
        </div>
      </div>
    </div>
    HTML;
}

function render_admin_mail(string $fn, string $ln, string $mail, string $phone, string $pos, string $msg, int $fileCount): string {
    $fn=e($fn); $ln=e($ln); $mail=e($mail); $phone=e($phone ?: '–'); $pos=e($pos);
    $msg=nl2br(e($msg));
    $primary = e(cfg('colors.primary', '#4f46e5'));
    return <<<HTML
    <div style="font-family:system-ui,Segoe UI,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:24px">
      <div style="max-width:620px;margin:auto;background:#fff;border-radius:14px;overflow:hidden">
        <div style="background:{$primary};color:#fff;padding:20px 24px;font-weight:700;font-size:18px">Neue Bewerbung</div>
        <div style="padding:24px;color:#0f172a;line-height:1.55">
          <p><strong>Name:</strong> {$fn} {$ln}<br>
             <strong>E-Mail:</strong> <a href="mailto:{$mail}">{$mail}</a><br>
             <strong>Telefon:</strong> {$phone}<br>
             <strong>Position:</strong> {$pos}<br>
             <strong>Anhänge:</strong> {$fileCount}</p>
          <hr style="border:none;border-top:1px solid #e2e8f0;margin:18px 0">
          <p style="white-space:pre-wrap">{$msg}</p>
          <p style="margin-top:24px;color:#64748b;font-size:13px">Details und Anhänge im Admin-Bereich.</p>
        </div>
      </div>
    </div>
    HTML;
}

$title = cfg('app_name', 'Bewerbung');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= e($title) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
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
<header class="hero">
  <div class="hero-inner">
    <img class="logo" src="<?= e(cfg('logo_path')) ?>" alt="<?= e(cfg('company_name')) ?>">
    <h1 class="fade-in">Werde Teil von <span class="accent"><?= e(cfg('company_name')) ?></span></h1>
    <p class="lead fade-in delay">Wir freuen uns auf deine Bewerbung. Fülle einfach das Formular aus — wir melden uns schnell zurück.</p>
  </div>
</header>

<main class="container">
  <?php if ($success): ?>
    <div class="card success-card fade-in">
      <div class="check-ring"><div class="check"></div></div>
      <h2>Danke für deine Bewerbung!</h2>
      <p>Wir haben deine Unterlagen sicher entgegengenommen und eine Bestätigung an deine E-Mail geschickt.</p>
      <a href="index.php" class="btn btn-ghost">Neue Bewerbung senden</a>
    </div>
  <?php else: ?>
    <form class="card form" method="post" enctype="multipart/form-data" novalidate autocomplete="on">
      <?= Auth::csrfField() ?>
      <!-- Honeypot -->
      <div class="hp" aria-hidden="true">
        <label>Website (leer lassen)</label>
        <input type="text" name="website" tabindex="-1" autocomplete="off">
      </div>

      <?php if ($errors): ?>
        <div class="alert">
          <strong>Bitte prüfe deine Angaben:</strong>
          <ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach ?></ul>
        </div>
      <?php endif ?>

      <h2>Deine Bewerbung</h2>

      <div class="grid-2">
        <label class="field">
          <span>Vorname *</span>
          <input type="text" name="first_name" required maxlength="80" value="<?= e($_POST['first_name'] ?? '') ?>">
        </label>
        <label class="field">
          <span>Nachname *</span>
          <input type="text" name="last_name" required maxlength="80" value="<?= e($_POST['last_name'] ?? '') ?>">
        </label>
      </div>

      <div class="grid-2">
        <label class="field">
          <span>E-Mail *</span>
          <input type="email" name="email" required maxlength="200" value="<?= e($_POST['email'] ?? '') ?>">
        </label>
        <label class="field">
          <span>Telefon <small>(optional)</small></span>
          <input type="tel" name="phone" maxlength="30" value="<?= e($_POST['phone'] ?? '') ?>">
        </label>
      </div>

      <label class="field">
        <span>Gewünschte Position *</span>
        <select name="position" required>
          <option value="">– Bitte wählen –</option>
          <?php foreach (cfg('positions', []) as $p): ?>
            <option value="<?= e($p) ?>" <?= (($_POST['position'] ?? '') === $p) ? 'selected' : '' ?>><?= e($p) ?></option>
          <?php endforeach ?>
        </select>
      </label>

      <label class="field">
        <span>Anschreiben / Kurzvorstellung *</span>
        <textarea name="message" rows="7" required minlength="10" maxlength="4000"
          placeholder="Erzähl uns, warum du zu uns passt …"><?= e($_POST['message'] ?? '') ?></textarea>
        <small class="hint">Max. 4000 Zeichen.</small>
      </label>

      <label class="field">
        <span>Anhänge <small>(PDF, optional — max. <?= (int)cfg('uploads.max_files') ?> Dateien à <?= (int)(cfg('uploads.max_size_bytes') / 1024 / 1024) ?> MB)</small></span>
        <input type="file" name="attachments[]" accept="application/pdf,.pdf" multiple>
      </label>

      <label class="checkbox">
        <input type="checkbox" name="consent" required>
        <span>Ich stimme zu, dass meine Angaben zur Bearbeitung der Bewerbung verarbeitet und verschlüsselt gespeichert werden.</span>
      </label>

      <div class="actions">
        <button type="submit" class="btn btn-primary">
          <span>Bewerbung senden</span>
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>
      </div>
    </form>
  <?php endif ?>

  <footer class="footer">
    <small>© <?= date('Y') ?> <?= e(cfg('company_name')) ?> · <a href="mailto:<?= e(cfg('contact_email')) ?>"><?= e(cfg('contact_email')) ?></a></small>
  </footer>
</main>

<script src="assets/app.js" defer></script>
</body>
</html>
