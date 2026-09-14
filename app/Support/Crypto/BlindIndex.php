<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BlindIndex.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Crypto;

use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{BankHelper, CryptoHelper};

/**
 * Nachschlage-Abdruck („Blindindex") für verschlüsselt gespeicherte Werte.
 *
 * IBAN, E-Mail-Adresse und Telefondurchwahl liegen verschlüsselt in der
 * Datenbank; damit man sie trotzdem finden kann, steht daneben ein Abdruck.
 * Bis zum Sicherheitsaudit 2026-09-13 war das ein **ungesalzenes SHA-256**.
 * Das ist bei diesen Werten keine Einbahnstraße: Der Raum ist klein und
 * strukturiert (eine deutsche IBAN hat 22 Zeichen mit fester Prüfsumme, eine
 * Durchwahl drei bis fünf Ziffern, Mailadressen kommen aus Listen). Wer die
 * Datenbank liest, rechnet die Abdrücke in Stunden zurück — die Verschlüsselung
 * daneben ist dann wertlos.
 *
 * Deshalb ein geschlüsselter Abdruck (HMAC). Der Schlüssel stammt aus
 * `BLIND_INDEX_KEY`, ersatzweise aus dem `APP_KEY` — dieselbe Abhängigkeit, die
 * die verschlüsselten Spalten ohnehin haben.
 *
 * **Doppellesen:** {@see self::candidates()} liefert zusätzlich den alten,
 * ungeschlüsselten Abdruck. Bestandszeilen bleiben damit auffindbar, bis
 * `security:rehash-blind-indexes` sie umgerechnet hat. Neue Zeilen bekommen
 * ausschließlich den geschlüsselten Wert.
 */
final class BlindIndex {
    /** Geschlüsselter Abdruck eines beliebigen Werts. */
    public static function of(string $value): string {
        return hash_hmac('sha256', $value, self::key());
    }

    /** Alter, ungeschlüsselter Abdruck — nur noch zum Finden von Bestandszeilen. */
    public static function legacy(string $value): string {
        return (string) CryptoHelper::hash($value, HashAlgorithm::SHA256);
    }

    /**
     * Beide Abdrücke für ein `whereIn`. Reihenfolge: neu, alt.
     *
     * @return list<string>
     */
    public static function candidates(string $value): array {
        return [self::of($value), self::legacy($value)];
    }

    /** IBAN in der Normalform des Toolkits (ohne Leerzeichen, Großbuchstaben). */
    public static function ofIban(?string $iban): ?string {
        $normalized = BankHelper::normalizeIBAN($iban);

        return $normalized === null ? null : self::of($normalized);
    }

    /** @return list<string> */
    public static function ibanCandidates(?string $iban): array {
        $normalized = BankHelper::normalizeIBAN($iban);

        return $normalized === null ? [] : self::candidates($normalized);
    }

    /** E-Mail in der bisherigen Normalform (kleingeschrieben, getrimmt). */
    public static function ofEmail(?string $email): ?string {
        $normalized = mb_strtolower(trim((string) $email));

        return $normalized === '' ? null : self::of($normalized);
    }

    /** @return list<string> */
    public static function emailCandidates(?string $email): array {
        $normalized = mb_strtolower(trim((string) $email));

        return $normalized === '' ? [] : self::candidates($normalized);
    }

    /**
     * Der Schlüssel. Eigener Wert, wenn gesetzt; sonst aus dem APP_KEY
     * abgeleitet, damit keine zweite Geheimnisverwaltung nötig ist.
     */
    private static function key(): string {
        static $key = null;
        if (is_string($key)) {
            return $key;
        }

        $configured = (string) config('app.blind_index_key', '');
        if ($configured !== '') {
            return $key = hash_hmac('sha256', 'blind-index', $configured, true);
        }

        $appKey = (string) config('app.key', '');
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            $appKey = $decoded === false ? $appKey : $decoded;
        }

        return $key = hash_hmac('sha256', 'blind-index', $appKey, true);
    }
}
