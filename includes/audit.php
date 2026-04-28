<?php
declare(strict_types=1);

/**
 * Audit-Log: jede sicherheits- oder DSGVO-relevante Aktion (Login, Status-Änderung,
 * Löschung, Pool-Vorgänge, Inhaltsänderungen, Admin-Verwaltung, Auto-Cleanup).
 */
final class Audit {
    /**
     * @param array<string,mixed>|null $details Wird als JSON gespeichert.
     */
    public static function log(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $details = null
    ): void {
        try {
            $pdo = DB::conn();
            $st = $pdo->prepare(
                'INSERT INTO audit_log (created_at, admin_id, admin_user, action, target_type, target_id, details, ip_hash)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                time(),
                isset($_SESSION['admin_id'])   ? (int)$_SESSION['admin_id']      : null,
                isset($_SESSION['admin_user']) ? (string)$_SESSION['admin_user'] : null,
                $action,
                $targetType,
                $targetId !== null ? (string)$targetId : null,
                $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                Auth::ipHash(),
            ]);
        } catch (Throwable $e) {
            error_log('[audit] ' . $e->getMessage());
        }
    }

    public static function recent(int $limit = 200): array {
        $pdo = DB::conn();
        $st = $pdo->prepare('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ?');
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}
