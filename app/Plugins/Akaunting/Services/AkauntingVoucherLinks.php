<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting\Services;

use App\Models\Finance\AccountingVoucher;
use App\Plugins\Akaunting\AkauntingPlugin;
use App\Services\Billing\Feed\MirroredVoucherLinks;

/** Belegfluss: Akaunting-Belege verlinken zum Original. */
final class AkauntingVoucherLinks extends MirroredVoucherLinks {
    protected function pluginId(): string {
        return AkauntingPlugin::ID;
    }

    protected function voucherUrl(AccountingVoucher $voucher): ?string {
        return app(AkauntingVoucherPullService::class)->voucherUrl($voucher);
    }
}
