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

use App\Models\{Customer, Organization};
use App\Models\Reselling\ResalePeriod;
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use Illuminate\Validation\Validator;

/**
 * Rechnungsbezug nie über Kundengrenzen (Feature 152, Review B13): der
 * Rechnungsempfänger der Position (laut Spiegelquelle) muss der Rechnungs-
 * empfänger der Periode sein. Bezugsdialog, Schnellzuordnung und Abgleich
 * prüfen hier.
 */
trait ChecksResaleLineRecipient {
    /**
     * Position aus dem Formularschlüssel ({@see MirrorLine::$key}) — die
     * Quelle, deren Schlüssel er ist, liefert sie; null wenn unbekannt oder
     * nicht aus der aktiven Organisation.
     */
    protected function lineFrom(mixed $key): ?MirrorLine {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $organization instanceof Organization || ! is_scalar($key) || trim((string) $key) === '') {
            return null;
        }

        return app(InvoiceMirror::class)->lineByKey($organization, trim((string) $key));
    }

    /**
     * Prüft Periode und Position: Periode gehört zum Empfänger, Position an
     * denselben Empfänger. Meldet an `period_id` bzw. `line_id`.
     */
    protected function validateLineRecipient(Validator $validator, ?ResalePeriod $period, ?MirrorLine $line, ?Customer $recipient = null): void {
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
        if ($line->recipientCustomerId !== $billedTo->id) {
            $validator->errors()->add('line_id', (string) __('resale.link_error.line_foreign', ['customer' => $billedTo->name]));
        }
    }
}
