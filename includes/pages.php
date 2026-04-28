<?php
declare(strict_types=1);

/**
 * Pflegbare Inhaltsseiten (Impressum, Datenschutz, ...).
 */
final class Pages {
    public static function get(string $slug): ?array {
        $st = DB::conn()->prepare('SELECT * FROM pages WHERE slug = ?');
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function all(): array {
        return DB::conn()->query('SELECT slug, title, updated_at FROM pages ORDER BY title ASC')->fetchAll();
    }

    public static function save(string $slug, string $title, string $html): void {
        $st = DB::conn()->prepare('UPDATE pages SET title = ?, content_html = ?, updated_at = ? WHERE slug = ?');
        $st->execute([$title, $html, time(), $slug]);
    }
}

/**
 * Großzügiger HTML-Sanitizer für eingeloggte Admins.
 * Lässt fast alle Struktur-Tags (h1-h6, p, ul/ol/li, table, address, hr, span, div, a, ...) zu.
 * Strippt aber gefährliche Inhalte: <script>/<style>/<iframe>/<object>/<embed>/<form>/<noscript>
 * inkl. ihres Inhalts, alle on*-Eventhandler sowie javascript:/data:/vbscript:-URLs.
 */
function sanitize_page_html(string $html): string {
    // Kommentare entfernen (können CSP-Bypass / Conditional-Comments enthalten)
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    // Gefährliche Tags inkl. Inhalt killen
    $html = preg_replace('#<(script|style|iframe|object|embed|form|noscript|template)\b[^>]*>.*?</\1\s*>#is', '', $html);
    // Selbstschließende Varianten / unvollständige
    $html = preg_replace('#<(script|style|iframe|object|embed|form|noscript|template)\b[^>]*/?\s*>#is', '', $html);
    // Inline-Eventhandler (onclick=, onerror=, ...)
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // javascript:/data:/vbscript: in href/src/action neutralisieren
    $html = preg_replace(
        '/\b(href|src|action|formaction)\s*=\s*("|\')\s*(javascript|data|vbscript)\s*:/i',
        '$1=$2#blocked:',
        $html
    );
    return trim($html);
}
