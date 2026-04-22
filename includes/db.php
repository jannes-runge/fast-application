<?php
declare(strict_types=1);

/**
 * SQLite-DB Wrapper. Legt Schema beim ersten Zugriff an.
 */
final class DB {
    private static ?PDO $pdo = null;

    public static function conn(): PDO {
        if (self::$pdo !== null) return self::$pdo;

        $dbFile = DATA_PATH . '/app.db';
        $dsn = 'sqlite:' . $dbFile;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        self::$pdo = $pdo;
        self::migrate();
        @chmod($dbFile, 0640);
        return $pdo;
    }

    private static function migrate(): void {
        self::$pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at INTEGER NOT NULL,
                first_name_enc TEXT NOT NULL,
                last_name_enc  TEXT NOT NULL,
                email_enc      TEXT NOT NULL,
                phone_enc      TEXT,
                position_enc   TEXT NOT NULL,
                message_enc    TEXT NOT NULL,
                attachments_enc TEXT,
                ip_hash TEXT
            );
            CREATE INDEX IF NOT EXISTS idx_apps_created ON applications(created_at DESC);

            CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bucket TEXT NOT NULL,
                key_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_rl ON rate_limits(bucket, key_hash, created_at);

            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_hash TEXT NOT NULL,
                username TEXT,
                success INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_la ON login_attempts(ip_hash, created_at);
        SQL);
    }
}
