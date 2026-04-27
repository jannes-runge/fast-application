<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
if (!Auth::csrfCheck($_POST['_csrf'] ?? null)) {
    http_response_code(400); exit('CSRF');
}

$id     = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
if ($id <= 0 || !AppStatus::isValid($status)) {
    http_response_code(400); exit('Ungültige Anfrage.');
}

$pdo = DB::conn();
$st = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) { http_response_code(404); exit('Nicht gefunden.'); }

$old = (string)$row['status'];

try {
    $upd = $pdo->prepare('UPDATE applications SET status = ?, status_changed_at = ? WHERE id = ?');
    $upd->execute([$status, time(), $id]);
} catch (Throwable $e) {
    error_log('[status] ' . $e->getMessage());
    http_response_code(500); exit('Fehler beim Speichern.');
}

// Mail an Bewerber, wenn neuer Status das erfordert und der Status geändert wurde.
if ($old !== $status && in_array($status, ['rejected', 'accepted'], true)) {
    try {
        $firstName = Crypto::decrypt($row['first_name_enc']);
        $email     = Crypto::decrypt($row['email_enc']);
        $position  = Crypto::decrypt($row['position_enc']);
        $companyName = cfg('company_name', '');

        if ($status === 'rejected') {
            Mailer::send([$email],
                'Rückmeldung zu deiner Bewerbung bei ' . $companyName,
                mail_applicant_rejection($firstName, $position));
        } elseif ($status === 'accepted') {
            Mailer::send([$email],
                'Deine Bewerbung bei ' . $companyName,
                mail_applicant_accepted($firstName, $position));
        }
    } catch (Throwable $e) {
        error_log('[status mail] ' . $e->getMessage());
    }
}

header('Location: view.php?id=' . $id);
