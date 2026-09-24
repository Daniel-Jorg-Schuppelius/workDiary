<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullPurchaseOrderGaebExporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Contracts;

use App\Models\Procurement\PurchaseOrder;
use App\Modules\ModuleUnavailableException;
use ERechnungToolkit\Enums\GaebPhase;

final class NullPurchaseOrderGaebExporter implements PurchaseOrderGaebExporter {
    public function export(PurchaseOrder $order, GaebPhase $phase = GaebPhase::Order): array {
        throw ModuleUnavailableException::for('gaeb');
    }
}
