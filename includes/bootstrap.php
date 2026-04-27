<?php
declare(strict_types=1);

/**
 * Wird von jedem Entry-Point inkludiert.
 * Setzt Pfade, lädt Config & Secrets, konfiguriert Session & Security-Header.
 */

define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('UPLOAD_PATH', DATA_PATH . '/uploads');
define('INCLUDES_PATH', __DIR__);

// ---------- Globales Error-Handling ----------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(DATA_PATH)) { @mkdir(DATA_PATH, 0770, true); }
ini_set('error_log', DATA_PATH . '/app-error.log');

function _app_error_page(string $ref): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Fehler</title>';
    echo '<body style="font-family:system-ui;padding:2rem;max-width:40rem;margin:auto;color:#0f172a">';
    echo '<h1>Es ist ein Fehler aufgetreten</h1>';
    echo '<p>Bitte versuche es in wenigen Minuten erneut.</p>';
    echo '<p style="color:#64748b;font-size:.9rem">Referenz: <code>' . htmlspecialchars($ref) . '</code><br>';
    echo 'Details stehen in <code>data/app-error.log</code>.</p>';
    echo '</body>';
}

set_exception_handler(function (Throwable $e): void {
    $ref = substr(bin2hex(random_bytes(4)), 0, 8);
    error_log("[$ref] Uncaught " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    _app_error_page($ref);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $ref = substr(bin2hex(random_bytes(4)), 0, 8);
        error_log("[$ref] Fatal: {$e['message']} @ {$e['file']}:{$e['line']}");
        _app_error_page($ref);
    }
});

if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0770, true);
}

// .htaccess im data-Verzeichnis sicherstellen (zusätzlich zum root-.htaccess)
$dataHt = DATA_PATH . '/.htaccess';
if (!file_exists($dataHt)) {
    @file_put_contents($dataHt, "Require all denied\nDeny from all\n");
}

// ---------- Konfiguration laden ----------
// includes/config.default.php = Template (im git getracked).
// includes/config.php         = Live-Konfig auf dem Server (ge-gitignored).
// Pull/Push überschreibt damit niemals die User-Anpassungen.
$configDefault = INCLUDES_PATH . '/config.default.php';
$configLive    = INCLUDES_PATH . '/config.php';
if (!file_exists($configLive) && file_exists($configDefault)) {
    @copy($configDefault, $configLive);
    @chmod($configLive, 0640);
}
$defaults = file_exists($configDefault) ? require $configDefault : [];
$user     = file_exists($configLive)    ? require $configLive    : [];
$CONFIG   = array_replace_recursive(is_array($defaults) ? $defaults : [],
                                    is_array($user)     ? $user     : []);

// Logo-Auto-Seed: liefert assets/logo.svg aus, falls der User noch keins abgelegt hat.
$logoLive    = BASE_PATH . '/assets/logo.svg';
$logoDefault = BASE_PATH . '/assets/logo.default.svg';
if (!file_exists($logoLive) && file_exists($logoDefault)) {
    @copy($logoDefault, $logoLive);
}

$secretsFile = INCLUDES_PATH . '/secrets.php';
$SECRETS = file_exists($secretsFile) ? require $secretsFile : null;

// Bei fehlendem Secret nur install.php zulassen.
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($SECRETS === null && $script !== 'install.php') {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Setup nötig</title>';
    echo '<body style="font-family:system-ui;padding:2rem;max-width:40rem;margin:auto">';
    echo '<h1>Setup erforderlich</h1>';
    echo '<p>Bitte rufe einmalig <a href="install.php">install.php</a> auf, um den Krypto-Schlüssel zu erzeugen und den ersten Admin anzulegen.</p>';
    echo '</body>';
    exit;
}

// SMTP-Passwort ggf. aus Umgebung ziehen
if (($envPw = getenv('SMTP_PASSWORD')) !== false && $envPw !== '') {
    $CONFIG['smtp']['password'] = $envPw;
}

// --- Security Header ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// --- Session sicher konfigurieren ---
if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('APPSID');
    session_start();
}

// Session-Timeout
$lifetime = (int)($CONFIG['security']['session_lifetime'] ?? 3600);
if (isset($_SESSION['admin_id'], $_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) > $lifetime) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
if (isset($_SESSION['admin_id'])) {
    $_SESSION['last_activity'] = time();
}

require_once INCLUDES_PATH . '/crypto.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/mailer.php';
require_once INCLUDES_PATH . '/mailtemplates.php';

/**
 * HTML-Escape Shortcut.
 */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Gibt Config-Wert per Dot-Notation zurück.
 */
function cfg(string $path, $default = null) {
    global $CONFIG;
    $ref = $CONFIG;
    foreach (explode('.', $path) as $k) {
        if (!is_array($ref) || !array_key_exists($k, $ref)) return $default;
        $ref = $ref[$k];
    }
    return $ref;
}
