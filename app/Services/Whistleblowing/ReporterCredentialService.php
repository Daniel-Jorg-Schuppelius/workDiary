<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReporterCredentialService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use RuntimeException;
use SensitiveParameter;

/**
 * Erzeugt und prueft die Zugangsdaten fuer das anonyme Postfach (Abschnitt
 * 7.2 / 9.2 / 25).
 *
 *   case_number : niedrig-entropische Anzeige-Referenz (WD-XXXX-XXXX),
 *                 NIE ein Login-Eingabefeld.
 *   secret      : hochentropisch (>=128 Bit). Wird nur einmal im Klartext
 *                 ausgegeben, als Argon2id-Hash gespeichert (access_code_hash)
 *                 und ueber einen getrennten HMAC-Key auffindbar gemacht
 *                 (access_code_lookup) – Login erfolgt ausschliesslich ueber das
 *                 Geheimnis, nicht ueber den Fallcode.
 */
class ReporterCredentialService {
    /** Verwechslungsarmes Alphabet (ohne 0/O/1/I). */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function generateCaseNumber(): string {
        return 'WD-' . $this->randomToken(4) . '-' . $this->randomToken(4);
    }

    /** 160-Bit-Geheimnis, gruppiert dargestellt (z. B. 5x 8 Zeichen). */
    public function generateSecret(): string {
        $groups = [];
        for ($i = 0; $i < 5; $i++) {
            $groups[] = $this->randomToken(8);
        }

        return implode('-', $groups);
    }

    public function hashSecret(#[SensitiveParameter] string $secret): string {
        return password_hash($secret, PASSWORD_ARGON2ID);
    }

    public function verifySecret(#[SensitiveParameter] string $secret, string $hash): bool {
        return password_verify($secret, $hash);
    }

    /**
     * Deterministischer HMAC fuer die gezielte Suche nach dem Fall – ohne den
     * Klartext oder den Verschluesselungs-Key zu beruehren.
     */
    public function lookupHmac(#[SensitiveParameter] string $secret): string {
        return hash_hmac('sha256', $this->normalize($secret), $this->lookupKey());
    }

    /**
     * Dummy-Argon2 fuer den Miss-Fall, damit der Postfach-Login bei
     * unbekanntem Geheimnis nicht durch fehlende Hash-Arbeit verraten wird
     * (moeglichst konstante Zeit, Abschnitt 19/25).
     */
    public function performDummyVerify(): void {
        password_verify('x', '$argon2id$v=19$m=65536,t=4,p=1$'
            . 'YWFhYWFhYWFhYWFhYWFhYQ$' // konstantes Salt – nur Zeitverbrauch
            . 'k1pVd0d0b0o5b0o5b0o5b0o5b0o5b0o5b0o5b0o5b0o');
    }

    private function normalize(string $secret): string {
        return strtoupper(str_replace('-', '', trim($secret)));
    }

    private function randomToken(int $length): string {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Eigener HMAC-Key (getrennt vom Verschluesselungs-Key). Faellt – falls
     * nicht separat gesetzt – auf einen aus dem Modul-Key abgeleiteten Wert
     * zurueck. Fehlt auch der Modul-Key, verweigert der Service den Dienst.
     */
    private function lookupKey(): string {
        $explicit = (string) config('whistleblowing.lookup_key');
        if ($explicit !== '') {
            return $explicit;
        }

        $moduleKey = (string) config('whistleblowing.key');
        if ($moduleKey === '') {
            throw new RuntimeException('Weder WHISTLEBLOWING_LOOKUP_KEY noch WHISTLEBLOWING_KEY sind gesetzt.');
        }

        return hash_hmac('sha256', 'lookup', $moduleKey);
    }
}
