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
use ERechnungToolkit\Enums\LeitwegIdFailure;
use ERechnungToolkit\Exceptions\InvalidLeitwegIdException;

/**
 * Grund einer ungültigen Leitweg-ID in der Sprache der Oberfläche (MVP-1687,
 * MVP-1699): Prüfung und Fehlerart liefert {@see LeitwegId}, dessen Texte nur
 * deutsch sind; hier steht nur die Übersetzung der Fehlerart.
 */
final class LeitwegIdCheck {
    /** null bei einer gültigen Leitweg-ID. */
    public static function problem(string $value): ?string {
        try {
            LeitwegId::of($value);
        } catch (InvalidLeitwegIdException $e) {
            return match ($e->getReason()) {
                LeitwegIdFailure::FORM => (string) __('invoicing.leitweg_reason.form'),
                LeitwegIdFailure::CHECK_DIGITS => (string) __('invoicing.leitweg_reason.check_digits', [
                    'given' => (string) $e->getGivenCheckDigits(),
                    'expected' => (string) $e->getExpectedCheckDigits(),
                ]),
                LeitwegIdFailure::COARSE_ADDRESS_LENGTH, LeitwegIdFailure::STATE_CODE, LeitwegIdFailure::FINE_ADDRESS => (string) __('invoicing.leitweg_reason.structure'),
            };
        }

        return null;
    }
}
