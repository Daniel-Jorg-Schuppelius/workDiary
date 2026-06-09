<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingCryptoService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use RuntimeException;
use SensitiveParameter;

/**
 * Envelope-Verschluesselung fuer das Hinweisgebermodul (Abschnitt 10/25).
 *
 *   KEK  = aus WHISTLEBLOWING_KEY abgeleitet (NICHT APP_KEY)
 *   DEK  = per Fall zufaellig erzeugt, mit dem KEK gewrappt (`dek_wrapped`)
 *   Feld = mit dem DEK des Falls verschluesselt
 *
 * Crypto-Shredding: Vernichten von `dek_wrapped` macht alle Falldaten – auch in
 * Backups – unwiederbringlich, ohne Backups anzufassen.
 *
 * Primitive: libsodium secretbox (XSalsa20-Poly1305, authentifiziert).
 * Fail closed: ohne konfigurierten Schluessel verweigert der Service den Dienst.
 */
class WhistleblowingCryptoService {
    private const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;   // 32
    private const NONCE_BYTES = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24

    /** Erzeugt einen neuen, zufaelligen Data Encryption Key fuer einen Fall. */
    public function generateDek(): string {
        return random_bytes(self::KEY_BYTES);
    }

    /** Wrappt (verschluesselt) einen DEK mit dem Modul-KEK → base64. */
    public function wrapDek(#[SensitiveParameter] string $dek): string {
        return $this->seal($dek, $this->kek());
    }

    /** Entpackt einen gewrappten DEK aus `dek_wrapped`. */
    public function unwrapDek(string $wrapped): string {
        return $this->open($wrapped, $this->kek());
    }

    /** Verschluesselt Klartext mit dem DEK des Falls → base64. */
    public function encryptWithDek(string $plaintext, #[SensitiveParameter] string $dek): string {
        return $this->seal($plaintext, $dek);
    }

    /** Entschluesselt einen mit dem Fall-DEK verschluesselten Wert. */
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

    /**
     * Leitet den Key Encryption Key aus dem konfigurierten Modul-Schluessel ab.
     * Akzeptiert base64- oder Roh-32-Byte-Schluessel; andere Laengen werden per
     * SHA-256 auf 32 Byte normalisiert. Fehlt der Schluessel → RuntimeException.
     */
    private function kek(): string {
        $configured = (string) config('whistleblowing.key');
        if ($configured === '') {
            throw new RuntimeException(
                'WHISTLEBLOWING_KEY ist nicht gesetzt – das Hinweisgebermodul verweigert ohne eigenen Schluessel den Dienst.'
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
