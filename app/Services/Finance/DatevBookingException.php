<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

/**
 * Fachlicher Fehler beim DATEV-Buchungsexport (Feature 045, Priorität 2):
 * leere Quellauswahl, fehlgeschlagener Preflight, Doppel-Übergabe oder
 * Veränderungsversuch an einem finalisierten Stapel. Gleiches Muster wie
 * {@see BillingTransferException}.
 */
class DatevBookingException extends \RuntimeException {
    /** @param  array<string, mixed>  $context */
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
