<?php
declare(strict_types=1);

/**
 * AES-256-GCM Wrapper.
 * Master-Key liegt in includes/secrets.php als 32-Byte Binär-String.
 * Jedes encrypt() nutzt einen frischen 12-Byte Nonce.
 * Format (base64): nonce(12) || tag(16) || ciphertext
 */

final class Crypto {
    private const METHOD = 'aes-256-gcm';
    private const NONCE_LEN = 12;
    private const TAG_LEN = 16;

    private static function key(): string {
        global $SECRETS;
        if (!is_array($SECRETS) || empty($SECRETS['master_key'])) {
            throw new RuntimeException('Master-Key nicht initialisiert.');
        }
        $key = base64_decode($SECRETS['master_key'], true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('Master-Key ungültig (muss 32 Byte sein).');
        }
        return $key;
    }

    public static function encrypt(string $plain): string {
        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';
        $ct = openssl_encrypt($plain, self::METHOD, self::key(), OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LEN);
        if ($ct === false) {
            throw new RuntimeException('Verschlüsselung fehlgeschlagen.');
        }
        return base64_encode($nonce . $tag . $ct);
    }

    public static function decrypt(string $blob): string {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < self::NONCE_LEN + self::TAG_LEN) {
            throw new RuntimeException('Ungültiger Ciphertext.');
        }
        $nonce = substr($raw, 0, self::NONCE_LEN);
        $tag   = substr($raw, self::NONCE_LEN, self::TAG_LEN);
        $ct    = substr($raw, self::NONCE_LEN + self::TAG_LEN);
        $pt = openssl_decrypt($ct, self::METHOD, self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($pt === false) {
            throw new RuntimeException('Entschlüsselung fehlgeschlagen.');
        }
        return $pt;
    }

    /**
     * Verschlüsselt eine Datei. Ausgabe: binär-Datei mit struktur nonce(12)||tag(16)||ciphertext.
     */
    public static function encryptFile(string $sourcePath, string $destPath): void {
        $plain = file_get_contents($sourcePath);
        if ($plain === false) throw new RuntimeException('Quelldatei nicht lesbar.');
        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';
        $ct = openssl_encrypt($plain, self::METHOD, self::key(), OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LEN);
        if ($ct === false) throw new RuntimeException('Dateiverschlüsselung fehlgeschlagen.');
        if (file_put_contents($destPath, $nonce . $tag . $ct, LOCK_EX) === false) {
            throw new RuntimeException('Ziel-Datei nicht schreibbar.');
        }
        @chmod($destPath, 0640);
    }

    /**
     * Entschlüsselt eine zuvor mit encryptFile() erzeugte Datei.
     */
    public static function decryptFile(string $sourcePath): string {
        $raw = file_get_contents($sourcePath);
        if ($raw === false || strlen($raw) < self::NONCE_LEN + self::TAG_LEN) {
            throw new RuntimeException('Verschlüsselte Datei defekt.');
        }
        $nonce = substr($raw, 0, self::NONCE_LEN);
        $tag   = substr($raw, self::NONCE_LEN, self::TAG_LEN);
        $ct    = substr($raw, self::NONCE_LEN + self::TAG_LEN);
        $pt = openssl_decrypt($ct, self::METHOD, self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($pt === false) throw new RuntimeException('Dateientschlüsselung fehlgeschlagen.');
        return $pt;
    }
}
