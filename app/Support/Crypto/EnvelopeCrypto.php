<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnvelopeCrypto.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Crypto;

use RuntimeException;
use SensitiveParameter;

/**
 * Wiederverwendbare Envelope-Verschluesselung mit per-Datensatz-DEK und Crypto-
 * Shredding. Modul-unabhaengig: der KEK wird aus einem konfigurierbaren Schluessel
 * abgeleitet (z. B. WHISTLEBLOWING_KEY oder DATAPROTECTION_KEY), getrennt von APP_KEY.
 *
 *   KEK  = aus dem Modul-Schluessel abgeleitet
 *   DEK  = pro Datensatz zufaellig, mit dem KEK gewrappt (`dek_wrapped`)
 *   Feld = mit dem DEK des Datensatzes verschluesselt
 *
 * Crypto-Shredding: Vernichten von `dek_wrapped` macht alle Felddaten – auch in
 * Backups – unwiederbringlich. Primitive: libsodium secretbox (XSalsa20-Poly1305,
 * authentifiziert). Fail closed: ohne konfigurierten Schluessel verweigert der Dienst.
 */
class EnvelopeCrypto {
    private const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;     // 32
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24

    /**
     * @param  string  $keyConfigKey  Config-Pfad zum Modul-Schluessel (z. B. 'dataprotection.key')
     * @param  string  $keyName       Name fuer Fehlermeldungen (z. B. 'DATAPROTECTION_KEY')
     */
    public function __construct(
        private readonly string $keyConfigKey,
        private readonly string $keyName,
    ) {}

    public function generateDek(): string {
        return random_bytes(self::KEY_BYTES);
    }

    public function wrapDek(#[SensitiveParameter] string $dek): string {
        return $this->seal($dek, $this->kek());
    }

    public function unwrapDek(string $wrapped): string {
        return $this->open($wrapped, $this->kek());
    }

    public function encryptWithDek(string $plaintext, #[SensitiveParameter] string $dek): string {
        return $this->seal($plaintext, $dek);
    }

    public function decryptWithDek(string $ciphertext, #[SensitiveParameter] string $dek): string {
        return $this->open($ciphertext, $dek);
    }

    private function seal(string $plaintext, #[SensitiveParameter] string $key): string {
        $nonce = random_bytes(self::NONCE_BYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return base64_encode($nonce . $cipher);
    }

    private function open(string $encoded, #[SensitiveParameter] string $key): string {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= self::NONCE_BYTES) {
            throw new RuntimeException('Ungueltiger Chiffretext.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $cipher = substr($raw, self::NONCE_BYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if ($plain === false) {
            throw new RuntimeException('Entschluesselung fehlgeschlagen (falscher Schluessel oder manipuliert).');
        }

        return $plain;
    }

    private function kek(): string {
        $configured = (string) config($this->keyConfigKey);
        if ($configured === '') {
            throw new RuntimeException(
                "{$this->keyName} ist nicht gesetzt – das Modul verweigert ohne eigenen Schluessel den Dienst."
            );
        }

        $decoded = base64_decode($configured, true);
        if ($decoded !== false && strlen($decoded) === self::KEY_BYTES) {
            return $decoded;
        }
        if (strlen($configured) === self::KEY_BYTES) {
            return $configured;
        }

        return hash('sha256', $configured, true);
    }
}
