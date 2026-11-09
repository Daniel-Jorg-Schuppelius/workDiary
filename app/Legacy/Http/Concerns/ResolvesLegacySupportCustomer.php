<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesLegacySupportCustomer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Concerns;

use App\Legacy\Http\Middleware\EnsureLegacySupportCustomer;
use App\Legacy\Models\LegacySupportCustomer;
use Illuminate\Http\Request;

trait ResolvesLegacySupportCustomer {
    /** Der von {@see EnsureLegacySupportCustomer} geprüfte Kunde der Sitzung. */
    private function supportCustomer(Request $request): LegacySupportCustomer {
        $customer = $request->attributes->get(EnsureLegacySupportCustomer::REQUEST_ATTRIBUTE);
        abort_unless($customer instanceof LegacySupportCustomer, 403);

        return $customer;
    }
}
