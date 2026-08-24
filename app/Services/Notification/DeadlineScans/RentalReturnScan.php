<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalReturnScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Rental\RentalCaseStatus;
use App\Models\{Organization, Rental\RentalCase};
use App\Services\Notification\NotificationDispatcher;
use App\Services\Rental\RentalCaseService;

/**
 * Überfällige Verleih-Rückgaben (Feature 073, MVP-264): Statuswechsel
 * auf overdue + idempotente Benachrichtigung/Eskalation laufen im
 * RentalCaseService je Organisation (Nummernkreis-/Audit-Kontext).
 */
class RentalReturnScan extends AbstractDeadlineScan {
    public function __construct(private readonly RentalCaseService $service) {}

    public function key(): string {
        return 'rental_returns';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->sumPerOrganization(
            RentalCase::query()
                ->withoutGlobalScopes()
                ->whereIn('status', [
                    RentalCaseStatus::HandedOver->value,
                    RentalCaseStatus::Overdue->value,
                ]),
            fn(Organization $organization): int => $this->service->escalateOverdue($organization),
        );
    }
}
