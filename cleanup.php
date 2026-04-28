<?php
declare(strict_types=1);

/**
 * Auto-Löschung nach DSGVO-Aufbewahrungsfrist.
 *
 * - CLI:  php cleanup.php
 * - Web:  https://example.com/cleanup.php?token=<gdpr.cleanup_token>
 *
 * Löscht alle Bewerbungen, die älter als gdpr.retention_days sind UND nicht
 * im Pool bestätigt sind. Anhänge auf der Platte werden mit entfernt.
 * Außerdem werden abgelaufene Pool-Einladungen aufgeräumt und alte
 * Audit-Log-Einträge (> 2 Jahre) sowie Login-Versuche (> 90 Tage) aussortiert.
 */

require __DIR__ . '/includes/bootstrap.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    $expected = (string)cfg('gdpr.cleanup_token', '');
    $given    = (string)($_GET['token'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit("403 Forbidden – Token fehlt oder ungültig.\nKonfiguriere gdpr.cleanup_token in includes/config.php\nund hänge ihn als ?token=… an.\n");
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$pdo = DB::conn();
$retentionDays = max(1, (int)cfg('gdpr.retention_days', 180));
$cutoff = time() - $retentionDays * 86400;

// 1) Bewerbungen löschen, die zu alt sind und nicht im Pool bestätigt sind
$st = $pdo->prepare(
    "SELECT id FROM applications
      WHERE created_at < ?
        AND pool_status != 'confirmed'"
);
$st->execute([$cutoff]);
$ids = $st->fetchAll(PDO::FETCH_COLUMN);
$deletedApps = 0;
foreach ($ids as $id) {
    if (Applications::delete((int)$id, 'auto_retention')) $deletedApps++;
}

// 2) Abgelaufene Pool-Einladungen zurücksetzen
$resetInv = $pdo->prepare(
    "UPDATE applications
        SET pool_status = 'none', pool_token = NULL, pool_token_expires = NULL
      WHERE pool_status = 'pending' AND pool_token_expires IS NOT NULL AND pool_token_expires < ?"
);
$resetInv->execute([time()]);
$expiredInvites = $resetInv->rowCount();

// 3) Alte Audit-Logs aufräumen (nach 2 Jahren)
$auditCutoff = time() - 2 * 365 * 86400;
$delAudit = $pdo->prepare('DELETE FROM audit_log WHERE created_at < ?');
$delAudit->execute([$auditCutoff]);
$deletedAudit = $delAudit->rowCount();

// 4) Login-Versuche (älter als 90 Tage) aufräumen
$delLA = $pdo->prepare('DELETE FROM login_attempts WHERE created_at < ?');
$delLA->execute([time() - 90 * 86400]);
$deletedLA = $delLA->rowCount();

Audit::log('cleanup.run', null, null, [
    'retention_days'  => $retentionDays,
    'deleted_apps'    => $deletedApps,
    'expired_invites' => $expiredInvites,
    'pruned_audit'    => $deletedAudit,
    'pruned_logins'   => $deletedLA,
]);

$report = sprintf(
    "Cleanup abgeschlossen\n"
  . "  Aufbewahrung:           %d Tage (Cutoff: %s)\n"
  . "  Gelöschte Bewerbungen:  %d\n"
  . "  Abgelaufene Pool-Inv.:  %d\n"
  . "  Audit-Log gekürzt:      %d Einträge (>2 Jahre)\n"
  . "  Login-Versuche gekürzt: %d Einträge (>90 Tage)\n",
    $retentionDays, date('c', $cutoff), $deletedApps, $expiredInvites, $deletedAudit, $deletedLA
);

echo $report;
