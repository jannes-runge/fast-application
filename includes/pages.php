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
 * Erlaubt eine kleine HTML-Teilmenge (p, h2-h4, ul/ol/li, b/strong, i/em, a, br, blockquote).
 * Strippt alles andere und entfernt event-Handler / javascript: URLs.
 */
function sanitize_page_html(string $html): string {
    $allowed = '<p><br><strong><em><b><i><u><h2><h3><h4><ul><ol><li><a><blockquote>';
    $html = strip_tags($html, $allowed);
    // Inline-Eventhandler entfernen
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // javascript:/data: URLs in href entschärfen
    $html = preg_replace_callback(
        '/<a\b([^>]*)>/i',
        function ($m) {
            $attrs = $m[1];
            if (preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $hm)) {
                $url = $hm[2] ?? $hm[3] ?? $hm[4] ?? '';
                if (!preg_match('#^(https?://|mailto:|/|#)#i', $url)) {
                    $attrs = preg_replace('/href\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', 'href="#"', $attrs);
                }
            }
            // External-Sicherheit
            if (preg_match('#href\s*=\s*["\']https?://#i', $attrs)) {
                $attrs = preg_replace('/\s+(target|rel)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
                $attrs .= ' target="_blank" rel="noopener noreferrer"';
            }
            return '<a' . $attrs . '>';
        },
        $html
    );
    return trim($html);
}
