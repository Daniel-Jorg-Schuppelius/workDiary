<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillRetainerVouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services\Retainer;

use App\Enums\Billing\AccountPaymentSource;
use App\Enums\Finance\BillingMode;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillTarget;
use App\Services\Billing\Retainer\MirroredRetainerVouchers;

/** Zahlstand der easybill-Pauschalen aus dem Spiegel (MVP-1505): `paid_amount` und `cancel_id` des Dokuments. */
final class EasybillRetainerVouchers extends MirroredRetainerVouchers {
    public function pluginId(): string {
        return EasybillPlugin::ID;
    }

    protected function label(): string {
        return BillingMode::Easybill->programName();
    }

    protected function paymentSource(): AccountPaymentSource {
        return AccountPaymentSource::Easybill;
    }

    protected function invoiceExtType(): string {
        return EasybillTarget::EXT_TYPE_INVOICE;
    }
}
