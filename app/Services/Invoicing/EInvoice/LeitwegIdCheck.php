<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeitwegIdCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use ERechnungToolkit\Entities\LeitwegId;
use InvalidArgumentException;

/**
 * Leitweg-ID im Kundenstamm und vor dem Versand (MVP-1687). Die Prüfung nach
 * der KoSIT-Formatspezifikation leistet {@see LeitwegId}; hier steht nur,
 * wann eine Käuferreferenz als Leitweg-ID gilt, und der Grund für die Meldung.
 * BT-10 bleibt sonst frei, weil private Kunden dort eigene Referenzen führen.
 */
final class LeitwegIdCheck {
    /** Kennzahl eines Landes (01–16) oder des Bundes (99), Ziffern, optional Feinadressierung, zweistellige Prüfziffer. */
    private const string SHAPE = '/^(0[1-9]|1[0-6]|99)[0-9]*(-[A-Z0-9]+)?-[0-9]{2}$/';

    /** Sieht die Referenz nach der Vereinheitlichung wie eine Leitweg-ID aus? */
    public static function looksLike(string $reference): bool {
        return preg_match(self::SHAPE, LeitwegId::normalize($reference)) === 1;
    }

    /**
     * Grund, warum der Wert keine gültige Leitweg-ID ist, in der Sprache der
     * Oberfläche; null bei einer gültigen. Die Ausnahmetexte des Toolkits sind
     * nur deutsch, deshalb ordnet die App den Fehler selbst ein.
     */
    public static function problem(string $value): ?string {
        if (LeitwegId::isValid($value)) {
            return null;
        }
        if (preg_match('/^([0-9]+)(?:-([A-Z0-9]+))?-([0-9]{2})$/', LeitwegId::normalize($value), $parts) !== 1) {
            return (string) __('invoicing.leitweg_reason.form');
        }
        try {
            $expected = LeitwegId::checkDigitsFor($parts[1], $parts[2] !== '' ? $parts[2] : null);
        } catch (InvalidArgumentException) {
            return (string) __('invoicing.leitweg_reason.structure');
        }

        return (string) __('invoicing.leitweg_reason.check_digits', ['given' => $parts[3], 'expected' => $expected]);
    }
}
