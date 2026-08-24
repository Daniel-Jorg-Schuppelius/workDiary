<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Models\{AssetCompliance\AssetComplianceAssignment, Organization};
use App\Services\AssetCompliance\AssetComplianceService;
use App\Services\Notification\NotificationDispatcher;

/**
 * Prüfpflichten (Feature 075, MVP-285/288): Warnung ab Vorwarnzeit,
 * Einsatzsperren gemäß blocking_mode über das gemeinsame Modell (D12);
 * Logik im AssetComplianceService je Organisation.
 */
class AssetInspectionScan extends AbstractDeadlineScan {
    public function __construct(private readonly AssetComplianceService $service) {}

    public function key(): string {
        return 'asset_inspections';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->sumPerOrganization(
            AssetComplianceAssignment::query()
                ->withoutGlobalScopes()
                ->where('is_active', true),
            fn(Organization $organization): int => $this->service->scanAssignments($organization),
        );
    }
}
