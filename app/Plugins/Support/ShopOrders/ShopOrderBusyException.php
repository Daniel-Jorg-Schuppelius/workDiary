<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrderBusyException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use RuntimeException;

final class ShopOrderBusyException extends RuntimeException {
    public static function for(string $pluginId, int $organizationId): self {
        return new self(sprintf('Shop-Import %s für Organisation #%d läuft bereits.', $pluginId, $organizationId));
    }
}
