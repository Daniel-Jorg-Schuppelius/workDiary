<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Iban.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * IBAN-Normalisierung und Blind-Index (Feature 045, „Datenschutz · Matching
 * über unverschlüsselte Ableitungen"). Verschlüsselte IBAN-Felder sind nicht
 * SQL-durchsuchbar; der Abgleich läuft ausschließlich über diesen Hash.
 */
final class Iban {
    /** Entfernt Whitespace und vereinheitlicht Großschreibung. */
    public static function normalize(?string $iban): ?string {
        if ($iban === null) {
            return null;
        }
        $normalized = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        return $normalized === '' ? null : $normalized;
    }

    /**
     * SHA-256-Blindindex der normalisierten IBAN (plaintext-Spalte, kein
     * Personenbezug in der Ableitung). Liefert null für leere Eingaben.
     */
    public static function hash(?string $iban): ?string {
        $normalized = self::normalize($iban);

        return $normalized === null ? null : hash('sha256', $normalized);
    }
}
