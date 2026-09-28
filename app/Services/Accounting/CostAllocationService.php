<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostAllocationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Finance\{CostAllocationKey, CostCenter};
use App\Models\Platform\{Organization, User};
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Umlagen zwischen Kostenstellen (MVP-982): Vorkostenstellen geben einen Anteil
 * ihrer Aufwendungen an Endkostenstellen ab. Rein rechnerisch — die BWA „nach
 * Umlage“ verteilt die Summen, Buchungen bleiben unverändert.
 */
final class CostAllocationService {
    /** @return Collection<int, CostAllocationKey> */
    public function keysFor(Organization $organization, int $fiscalYear): Collection {
        return CostAllocationKey::query()->where('organization_id', $organization->id)->where('fiscal_year', $fiscalYear)
            ->with(['source', 'target'])->orderBy('source_cost_center_id')->orderBy('target_cost_center_id')->get();
    }

    public function save(Organization $organization, int $fiscalYear, CostCenter $source, CostCenter $target, Percentage $share, User $actor): CostAllocationKey {
        if ($source->organization_id !== $organization->id || $target->organization_id !== $organization->id) {
            abort(404);
        }
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target' => (string) __('accounting.allocation.error.same')]);
        }
        if (! $share->isPositive() || ! $share->isWithinZeroAndHundred()) {
            throw ValidationException::withMessages(['share_percent' => (string) __('accounting.allocation.error.share')]);
        }
        $others = $this->total(CostAllocationKey::query()->where('organization_id', $organization->id)->where('fiscal_year', $fiscalYear)
            ->where('source_cost_center_id', $source->id)->where('target_cost_center_id', '!=', $target->id)->get());
        if (! $others->plus($share)->isWithinZeroAndHundred()) {
            throw ValidationException::withMessages(['share_percent' => (string) __('accounting.allocation.error.total', ['rest' => Percentage::of(100, 2)->minus($others)->getNumericValue()])]);
        }

        return CostAllocationKey::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'fiscal_year' => $fiscalYear, 'source_cost_center_id' => $source->id, 'target_cost_center_id' => $target->id],
            ['share_percent' => $share, 'created_by' => $actor->id],
        );
    }

    /**
     * Kontensummen einer Kostenstelle nach Umlage: eigene Aufwendungen abzüglich
     * des abgegebenen Anteils, zuzüglich der Anteile aus den Vorkostenstellen.
     * Erlöse und Bestandskonten bleiben unberührt.
     *
     * @param  array<int, array{debit: numeric-string, credit: numeric-string}>  $own
     * @param  list<int>  $expenseAccountIds
     * @param  callable(int): array<int, array{debit: numeric-string, credit: numeric-string}>  $sumsOf  Summen einer anderen Kostenstelle
     * @return array<int, array{debit: numeric-string, credit: numeric-string}>
     */
    public function allocate(Organization $organization, int $fiscalYear, int $costCenterId, array $own, array $expenseAccountIds, callable $sumsOf): array {
        $keys = $this->keysFor($organization, $fiscalYear);
        $given = $this->total($keys->where('source_cost_center_id', $costCenterId));
        $result = $this->scale($own, $expenseAccountIds, Percentage::of(100, 2)->minus($given), true);

        foreach ($keys->where('target_cost_center_id', $costCenterId) as $key) {
            foreach ($this->scale($sumsOf($key->source_cost_center_id), $expenseAccountIds, $key->share_percent, false) as $accountId => $sums) {
                $result[$accountId] = [
                    'debit' => NumberHelper::addPrecise($result[$accountId]['debit'] ?? '0.00', $sums['debit'], 2),
                    'credit' => NumberHelper::addPrecise($result[$accountId]['credit'] ?? '0.00', $sums['credit'], 2),
                ];
            }
        }

        return $result;
    }

    /**
     * Summe der Anteile; leer ergibt 0 %.
     *
     * @param  iterable<CostAllocationKey>  $keys
     */
    public function total(iterable $keys): Percentage {
        $sum = Percentage::of(0, 2);
        foreach ($keys as $key) {
            $sum = $sum->plus($key->share_percent);
        }

        return $sum;
    }

    /**
     * @param  array<int, array{debit: numeric-string, credit: numeric-string}>  $sums
     * @param  list<int>  $expenseAccountIds
     * @return array<int, array{debit: numeric-string, credit: numeric-string}>
     */
    private function scale(array $sums, array $expenseAccountIds, Percentage $share, bool $keepOthers): array {
        $factor = $share->asFactor()->getValue();
        $result = [];
        foreach ($sums as $accountId => $values) {
            if (! in_array($accountId, $expenseAccountIds, true)) {
                if ($keepOthers) {
                    $result[$accountId] = $values;
                }

                continue;
            }
            $result[$accountId] = [
                'debit' => NumberHelper::multiplyPrecise($values['debit'], $factor, 2, RoundingMode::HalfUp),
                'credit' => NumberHelper::multiplyPrecise($values['credit'], $factor, 2, RoundingMode::HalfUp),
            ];
        }

        return $result;
    }
}
