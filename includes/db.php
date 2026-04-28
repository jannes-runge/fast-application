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

        // Inkrementelle Migrationen
        $cols = self::$pdo->query("PRAGMA table_info(applications)")->fetchAll();
        $names = array_column($cols, 'name');
        if (!in_array('status', $names, true)) {
            self::$pdo->exec("ALTER TABLE applications ADD COLUMN status TEXT NOT NULL DEFAULT 'new'");
        }
        if (!in_array('status_changed_at', $names, true)) {
            self::$pdo->exec("ALTER TABLE applications ADD COLUMN status_changed_at INTEGER");
        }
        // DSGVO / Bewerber-Pool
        if (!in_array('pool_status', $names, true)) {
            self::$pdo->exec("ALTER TABLE applications ADD COLUMN pool_status TEXT NOT NULL DEFAULT 'none'");
        }
        if (!in_array('pool_token', $names, true)) {
            self::$pdo->exec("ALTER TABLE applications ADD COLUMN pool_token TEXT");
        }
        if (!in_array('pool_token_expires', $names, true)) {
            self::$pdo->exec("ALTER TABLE applications ADD COLUMN pool_token_expires INTEGER");
        }
        if (!in_array('pool_invited_at', $names, true)) {
            self::$pdo->exec("ALTER TABLE applications ADD COLUMN pool_invited_at INTEGER");
        }
        if (!in_array('pool_confirmed_at', $names, true)) {
            self::$pdo->exec("ALTER TABLE applications ADD COLUMN pool_confirmed_at INTEGER");
        }

        // Audit-Log für DSGVO-Nachvollziehbarkeit
        self::$pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at INTEGER NOT NULL,
                admin_id INTEGER,
                admin_user TEXT,
                action TEXT NOT NULL,
                target_type TEXT,
                target_id TEXT,
                details TEXT,
                ip_hash TEXT
            );
            CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_audit_target ON audit_log(target_type, target_id);
        SQL);

        // Tabelle für pflegbare Seiten (Impressum, Datenschutz, …)
        self::$pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS pages (
                slug TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                content_html TEXT NOT NULL DEFAULT '',
                updated_at INTEGER NOT NULL
            );
        SQL);

        // Default-Seiten anlegen, falls leer
        $existing = self::$pdo->query('SELECT slug FROM pages')->fetchAll(PDO::FETCH_COLUMN);
        $defaults = [
            'impressum' => [
                'title' => 'Impressum',
                'html'  => "<h1>Impressum</h1>"
                         . "<h2>Angaben gem&auml;&szlig; &sect; 5 TMG</h2>"
                         . "<p>[Firmenname]<br>[Stra&szlig;e Hausnummer]<br>[PLZ Ort]</p>"
                         . "<h2>Vertreten durch</h2><p>[Name der Gesch&auml;ftsf&uuml;hrung]</p>"
                         . "<h2>Kontakt</h2><p>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail]</p>"
                         . "<h2>Umsatzsteuer-ID</h2><p>Umsatzsteuer-Identifikationsnummer gem&auml;&szlig; &sect; 27a Umsatzsteuergesetz: [USt-IdNr.]</p>"
                         . "<h2>Verantwortlich f&uuml;r den Inhalt nach &sect; 55 Abs. 2 RStV</h2><p>[Name]<br>[Anschrift]</p>"
                         . "<p><em>Bitte diesen Platzhalter im Admin-Bereich unter &bdquo;Inhalte&ldquo; an deine Firmendaten anpassen.</em></p>",
            ],
            'datenschutz' => [
                'title' => 'Datenschutzerklärung',
                'html'  => "<h1>Datenschutzerkl&auml;rung</h1>"
                         . "<h2>1. Verantwortlicher</h2>"
                         . "<p>[Firmenname]<br>[Anschrift]<br>E-Mail: [E-Mail]</p>"
                         . "<h2>2. Erhebung und Verarbeitung personenbezogener Daten</h2>"
                         . "<p>Wenn du dich &uuml;ber das Bewerbungsformular bei uns bewirbst, verarbeiten wir die von dir &uuml;bermittelten Daten "
                         . "(Vorname, Nachname, E-Mail, Telefon, gew&uuml;nschte Position, Anschreiben, Anh&auml;nge) ausschlie&szlig;lich zur Bearbeitung deiner Bewerbung. "
                         . "Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO sowie &sect; 26 BDSG.</p>"
                         . "<h2>3. Speicherung und L&ouml;schung</h2>"
                         . "<p>Alle Bewerbungsdaten werden verschl&uuml;sselt (AES-256) gespeichert. Wenn es nicht zu einer Anstellung kommt, "
                         . "werden deine Daten sp&auml;testens 6 Monate nach Abschluss des Bewerbungsverfahrens gel&ouml;scht.</p>"
                         . "<h2>4. Deine Rechte</h2>"
                         . "<p>Du hast jederzeit das Recht auf Auskunft, Berichtigung, L&ouml;schung, Einschr&auml;nkung der Verarbeitung, "
                         . "Daten&uuml;bertragbarkeit sowie Widerspruch. Wende dich daf&uuml;r per E-Mail an [E-Mail].</p>"
                         . "<h2>5. Beschwerderecht</h2>"
                         . "<p>Du hast das Recht, dich bei der zust&auml;ndigen Datenschutzaufsichtsbeh&ouml;rde zu beschweren.</p>"
                         . "<p><em>Bitte diesen Platzhalter im Admin-Bereich unter &bdquo;Inhalte&ldquo; an deine konkreten Verh&auml;ltnisse anpassen.</em></p>",
            ],
        ];
        foreach ($defaults as $slug => $d) {
            if (in_array($slug, $existing, true)) continue;
            $st = self::$pdo->prepare('INSERT INTO pages (slug, title, content_html, updated_at) VALUES (?,?,?,?)');
            $st->execute([$slug, $d['title'], $d['html'], time()]);
        }
    }
}

/**
 * Erlaubte Bewerbungs-Status mit deutschem Label und Badge-Klasse.
 */
final class AppStatus {
    public const ALL = [
        'new'       => ['label' => 'Neu',          'class' => 'st-new'],
        'contacted' => ['label' => 'Kontaktiert',  'class' => 'st-contacted'],
        'accepted'  => ['label' => 'Angenommen',   'class' => 'st-accepted'],
        'rejected'  => ['label' => 'Abgelehnt',    'class' => 'st-rejected'],
    ];
    public static function isValid(string $s): bool { return isset(self::ALL[$s]); }
    public static function label(string $s): string { return self::ALL[$s]['label'] ?? $s; }
    public static function cssClass(string $s): string { return self::ALL[$s]['class'] ?? ''; }
}

/**
 * Bewerber-Pool: Status mit Label.
 */
final class PoolStatus {
    public const NONE      = 'none';
    public const PENDING   = 'pending';
    public const CONFIRMED = 'confirmed';
    public const DECLINED  = 'declined';

    public const LABELS = [
        self::NONE      => '–',
        self::PENDING   => 'Einladung versendet',
        self::CONFIRMED => 'Im Pool',
        self::DECLINED  => 'Pool abgelehnt',
    ];

    public static function label(string $s): string { return self::LABELS[$s] ?? $s; }
}
