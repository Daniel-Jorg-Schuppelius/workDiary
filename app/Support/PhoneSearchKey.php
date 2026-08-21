<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneSearchKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use CommonToolkit\Helper\Data\PhoneNumberHelper;

/**
 * Normalisierte Rufnummer als Suchschlüssel (Folgepunkt aus Audit-Welle 2.4).
 *
 * Eine Stelle für die Regel, weil sie an zwei Enden gelten muss: beim
 * Speichern der Stammdaten ({@see \App\Models\Concerns\HasPhoneSearchKeys})
 * und beim Nachschlagen einer eingehenden Nummer
 * ({@see \App\Services\Contacts\PhoneNumberMatcher}). Liefen die beiden
 * auseinander, fände der Abgleich nichts mehr — und niemand würde es merken,
 * weil ein nicht erkannter Anrufer wie ein unbekannter Anrufer aussieht.
 */
final class PhoneSearchKey {
    /** Spaltenbreite der `*_e164`-Spalten. */
    public const MAX_LENGTH = 24;

    /** Nicht deutbare Eingaben ergeben null — geraten wird nichts. */
    public static function of(?string $value): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $e164 = PhoneNumberHelper::toE164($value, 'DE');

        return ($e164 === null || $e164 === '') ? null : mb_substr($e164, 0, self::MAX_LENGTH);
    }
}
