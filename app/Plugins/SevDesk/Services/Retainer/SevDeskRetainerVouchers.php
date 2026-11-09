<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskRetainerVouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services\Retainer;

use App\Enums\Billing\AccountPaymentSource;
use App\Enums\Finance\BillingMode;
use App\Plugins\SevDesk\Services\{SevDeskTarget, SevDeskVoucherPullService};
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\Retainer\MirroredRetainerVouchers;

/** Zahlstand der sevDesk-Pauschalen aus dem Spiegel (MVP-1505); Rechnungen tragen dort das Präfix `invoice:`. */
final class SevDeskRetainerVouchers extends MirroredRetainerVouchers {
    public function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    protected function label(): string {
        return BillingMode::SevDesk->programName();
    }

    protected function paymentSource(): AccountPaymentSource {
        return AccountPaymentSource::SevDesk;
    }

    protected function invoiceExtType(): string {
        return SevDeskTarget::EXT_TYPE_INVOICE;
    }

    protected function mirrorId(string $remoteId): string {
        return SevDeskVoucherPullService::INVOICE_PREFIX . $remoteId;
    }
}
