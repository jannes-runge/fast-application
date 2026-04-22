<?php
declare(strict_types=1);

/**
 * Auth-Helpers: CSRF, Admin-Login, Rate-Limit, Lockout.
 */
final class Auth {

    /* ---------------- CSRF ---------------- */

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfCheck(?string $token): bool {
        return isset($_SESSION['csrf'], $token) && hash_equals($_SESSION['csrf'], (string)$token);
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES) . '">';
    }

    /* ---------------- Admin-Login ---------------- */

    public static function login(string $username, string $password): bool {
        $pdo = DB::conn();
        $st = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        $row = $st->fetch();

        $ok = $row && password_verify($password, $row['password_hash']);
        self::recordLoginAttempt($username, $ok);

        if (!$ok) return false;

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$row['id'];
        $_SESSION['admin_user'] = $row['username'];
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool {
        return !empty($_SESSION['admin_id']);
    }

    public static function require(): void {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /* ---------------- Lockout / Bruteforce ---------------- */

    public static function recordLoginAttempt(string $username, bool $success): void {
        $pdo = DB::conn();
        $st = $pdo->prepare('INSERT INTO login_attempts (ip_hash, username, success, created_at) VALUES (?,?,?,?)');
        $st->execute([self::ipHash(), $username, $success ? 1 : 0, time()]);
    }

    public static function isLockedOut(): bool {
        global $CONFIG;
        $max  = (int)$CONFIG['security']['login_max_attempts'];
        $mins = (int)$CONFIG['security']['login_lockout_min'];
        $since = time() - ($mins * 60);
        $pdo = DB::conn();
        $st = $pdo->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE ip_hash = ? AND success = 0 AND created_at > ?');
        $st->execute([self::ipHash(), $since]);
        return (int)$st->fetch()['c'] >= $max;
    }

    /* ---------------- Submit-Rate-Limit ---------------- */

    public static function hitRateLimit(string $bucket, string $key, int $windowSec, int $max): bool {
        $pdo = DB::conn();
        $now = time();
        $pdo->prepare('DELETE FROM rate_limits WHERE created_at < ?')
            ->execute([$now - max(3600, $windowSec)]);

        $kh = hash('sha256', $key);
        $st = $pdo->prepare('SELECT COUNT(*) AS c FROM rate_limits WHERE bucket = ? AND key_hash = ? AND created_at > ?');
        $st->execute([$bucket, $kh, $now - $windowSec]);
        if ((int)$st->fetch()['c'] >= $max) return false;

        $pdo->prepare('INSERT INTO rate_limits (bucket, key_hash, created_at) VALUES (?,?,?)')
            ->execute([$bucket, $kh, $now]);
        return true;
    }

    public static function ipHash(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return hash('sha256', $ip);
    }
}
