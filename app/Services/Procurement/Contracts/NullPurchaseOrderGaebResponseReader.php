<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullPurchaseOrderGaebResponseReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Contracts;

use App\Services\Procurement\Dto\OrderResponseData;

final class NullPurchaseOrderGaebResponseReader implements PurchaseOrderGaebResponseReader {
    public function read(string $content): ?OrderResponseData {
        return null;
    }
}
