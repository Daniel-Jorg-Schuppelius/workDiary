<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceDeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Models\{AssetFinance\AssetFinanceDeadline, Organization};
use App\Services\AssetFinance\AssetFinanceService;
use App\Services\Notification\NotificationDispatcher;

/**
 * Leasing-/Vertragsfristen (Feature 074, MVP-273/278): Warnung ab
 * Vorwarnzeit + Eskalation; Logik im AssetFinanceService je Organisation.
 */
class AssetFinanceDeadlineScan extends AbstractDeadlineScan {
    public function __construct(private readonly AssetFinanceService $service) {}

    public function key(): string {
        return 'asset_finance';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->sumPerOrganization(
            AssetFinanceDeadline::query()
                ->withoutGlobalScopes()
                ->where('status', 'open'),
            fn(Organization $organization): int => $this->service->scanDeadlines($organization),
        );
    }
}
