<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullAssetComplianceStatusProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Contracts;

use App\Enums\AssetCompliance\AssetComplianceStatus;
use App\Models\Asset\Asset;

final class NullAssetComplianceStatusProvider implements AssetComplianceStatusProvider {
    public function statusFor(Asset $asset): AssetComplianceStatus {
        return AssetComplianceStatus::NotApplicable;
    }

    public function syncOverdueBlocks(Asset $asset): int {
        return 0;
    }
}
