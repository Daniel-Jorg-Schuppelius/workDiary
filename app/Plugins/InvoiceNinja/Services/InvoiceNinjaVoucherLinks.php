<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja\Services;

use App\Models\Finance\AccountingVoucher;
use App\Plugins\InvoiceNinja\InvoiceNinjaPlugin;
use App\Services\Billing\Feed\MirroredVoucherLinks;

/** Belegfluss: Invoice-Ninja-Belege verlinken zum Original. */
final class InvoiceNinjaVoucherLinks extends MirroredVoucherLinks {
    protected function pluginId(): string {
        return InvoiceNinjaPlugin::ID;
    }

    protected function voucherUrl(AccountingVoucher $voucher): ?string {
        return app(InvoiceNinjaVoucherPullService::class)->voucherUrl($voucher);
    }
}
