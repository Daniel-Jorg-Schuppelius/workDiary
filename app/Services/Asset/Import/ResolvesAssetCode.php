<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesAssetCode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Import;

use App\Models\Asset\Asset;
use App\Models\Platform\Organization;

/** Gerät einer Importzeile über Asset-, Inventar- oder Seriennummer (Zählerstände, Messwerte, Positionen). */
trait ResolvesAssetCode {
    private function assetByCode(Organization $organization, string $code): ?Asset {
        return Asset::query()->where('organization_id', $organization->id)
            ->where(fn ($q) => $q->where('asset_no', $code)->orWhere('inventory_no', $code)->orWhere('serial_no', $code))
            ->first();
    }
}
