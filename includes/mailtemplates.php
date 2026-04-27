<?php
declare(strict_types=1);

/**
 * Zentrale Mail-Templates. Alle Templates nutzen die in includes/config.php
 * gepflegten Branding-Werte (Firma, Kontakt, Primärfarbe).
 */

function mail_layout(string $title, string $bodyHtml): string {
    $primary = e(cfg('colors.primary', '#2563eb'));
    $company = e(cfg('company_name', ''));
    return <<<HTML
    <div style="font-family:system-ui,Segoe UI,Helvetica,Arial,sans-serif;background:#f8fafc;padding:24px;color:#0f172a">
      <div style="max-width:600px;margin:auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb">
        <div style="background:{$primary};color:#fff;padding:22px 28px;font-weight:700;font-size:18px">{$company}</div>
        <div style="padding:28px;line-height:1.6">{$bodyHtml}</div>
        <div style="background:#f8fafc;padding:14px 28px;color:#64748b;font-size:12px;border-top:1px solid #e5e7eb">
          Diese Nachricht wurde automatisch generiert.
        </div>
      </div>
    </div>
    HTML;
}

function mail_applicant_confirm(string $firstName, string $position): string {
    $fn = e($firstName); $pos = e($position);
    $co = e(cfg('company_name', ''));
    $m  = e(cfg('contact_email', ''));
    $ph = e(cfg('contact_phone', ''));
    $primary = e(cfg('colors.primary', '#2563eb'));
    return mail_layout('Bestätigung', <<<HTML
        <h1 style="margin:0 0 12px;font-size:22px">Danke, {$fn}!</h1>
        <p>Wir haben deine Bewerbung für die Position <strong>{$pos}</strong> erhalten.</p>
        <p>Unser Team schaut sich deine Unterlagen sorgfältig an und meldet sich zeitnah bei dir zurück.</p>
        <p>Solltest du Fragen haben, erreichst du uns jederzeit unter
           <a href="mailto:{$m}" style="color:{$primary}">{$m}</a>
           oder telefonisch unter {$ph}.</p>
        <p style="margin-top:24px">Herzliche Grüße<br>dein Team von {$co}</p>
        HTML);
}

function mail_admin_new_application(string $fn, string $ln, string $mail, string $phone, string $pos, string $msg, int $fileCount): string {
    $fn=e($fn); $ln=e($ln); $mail=e($mail); $phone=e($phone !== '' ? $phone : '–'); $pos=e($pos);
    $msg=nl2br(e($msg));
    return mail_layout('Neue Bewerbung', <<<HTML
        <h1 style="margin:0 0 12px;font-size:20px">Neue Bewerbung eingegangen</h1>
        <p><strong>Name:</strong> {$fn} {$ln}<br>
           <strong>E-Mail:</strong> <a href="mailto:{$mail}">{$mail}</a><br>
           <strong>Telefon:</strong> {$phone}<br>
           <strong>Position:</strong> {$pos}<br>
           <strong>Anhänge:</strong> {$fileCount}</p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:18px 0">
        <p style="white-space:pre-wrap">{$msg}</p>
        <p style="margin-top:24px;color:#64748b;font-size:13px">Details und Anhänge im Admin-Bereich.</p>
        HTML);
}

function mail_applicant_rejection(string $firstName, string $position): string {
    $fn = e($firstName); $pos = e($position);
    $co = e(cfg('company_name', ''));
    $m  = e(cfg('contact_email', ''));
    return mail_layout('Rückmeldung', <<<HTML
        <h1 style="margin:0 0 12px;font-size:22px">Hallo {$fn},</h1>
        <p>vielen Dank für dein Interesse an {$co} und für die Zeit, die du in deine Bewerbung
           für die Position <strong>{$pos}</strong> investiert hast.</p>
        <p>Wir haben uns deine Unterlagen sorgfältig angesehen, müssen dir aber leider mitteilen,
           dass wir uns dieses Mal für eine andere Bewerbung entschieden haben.</p>
        <p>Diese Entscheidung sagt nichts über deine Qualifikation aus — wir wünschen dir für
           deinen weiteren beruflichen Weg alles Gute und viel Erfolg.</p>
        <p>Bei Rückfragen kannst du dich gerne an
           <a href="mailto:{$m}">{$m}</a> wenden.</p>
        <p style="margin-top:24px">Herzliche Grüße<br>dein Team von {$co}</p>
        HTML);
}

function mail_applicant_accepted(string $firstName, string $position): string {
    $fn = e($firstName); $pos = e($position);
    $co = e(cfg('company_name', ''));
    $m  = e(cfg('contact_email', ''));
    return mail_layout('Glückwunsch', <<<HTML
        <h1 style="margin:0 0 12px;font-size:22px">Hallo {$fn},</h1>
        <p>wir freuen uns sehr, dir mitteilen zu können, dass wir dich für die Position
           <strong>{$pos}</strong> sehr gerne im nächsten Schritt kennenlernen möchten.</p>
        <p>Wir melden uns in Kürze persönlich bei dir, um die nächsten Schritte zu besprechen.</p>
        <p>Bei Fragen erreichst du uns unter <a href="mailto:{$m}">{$m}</a>.</p>
        <p style="margin-top:24px">Herzliche Grüße<br>dein Team von {$co}</p>
        HTML);
}
