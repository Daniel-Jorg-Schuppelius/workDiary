<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractIndexationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Contract\{ContractIndexationStatus, IndexationMethod};
use App\Enums\Notification\NotificationEvent;
use App\Models\Contract\{Contract, ContractIndexation};
use App\Models\Platform\{Organization, User};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Notification\NotificationDispatcher;
use App\Support\OrganizationContext;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RoundingMode;

/**
 * Indexanpassung nach Verbraucherpreisindex (MVP-952): Veränderung des
 * jüngsten freigegebenen Indexstands gegenüber dem Basisindex des Vertrags,
 * mit optionaler Schwelle (Anpassung erst ab x %) und Weitergabequote.
 * Die Rechnung ist ein Vorschlag; erst die Übernahme ändert Vertragswert und
 * Basisindex. Keine Rechtsprüfung der Wertsicherungsklausel.
 */
class ContractIndexationService {
    use AssertsStatusTransition;

    private const SCALE = 6;

    public function __construct(
        private readonly PriceIndexService $index,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /** Ob der Vertrag alle Angaben für eine VPI-Anpassung trägt. */
    public function applicable(Contract $contract): bool {
        return $contract->indexation_method === IndexationMethod::ConsumerPriceIndex
            && $contract->indexation_base_value !== null
            && bccomp((string) $contract->indexation_base_value, '0', 1) > 0
            && $contract->value_amount !== null
            && $contract->status->isOpen();
    }

    /**
     * Rechnet die Anpassung gegen den jüngsten freigegebenen Indexstand.
     *
     * @return array{index_period_on: CarbonImmutable, index_value: string, change_percent: string, effective_percent: string, new_amount: string, reaches_threshold: bool}|null
     */
    public function calculate(Contract $contract, ?CarbonImmutable $asOf = null): ?array {
        if (! $this->applicable($contract)) {
            return null;
        }
        $latest = $this->index->latestApproved($asOf ?? CarbonImmutable::now());
        if ($latest === null || ($contract->indexation_base_period_on !== null && ! $latest->period_on->greaterThan($contract->indexation_base_period_on))) {
            return null;
        }
        $base = $contract->indexation_base_value ?? '0';
        $change = bcmul(bcsub(bcdiv((string) $latest->value, $base, self::SCALE), '1', self::SCALE), '100', self::SCALE);
        $passThrough = $contract->indexation_pass_through_percent ?? '100';
        $effective = bcdiv(bcmul($change, $passThrough, self::SCALE), '100', self::SCALE);
        $old = $contract->value_amount ?? '0';
        $new = bcround(bcmul($old, bcadd('1', bcdiv($effective, '100', self::SCALE), self::SCALE), self::SCALE), 2, RoundingMode::HalfAwayFromZero);
        $threshold = $contract->indexation_threshold_percent ?? '0';
        $absChange = bccomp($change, '0', self::SCALE) < 0 ? bcmul($change, '-1', self::SCALE) : $change;

        return [
            'index_period_on' => CarbonImmutable::parse($latest->period_on->toDateString()),
            'index_value' => (string) $latest->value,
            'change_percent' => bcround($change, 4, RoundingMode::HalfAwayFromZero),
            'effective_percent' => bcround($effective, 4, RoundingMode::HalfAwayFromZero),
            'new_amount' => $new,
            'reaches_threshold' => bccomp($absChange, $threshold, self::SCALE) >= 0 && bccomp($new, $old, 2) !== 0,
        ];
    }

    /** Legt einen Vorschlag an, sofern die Schwelle erreicht ist und für diesen Indexstand noch keiner besteht. */
    public function propose(Contract $contract, ?CarbonImmutable $asOf = null): ?ContractIndexation {
        $result = $this->calculate($contract, $asOf);
        if ($result === null || ! $result['reaches_threshold']) {
            return null;
        }
        $exists = ContractIndexation::query()->where('contract_id', $contract->id)
            ->whereBetween('index_period_on', DateRange::days($result['index_period_on'], $result['index_period_on']))
            ->exists();
        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($contract, $result): ContractIndexation {
            // Ein neuerer Stand löst den noch offenen Vorschlag ab.
            ContractIndexation::query()->where('contract_id', $contract->id)
                ->where('status', ContractIndexationStatus::Proposed->value)
                ->update(['status' => ContractIndexationStatus::Dismissed->value, 'note' => (string) __('contract.indexation.superseded'), 'decided_at' => now()]);

            $indexation = ContractIndexation::query()->create([
                'organization_id' => $contract->organization_id,
                'contract_id' => $contract->id,
                'status' => ContractIndexationStatus::Proposed,
                'base_period_on' => $contract->indexation_base_period_on?->toDateString() ?? $result['index_period_on']->toDateString(),
                'base_value' => (string) $contract->indexation_base_value,
                'index_period_on' => $result['index_period_on']->toDateString(),
                'index_value' => $result['index_value'],
                'change_percent' => $result['effective_percent'],
                'old_amount' => (string) $contract->value_amount,
                'new_amount' => $result['new_amount'],
                'currency' => $contract->currency->value,
            ]);
            $contract->audit('contract.indexationProposed', ['indexation_id' => $indexation->id, 'change_percent' => $result['effective_percent']]);

            return $indexation;
        });
    }

    public function apply(ContractIndexation $indexation, User $actor, ?CarbonImmutable $effectiveOn = null, ?string $note = null): ContractIndexation {
        $this->assertStatusTransition($indexation->status, ContractIndexationStatus::Applied);

        return DB::transaction(function () use ($indexation, $actor, $effectiveOn, $note): ContractIndexation {
            $contract = $indexation->contract()->lockForUpdate()->firstOrFail();
            $indexation->forceFill([
                'status' => ContractIndexationStatus::Applied,
                'effective_on' => ($effectiveOn ?? CarbonImmutable::now())->toDateString(),
                'decider_user_id' => $actor->id,
                'decided_at' => now(),
                'note' => $note,
            ])->save();
            $contract->forceFill([
                'value_amount' => (string) $indexation->new_amount,
                'indexation_base_value' => (string) $indexation->index_value,
                'indexation_base_period_on' => $indexation->index_period_on->toDateString(),
            ])->save();
            $contract->audit('contract.indexationApplied', ['indexation_id' => $indexation->id, 'old_amount' => (string) $indexation->old_amount, 'new_amount' => (string) $indexation->new_amount]);

            return $indexation;
        });
    }

    public function dismiss(ContractIndexation $indexation, User $actor, ?string $note = null): ContractIndexation {
        $this->assertStatusTransition($indexation->status, ContractIndexationStatus::Dismissed);
        $indexation->forceFill(['status' => ContractIndexationStatus::Dismissed, 'decider_user_id' => $actor->id, 'decided_at' => now(), 'note' => $note])->save();
        $indexation->contract?->audit('contract.indexationDismissed', ['indexation_id' => $indexation->id]);

        return $indexation;
    }

    /** Vorschläge für alle Verträge der Organisation mit VPI-Klausel; benachrichtigt die Verantwortlichen. */
    public function scan(Organization $organization): int {
        return (int) OrganizationContext::run($organization, fn (): int => $this->scanCurrent($organization));
    }

    private function scanCurrent(Organization $organization): int {
        $sent = 0;
        $contracts = Contract::query()
            ->where('organization_id', $organization->id)
            ->where('indexation_method', IndexationMethod::ConsumerPriceIndex->value)
            ->whereNotNull('indexation_base_value')
            ->with('responsible')
            ->get();
        foreach ($contracts as $contract) {
            $indexation = $this->propose($contract);
            if ($indexation === null) {
                continue;
            }
            $sent += $this->notifier->notify(NotificationEvent::ContractIndexationProposed, $indexation, $contract->responsible, [
                'title' => (string) __('contract.indexation.notification', ['number' => $contract->number]),
                'title_key' => 'contract.indexation.notification',
                'title_params' => ['number' => $contract->number],
                'message' => $contract->title,
                'url' => route('contracts.show', $contract),
            ], dedup: true);
        }

        return $sent;
    }
}
