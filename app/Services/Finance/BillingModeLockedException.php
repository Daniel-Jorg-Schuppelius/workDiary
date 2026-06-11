<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingModeLockedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

use App\Enums\Finance\BillingMode;

/**
 * Lokale Rechnungserstellung ist gesperrt, weil ein externes Programm die
 * Fakturierungshoheit hat (Feature 045, „Führendes System").
 */
class BillingModeLockedException extends \RuntimeException {
    public function __construct(public readonly BillingMode $mode) {
        parent::__construct((string) __('finance.error.local_invoicing_locked', [
            'program' => $mode->label(),
        ]));
    }
}
