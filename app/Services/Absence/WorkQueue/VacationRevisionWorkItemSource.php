<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationRevisionWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Absence\WorkQueue;

use App\Enums\User\Permission;
use App\Enums\Vacation\VacationStatus;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Absence\Vacation;
use App\Models\Platform\{Organization, User};
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;

/**
 * Eigene Urlaubsanträge, die zur Korrektur zurückgegeben wurden (MVP-1554):
 * eine Aufgabe der antragstellenden Person, keine Genehmigung.
 */
final class VacationRevisionWorkItemSource implements WorkItemSource {
    public const string KEY = 'vacation_revision';

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('work-queue.source.vacation_revision');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return $viewer->can(Permission::VacationRequest->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $returned = Vacation::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $viewer->id)
            ->where('status', VacationStatus::Revision)
            ->orderBy('start_date')
            ->get();

        foreach ($returned as $vacation) {
            yield new WorkItem(
                source: $this->key(),
                key: $vacation->getMorphClass() . ':' . $vacation->id,
                title: (string) __('work-queue.item.vacation_revision'),
                url: route('duties.index', ['tab' => 'urlaub', 'vstatus' => VacationStatus::Revision->value]),
                detail: CarbonFmt::fdate($vacation->start_date) . ' – ' . CarbonFmt::fdate($vacation->end_date),
                dueAt: CarbonImmutable::instance($vacation->start_date),
                // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                movedAt: WorkItem::lastMoved($vacation->updated_at),
            );
        }
    }
}
