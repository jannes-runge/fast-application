<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$id = (int)($_GET['id'] ?? 0);
$fid = (string)($_GET['f'] ?? '');
if ($id <= 0 || !preg_match('/^[a-f0-9]{32}$/', $fid)) {
    http_response_code(400);
    echo 'Ungültige Anfrage.';
    exit;
}

$pdo = DB::conn();
$st = $pdo->prepare('SELECT attachments_enc FROM applications WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row || !$row['attachments_enc']) {
    http_response_code(404); echo 'Nicht gefunden.'; exit;
}

try {
    $meta = json_decode(Crypto::decrypt($row['attachments_enc']), true) ?: [];
} catch (Throwable $e) {
    http_response_code(500); echo 'Fehler.'; exit;
}

$match = null;
foreach ($meta as $m) {
    if (($m['id'] ?? '') === $fid) { $match = $m; break; }
}
if (!$match) { http_response_code(404); echo 'Nicht gefunden.'; exit; }

$path = UPLOAD_PATH . '/' . $fid . '.bin';
if (!is_file($path)) { http_response_code(404); echo 'Datei fehlt.'; exit; }

try {
    $plain = Crypto::decryptFile($path);
} catch (Throwable $e) {
    http_response_code(500); echo 'Entschlüsselung fehlgeschlagen.'; exit;
}

$safeName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string)$match['name']) ?: 'datei.pdf';
header('Content-Type: ' . ($match['mime'] ?? 'application/pdf'));
header('Content-Length: ' . strlen($plain));
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $plain;
