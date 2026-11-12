<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderInvoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary;

use App\Enums\Diary\Status;
use App\Models\Diary\DiaryEntry;
use App\Models\Material\MaterialUsage;
use App\Models\Time\TimeEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * „Berechnet“ durch den Beleg (MVP-1596): Eine Rechnung oder Übergabe, die Zeiten
 * oder Material (MVP-1624) eines abgenommenen Auftrags abrechnet, setzt ihn auf
 * „Berechnet“; ihr Storno nimmt das zurück, solange kein anderer Beleg Zeiten oder
 * Material des Auftrags hält. Material gehört über seinen Stundenzettel zu den
 * Aufträgen, deren Zeiten auf diesem Zettel stehen — dieselbe Zuordnung wie in
 * der Auftragsakte.
 */
class OrderInvoicing {
    public function __construct(private readonly OrderService $orders) {}

    /**
     * @param  list<int>  $timeEntryIds  abgerechnete Zeiten
     * @param  array<string, mixed>  $origin  Herkunft für das Journal
     * @param  list<int>  $materialUsageIds  abgerechnetes Material
     * @return int Anzahl der Aufträge, die auf „Berechnet“ gewechselt sind
     */
    public function markBilled(int $organizationId, array $timeEntryIds, string $reference, array $origin, array $materialUsageIds = []): int {
        $changed = 0;
        foreach ($this->ordersOf($organizationId, $timeEntryIds, $materialUsageIds, Status::AcceptedFinal) as $order) {
            $this->orders->markInvoiced($order, null, $reference, $origin);
            $changed++;
        }

        return $changed;
    }

    /**
     * Nur Aufträge, die genau dieser Beleg berechnet hat — eine Referenz von Hand bleibt stehen.
     *
     * @param  list<int>  $timeEntryIds
     * @param  list<int>  $materialUsageIds
     */
    public function release(int $organizationId, array $timeEntryIds, string $reference, array $materialUsageIds = []): int {
        $changed = 0;
        foreach ($this->ordersOf($organizationId, $timeEntryIds, $materialUsageIds, Status::Invoiced) as $order) {
            if ($order->invoice_reference !== mb_substr($reference, 0, 120)) {
                continue;
            }
            // `exported` bzw. `billed` trägt, was noch ein Beleg hält; der Storno hat seines schon freigegeben.
            if (TimeEntry::query()->where('diary_entry_id', $order->id)->where('exported', true)->exists()
                || MaterialUsage::query()->where('billed', true)->whereIn('timesheet_id', TimeEntry::query()->where('diary_entry_id', $order->id)->whereNotNull('timesheet_id')->select('timesheet_id'))->exists()) {
                continue;
            }
            $this->orders->revertInvoiced($order, null, $reference);
            $changed++;
        }

        return $changed;
    }

    /**
     * @param  list<int>  $timeEntryIds
     * @param  list<int>  $materialUsageIds
     * @return \Illuminate\Database\Eloquent\Collection<int, DiaryEntry>
     */
    private function ordersOf(int $organizationId, array $timeEntryIds, array $materialUsageIds, Status $status): \Illuminate\Database\Eloquent\Collection {
        if ($timeEntryIds === [] && $materialUsageIds === []) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return DiaryEntry::query()
            ->where('organization_id', $organizationId)
            ->where('status', $status->value)
            ->where(fn (Builder $query) => $query
                ->whereIn('id', TimeEntry::query()->whereKey($timeEntryIds)->whereNotNull('diary_entry_id')->select('diary_entry_id'))
                ->orWhereIn('id', TimeEntry::query()
                    ->whereNotNull('diary_entry_id')
                    ->whereIn('timesheet_id', MaterialUsage::query()->whereKey($materialUsageIds)->whereNotNull('timesheet_id')->select('timesheet_id'))
                    ->select('diary_entry_id')))
            ->get();
    }
}
