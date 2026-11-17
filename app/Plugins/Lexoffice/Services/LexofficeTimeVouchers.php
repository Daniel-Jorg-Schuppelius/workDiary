<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeTimeVouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Invoicing\Contracts\TimeVoucherSource;

/** Lexoffice-Zeitbelege eines Kunden mit dem Zeitraum aus ihrem Belegtext (MVP-1734). */
final class LexofficeTimeVouchers implements TimeVoucherSource {
    public function timeVouchers(Customer $customer): array {
        $found = [];
        $vouchers = ExternalReference::query()
            ->forPlugin((int) $customer->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_VOUCHER)
            ->forReferenceable($customer)
            ->orderBy('id')
            ->get();
        foreach ($vouchers as $voucher) {
            $period = LexofficeMapper::timeVoucherPeriod(is_array($voucher->payload) ? $voucher->payload : []);
            if ($period !== null) {
                $found[] = ['reference' => $voucher, 'label' => "Lexoffice-Zeitbeleg #{$voucher->id}", 'from' => $period['from'], 'to' => $period['to']];
            }
        }

        return $found;
    }
}
