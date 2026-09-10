<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksResaleLineRecipient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Concerns;

use App\Models\{Customer, LexofficeVoucherLine};
use App\Models\Reselling\ResalePeriod;
use App\Plugins\Lexoffice\Services\LexofficeContactMap;
use Illuminate\Validation\Validator;

/**
 * Rechnungsbezug nie über Kundengrenzen (Feature 152, Review B13): die
 * Position muss zu einem Lexoffice-Kontakt des Rechnungsempfängers der
 * Periode gehören. Bezugsdialog, Schnellzuordnung und Abgleich prüfen hier.
 */
trait ChecksResaleLineRecipient {
    /** Position aus der dekodierten ID (mit Beleg geladen), null wenn unbekannt. */
    protected function lineFrom(mixed $id): ?LexofficeVoucherLine {
        return is_numeric($id) && (int) $id > 0 ? LexofficeVoucherLine::query()->with('voucher')->find((int) $id) : null;
    }

    /**
     * Prüft Periode und Position: Periode gehört zum Empfänger, Position zu
     * einem seiner Kontakte. Meldet an `period_id` bzw. `line_id`.
     */
    protected function validateLineRecipient(Validator $validator, ?ResalePeriod $period, ?LexofficeVoucherLine $line, ?Customer $recipient = null): void {
        if ($period === null) {
            $validator->errors()->add('period_id', (string) __('resale.link_error.period_missing'));

            return;
        }
        $billedTo = $period->subscription->billedTo();
        if ($billedTo === null || ($recipient !== null && $billedTo->id !== $recipient->id)) {
            $validator->errors()->add('period_id', (string) __('resale.link_error.period_foreign'));

            return;
        }
        if ($line === null) {
            $validator->errors()->add('line_id', (string) __('resale.link.error.line_missing'));

            return;
        }
        $contacts = LexofficeContactMap::forCustomer($billedTo)->byCustomer($billedTo->id);
        if (! in_array((string) $line->voucher->contact_external_id, $contacts, true)) {
            $validator->errors()->add('line_id', (string) __('resale.link_error.line_foreign', ['customer' => $billedTo->name]));
        }
    }
}
