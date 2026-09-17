<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegalHoldException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions\Privacy;

use App\Models\Privacy\LegalHold;
use RuntimeException;

/**
 * Löschen oder Anonymisieren trifft Daten unter Legal Hold (MVP-801). Basis
 * RuntimeException, damit vorhandene Generalfänger (Aufbewahrungs-Entscheidung)
 * die Meldung anzeigen statt 500 zu liefern.
 */
class LegalHoldException extends RuntimeException {
    public function __construct(public readonly LegalHold $hold) {
        parent::__construct((string) __('Gesperrt durch Legal Hold seit :date — Löschen und Anonymisieren sind bis zur Aufhebung ausgeschlossen.', [
            'date' => $hold->placed_at->format('d.m.Y'),
        ]));
    }
}
