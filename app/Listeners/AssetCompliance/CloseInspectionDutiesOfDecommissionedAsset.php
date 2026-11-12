<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloseInspectionDutiesOfDecommissionedAsset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\AssetCompliance;

use App\Events\Asset\AssetDecommissioned;
use App\Listeners\ModuleListener;
use App\Models\Platform\User;
use App\Services\AssetCompliance\AssetComplianceService;

/** Außer Betrieb genommenes Asset: Prüfpflichten ruhen, offene Prüftermine sind storniert. */
final class CloseInspectionDutiesOfDecommissionedAsset extends ModuleListener {
    public function __construct(private readonly AssetComplianceService $service) {}

    protected function module(): string {
        return 'asset_compliance';
    }

    public function handle(AssetDecommissioned $event): void {
        if (! $this->shouldHandle((int) $event->asset->organization_id)) {
            return;
        }

        $actor = $event->actorUserId !== null ? User::query()->find($event->actorUserId) : null;
        $this->service->closeForDecommissionedAsset($event->asset, $actor);
    }
}
