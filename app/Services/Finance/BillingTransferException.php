<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

/**
 * Fachliche Verletzung der Übergabenachweis-Statusmaschine (Feature 045) —
 * gleiches Muster wie TimeCorrectionWorkflowException.
 */
class BillingTransferException extends \RuntimeException {
    /** @param  array<string, mixed>  $context */
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
