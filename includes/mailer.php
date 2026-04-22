<?php
declare(strict_types=1);

/**
 * Minimalistischer SMTP-Client (keine externen Dependencies).
 * Unterstützt STARTTLS/SSL + AUTH LOGIN, HTML-Mails mit Plain-Text-Fallback.
 */
final class Mailer {

    public static function send(array $to, string $subject, string $htmlBody, string $textBody = ''): bool {
        global $CONFIG;
        $s = $CONFIG['smtp'];
        if ($textBody === '') {
            $textBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)) ?? '');
        }

        $scheme = ($s['encryption'] === 'ssl') ? 'ssl://' : '';
        $host = $scheme . $s['host'];
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client($host . ':' . $s['port'], $errno, $errstr, (int)$s['timeout']);
        if (!$fp) {
            error_log("[Mailer] Connect fehlgeschlagen: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($fp, (int)$s['timeout']);

        try {
            self::expect($fp, 220);
            $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
            self::cmd($fp, "EHLO $ehloHost", 250);

            if ($s['encryption'] === 'tls') {
                self::cmd($fp, "STARTTLS", 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS fehlgeschlagen');
                }
                self::cmd($fp, "EHLO $ehloHost", 250);
            }

            if ($s['username'] !== '' && $s['password'] !== '') {
                self::cmd($fp, "AUTH LOGIN", 334);
                self::cmd($fp, base64_encode($s['username']), 334);
                self::cmd($fp, base64_encode($s['password']), 235);
            }

            self::cmd($fp, 'MAIL FROM:<' . $s['from_email'] . '>', 250);
            foreach ($to as $rcpt) {
                self::cmd($fp, 'RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            self::cmd($fp, 'DATA', 354);

            $headers = self::buildHeaders($to, $subject);
            [$body, $boundary] = self::buildBody($htmlBody, $textBody);
            $msg = $headers . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n" . $body;

            // Dot-Stuffing
            $msg = preg_replace('/^\./m', '..', $msg);
            fwrite($fp, $msg . "\r\n.\r\n");
            self::expect($fp, 250);
            self::cmd($fp, 'QUIT', [221, 250]);
            fclose($fp);
            return true;
        } catch (Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            @fclose($fp);
            return false;
        }
    }

    private static function buildHeaders(array $to, string $subject): string {
        global $CONFIG;
        $s = $CONFIG['smtp'];
        $from = sprintf('"%s" <%s>', self::mimeHeader($s['from_name']), $s['from_email']);
        $h  = "From: $from\r\n";
        $h .= 'To: ' . implode(', ', array_map(fn($x) => "<$x>", $to)) . "\r\n";
        $h .= 'Subject: ' . self::mimeHeader($subject) . "\r\n";
        $h .= 'Date: ' . date('r') . "\r\n";
        $h .= "MIME-Version: 1.0\r\n";
        $h .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\n";
        return $h;
    }

    private static function buildBody(string $html, string $text): array {
        $boundary = '=_' . bin2hex(random_bytes(12));
        $b  = "--$boundary\r\n";
        $b .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $b .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $b .= chunk_split(base64_encode($text)) . "\r\n";
        $b .= "--$boundary\r\n";
        $b .= "Content-Type: text/html; charset=UTF-8\r\n";
        $b .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $b .= chunk_split(base64_encode($html)) . "\r\n";
        $b .= "--$boundary--\r\n";
        return [$b, $boundary];
    }

    private static function mimeHeader(string $s): string {
        if (preg_match('/[^\x20-\x7e]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }

    /** @param int|int[] $expect */
    private static function cmd($fp, string $cmd, $expect): void {
        fwrite($fp, $cmd . "\r\n");
        self::expect($fp, $expect);
    }

    /** @param int|int[] $expect */
    private static function expect($fp, $expect): void {
        $expected = is_array($expect) ? $expect : [$expect];
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException("SMTP: erwartet " . implode('/', $expected) . ", erhalten: " . trim($resp));
        }
    }
}
