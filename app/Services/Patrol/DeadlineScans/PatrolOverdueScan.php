<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolOverdueScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Patrol\DeadlineScans;

use App\Enums\Patrol\PatrolRunStatus;
use App\Models\Patrol\PatrolRun;
use App\Models\Platform\Organization;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use App\Services\Patrol\PatrolService;

/** Nicht beendete Rundgänge nach Soll-Dauer plus Toleranz (Feature 089, MVP-1611) — einmal je Lauf als offener Punkt. */
final class PatrolOverdueScan extends AbstractDeadlineScan {
    public function __construct(private readonly PatrolService $service) {}

    public function key(): string {
        return 'patrol_overdue';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->sumPerOrganization(
            PatrolRun::query()
                ->withoutGlobalScopes()
                ->where('status', PatrolRunStatus::Running->value)
                ->whereNull('overdue_flagged_at'),
            fn (Organization $organization): int => $this->service->flagOverdueRuns($organization),
        );
    }
}
