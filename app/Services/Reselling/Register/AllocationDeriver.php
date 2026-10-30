<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AllocationDeriver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\SubscriptionStatus;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResaleAccountAllocation, ResalePeriod, ResaleSubscription};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Leitet aus der Aufteilung eines Lizenzkontos (Feature 185, MVP-1401) die
 * Abtretungen je Vertrag ab: fremde Halter füllen zuerst den Vertrag mit der
 * größten Menge, dann nach Beginn — für Schub und Schlepp ergibt das
 * „Nordufer 5 aus CNLCON00111" (Jahrestag 18.10. = Rechnungstag). Manuelle
 * Abtretungen bleiben unangetastet und zählen gegen die Kapazität; abgeleitete
 * tragen `sync_status = alloc:<id>` und folgen jeder Änderung der Aufteilung.
 */
final class AllocationDeriver {
    public function __construct(
        private readonly LicenseAccounts $accounts,
        private readonly PeriodPlanner $planner,
    ) {}

    /**
     * Aufteilung eines Kontos auf seine Verträge anwenden.
     *
     * @return array{created: int, updated: int, removed: int, conflicts: list<array{allocation: ResaleAccountAllocation, missing: int}>}
     */
    public function apply(Organization $organization, LicenseAccountGroup $group, ?CarbonImmutable $reference = null): array {
        $reference ??= ResalePeriod::today();
        $result = ['created' => 0, 'updated' => 0, 'removed' => 0, 'conflicts' => []];
        $allocations = ResaleAccountAllocation::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->forAccount($group->provider, $group->account)
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id'])
            ->orderBy('valid_from')->orderBy('id')
            ->get();

        DB::transaction(function () use ($group, $allocations, $reference, &$result): void {
            $contracts = $group->contracts()->sortBy([['quantity', 'desc'], ['starts_on', 'asc'], ['id', 'asc']])->values();
            /** @var array<int, EloquentCollection<int, ResaleSubscription>> $assignmentsByContract */
            $assignmentsByContract = [];
            foreach ($contracts as $contract) {
                $assignmentsByContract[$contract->id] = $contract->assignments()->get();
            }
            /** @var array<string, true> $keep Abgeleitete Abtretungen, die bleiben (Vertrag:Aufteilung) */
            $keep = [];
            foreach ($allocations as $allocation) {
                /** @var list<array{from: CarbonImmutable, until: CarbonImmutable|null, take: int}> $placed Anteile je Vertragsfenster */
                $placed = [];
                /** @var list<array{from: CarbonImmutable, until: CarbonImmutable|null}> $windows Vertragsfenster, in denen die Aufteilung gilt */
                $windows = [];
                foreach ($contracts as $contract) {
                    if ($contract->productKey() !== $allocation->product_key || ! $this->overlapsContract($allocation, $contract)) {
                        continue;
                    }
                    if ($contract->holderKey() === $allocation->holderKey()) {
                        continue; // der eigene Halter des Vertrags bekommt den Rest, keine Abtretung an sich selbst
                    }
                    // Die Menge gilt je Zeitabschnitt: gleichzeitig laufende Verträge teilen sie sich,
                    // Vorgänger und Nachfolger bekommen sie nacheinander je voll.
                    $from = max($contract->starts_on, $allocation->valid_from);
                    $until = $this->endOf($contract, $allocation);
                    $windows[] = ['from' => $from, 'until' => $until];
                    $already = 0;
                    foreach ($placed as $slice) {
                        if ($this->slicesOverlap($from, $until, $slice['from'], $slice['until'])) {
                            $already += $slice['take'];
                        }
                    }
                    $wanted = max(0, $allocation->quantity - $already);
                    $capacity = $contract->quantity - $this->otherAssignedQuantity($assignmentsByContract[$contract->id], $allocation);
                    $take = min($wanted, max(0, $capacity));
                    $existing = $assignmentsByContract[$contract->id]->first(static fn(ResaleSubscription $a): bool => $a->derivedFromAllocationId() === $allocation->id);
                    if ($take <= 0) {
                        continue;
                    }
                    $placed[] = ['from' => $from, 'until' => $until, 'take' => $take];
                    $keep[$contract->id . ':' . $allocation->id] = true;
                    $attributes = [
                        'company_name' => $allocation->holderLabel(),
                        'customer_id' => $allocation->is_own_holding ? null : ($allocation->foreign_customer_id === null ? $allocation->customer_id : null),
                        'foreign_customer_id' => $allocation->is_own_holding ? null : $allocation->foreign_customer_id,
                        'is_own_holding' => $allocation->is_own_holding,
                        'quantity' => $take,
                        'starts_on' => $from->toDateString(),
                        'ends_on' => $until?->toDateString(),
                        'status' => $contract->status,
                        'notes' => $allocation->note,
                    ];
                    if ($existing === null) {
                        $contract->setRelation('assignments', $assignmentsByContract[$contract->id]);
                        $created = ResaleSubscription::query()->create($attributes + [
                            'organization_id' => $contract->organization_id,
                            'parent_id' => $contract->id,
                            'kind' => $contract->kind,
                            'provider' => $contract->provider,
                            'provider_account' => $contract->provider_account,
                            'external_id' => $contract->external_id !== null ? $contract->external_id . '#' . $contract->nextAssignmentSuffix() : null,
                            'label' => $contract->label,
                            'article_ref' => $contract->article_ref,
                            'term_months' => $contract->term_months,
                            'interval' => $contract->interval,
                            'renewal' => $contract->renewal,
                            'purchase_unit_price' => $contract->purchase_unit_price?->getAmount(),
                            'sale_unit_price' => $contract->sale_unit_price?->getAmount(),
                            'currency' => $contract->currency,
                            'sync_status' => ResaleAccountAllocation::DERIVED_PREFIX . $allocation->id,
                        ]);
                        $assignmentsByContract[$contract->id]->push($created);
                        $this->planner->sync($created, $reference);
                        $result['created']++;
                    } else {
                        $existing->fill($attributes);
                        if ($existing->isDirty()) {
                            $existing->save();
                            $this->planner->sync($existing, $reference);
                            $result['updated']++;
                        }
                    }
                }
                // Konflikt: in irgendeinem Vertragsfenster bleibt ein Teil der Aufteilung ohne Vertrag.
                $missing = 0;
                foreach ($windows as $window) {
                    $covered = 0;
                    foreach ($placed as $slice) {
                        if ($this->slicesOverlap($window['from'], $window['until'], $slice['from'], $slice['until'])) {
                            $covered += $slice['take'];
                        }
                    }
                    $missing = max($missing, $allocation->quantity - $covered);
                }
                if ($missing > 0) {
                    $result['conflicts'][] = ['allocation' => $allocation, 'missing' => (int) $missing];
                }
            }
            // Abgeleitete Abtretungen ohne Aufteilung oder ohne Anteil: weg, sofern keine Entscheidung dranhängt.
            foreach ($contracts as $contract) {
                foreach ($assignmentsByContract[$contract->id] as $assignment) {
                    $allocationId = $assignment->derivedFromAllocationId();
                    if ($allocationId === null || isset($keep[$contract->id . ':' . $allocationId])) {
                        continue;
                    }
                    $result['removed'] += $this->retire($assignment, $reference) ? 1 : 0;
                }
                $contract->unsetRelation('assignments');
                $this->planner->sync($contract, $reference);
            }
        });

        return $result;
    }

    /**
     * Konto eines Abos neu ableiten (nach Dialog, Import, Löschen einer Aufteilung).
     *
     * @return array{created: int, updated: int, removed: int, conflicts: list<array{allocation: ResaleAccountAllocation, missing: int}>}
     */
    public function applyFor(Organization $organization, ResaleSubscription $subscription, ?CarbonImmutable $reference = null): array {
        $group = $this->accounts->group($organization, $this->accounts->keyFor($organization, $subscription));

        return $group === null ? ['created' => 0, 'updated' => 0, 'removed' => 0, 'conflicts' => []] : $this->apply($organization, $group, $reference);
    }

    /**
     * Ablösung: die Aufteilung folgt dem Nachfolger, wenn der ein anderes
     * Konto bildet (Telekom → Quality Hosting). Zeilen, die es dort schon
     * gibt (Produkt und Halter), bleiben die des Nachfolgers.
     */
    public function carry(ResaleSubscription $predecessor, ResaleSubscription $successor): void {
        $from = $predecessor->accountKey();
        $to = $successor->accountKey();
        if ($from === null || $to === null || $from === $to) {
            return;
        }
        $rows = ResaleAccountAllocation::query()->withoutGlobalScopes()
            ->where('organization_id', $predecessor->organization_id)
            ->forAccount($predecessor->provider, LicenseAccounts::accountOf($predecessor))
            ->get();
        if ($rows->isEmpty()) {
            return;
        }
        $existing = ResaleAccountAllocation::query()->withoutGlobalScopes()
            ->where('organization_id', $successor->organization_id)
            ->forAccount($successor->provider, LicenseAccounts::accountOf($successor))
            ->get();
        foreach ($rows as $row) {
            $duplicate = $existing->first(static fn(ResaleAccountAllocation $e): bool => $e->product_key === $row->product_key && $e->holderKey() === $row->holderKey());
            if ($duplicate !== null) {
                continue;
            }
            $row->forceFill(['provider' => $successor->provider, 'provider_account' => LicenseAccounts::accountOf($successor)])->save();
        }
    }

    /** Alle abgeleiteten Abtretungen einer gelöschten Aufteilung zurücknehmen. */
    public function removeFor(ResaleAccountAllocation $allocation, ?CarbonImmutable $reference = null): int {
        $reference ??= ResalePeriod::today();
        $removed = 0;
        $parents = [];
        foreach (ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $allocation->organization_id)->where('sync_status', ResaleAccountAllocation::DERIVED_PREFIX . $allocation->id)->get() as $assignment) {
            $parents[(int) $assignment->parent_id] = true;
            $removed += $this->retire($assignment, $reference) ? 1 : 0;
        }
        foreach (array_keys($parents) as $parentId) {
            $parent = ResaleSubscription::query()->withoutGlobalScopes()->find($parentId);
            if ($parent !== null) {
                $this->planner->sync($parent, $reference);
            }
        }

        return $removed;
    }

    /** Abgeleitete Abtretung entfernen; mit entschiedenen Perioden endet sie stattdessen am Stichtag. */
    private function retire(ResaleSubscription $assignment, CarbonImmutable $reference): bool {
        $hasDecisions = $assignment->periods()->whereNotNull('decided_at')->exists();
        if (! $hasDecisions) {
            $assignment->periods()->delete();
            $assignment->delete();

            return true;
        }
        $assignment->forceFill(['ends_on' => $reference->toDateString(), 'status' => SubscriptionStatus::Ended, 'sync_status' => null])->save();
        $this->planner->sync($assignment, $reference);

        return true;
    }

    /** Zwei Zeitabschnitte überschneiden sich; der Übergabetag eines Nachfolgers (Ende = Beginn) zählt nicht. */
    private function slicesOverlap(CarbonImmutable $aFrom, ?CarbonImmutable $aUntil, CarbonImmutable $bFrom, ?CarbonImmutable $bUntil): bool {
        $aOpen = $aUntil === null || $aUntil->greaterThan($bFrom);
        $bOpen = $bUntil === null || $bUntil->greaterThan($aFrom);

        return $aOpen && $bOpen;
    }

    private function overlapsContract(ResaleAccountAllocation $allocation, ResaleSubscription $contract): bool {
        return $allocation->overlaps($contract->starts_on, $contract->ends_on);
    }

    private function endOf(ResaleSubscription $contract, ResaleAccountAllocation $allocation): ?CarbonImmutable {
        if ($contract->ends_on === null) {
            return $allocation->valid_until;
        }
        if ($allocation->valid_until === null) {
            return $contract->ends_on;
        }

        return min($contract->ends_on, $allocation->valid_until);
    }

    /**
     * Lizenzen des Vertrags, die andere Abtretungen (manuell oder aus anderen Aufteilungen) im Zeitraum der Aufteilung schon halten.
     *
     * @param  EloquentCollection<int, ResaleSubscription>  $assignments
     */
    private function otherAssignedQuantity(EloquentCollection $assignments, ResaleAccountAllocation $allocation): int {
        $sum = 0;
        foreach ($assignments as $assignment) {
            if ($assignment->derivedFromAllocationId() === $allocation->id) {
                continue;
            }
            if ($allocation->overlaps($assignment->starts_on, $assignment->ends_on)) {
                $sum += $assignment->quantity;
            }
        }

        return $sum;
    }
}
