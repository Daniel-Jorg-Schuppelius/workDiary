<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompleteCustomerDeadlinesOnOrderCompleted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Customer;

use App\Events\Diary\OrderCompleted;
use App\Listeners\ModuleListener;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\Organization;
use App\Services\Customer\Deadlines\CustomerDeadlineService;
use App\Support\OrganizationContext;

/**
 * Ein abgeschlossener Auftrag mit Periode erledigt die zugeordneten
 * Kundenfristen derselben Periode (Feature 197, MVP-1632) — etwa ein Auftrag
 * `beratungsbesuch` 2026-H1 den Beratungsbesuch des ersten Halbjahrs.
 */
final class CompleteCustomerDeadlinesOnOrderCompleted extends ModuleListener {
    public function __construct(private readonly CustomerDeadlineService $deadlines) {}

    protected function module(): string {
        return 'customer';
    }

    public function handle(OrderCompleted $event): void {
        if (! $this->shouldHandle($event->organizationId)) {
            return;
        }
        $entry = DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $event->organizationId)
            ->with('classifications')
            ->find($event->diaryEntryId);
        $organization = Organization::query()->find($event->organizationId);
        if ($entry === null || $entry->period_key === null || $entry->customer_id === null || ! $organization instanceof Organization) {
            return;
        }

        OrganizationContext::run($organization, fn (): int => $this->deadlines->completeForOrder($entry));
    }
}
