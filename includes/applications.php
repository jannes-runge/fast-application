<?php
declare(strict_types=1);

/**
 * Helper für Bewerbungs-Operationen (Löschen mit Anhängen, Audit-Logging).
 */
final class Applications {
    /**
     * Löscht eine Bewerbung samt Anhängen unwiderruflich.
     * Logt die Aktion mit Grund (manual/auto/pool_decline) ins Audit-Log.
     *
     * @return bool true wenn gelöscht, false wenn nicht gefunden
     */
    public static function delete(int $id, string $reason = 'manual'): bool {
        $pdo = DB::conn();
        $st = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return false;

        // Anhänge entschlüsseln, um Dateinamen / IDs zu kennen
        $files = [];
        if (!empty($row['attachments_enc'])) {
            try {
                $meta = json_decode(Crypto::decrypt($row['attachments_enc']), true);
                if (is_array($meta)) $files = $meta;
            } catch (Throwable $e) {
                // Ignorieren – wir löschen trotzdem
            }
        }
        foreach ($files as $f) {
            if (!isset($f['id'])) continue;
            $path = UPLOAD_PATH . '/' . preg_replace('/[^a-f0-9]/i', '', (string)$f['id']) . '.bin';
            if (is_file($path)) @unlink($path);
        }

        $del = $pdo->prepare('DELETE FROM applications WHERE id = ?');
        $del->execute([$id]);

        Audit::log('application.delete', 'application', (string)$id, [
            'reason'      => $reason,
            'attachments' => count($files),
            'created_at'  => (int)$row['created_at'],
        ]);
        return true;
    }
}
