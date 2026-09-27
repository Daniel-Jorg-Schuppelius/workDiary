<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentSupplierRatingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments;

use App\Models\Investments\{InvestmentCase, InvestmentSupplierRating};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Lieferantenbewertung über Investitionen (MVP-928): bewertet werden die
 * Lieferanten der Optionen, sobald die Investition in Umsetzung oder
 * abgeschlossen ist; die Übersicht mittelt je Lieferant über alle Investitionen.
 */
final class InvestmentSupplierRatingService {
    public const RATEABLE_STATUSES = ['in_progress', 'completed', 'post_review'];

    /** @return Collection<int, Supplier> */
    public function suppliersFor(InvestmentCase $case): Collection {
        return Supplier::query()
            ->whereIn('id', $case->options()->whereNotNull('supplier_id')->select('supplier_id'))
            ->orderBy('name')
            ->get();
    }

    public function isRateable(InvestmentCase $case): bool {
        return in_array((string) $case->status, self::RATEABLE_STATUSES, true);
    }

    /** @param array{schedule_score: int, cost_score: int, quality_score: int, note?: ?string} $scores */
    public function rate(InvestmentCase $case, Supplier $supplier, array $scores, User $actor): InvestmentSupplierRating {
        if (! $this->isRateable($case)) {
            throw new RuntimeException((string) __('investment.supplier_rating.error.not_rateable'));
        }
        if (! $this->suppliersFor($case)->contains('id', $supplier->id)) {
            throw new RuntimeException((string) __('investment.supplier_rating.error.not_supplier'));
        }

        return InvestmentSupplierRating::query()->updateOrCreate(
            ['investment_case_id' => $case->id, 'supplier_id' => $supplier->id],
            [
                'organization_id' => $case->organization_id,
                'schedule_score' => $scores['schedule_score'],
                'cost_score' => $scores['cost_score'],
                'quality_score' => $scores['quality_score'],
                'note' => isset($scores['note']) && trim((string) $scores['note']) !== '' ? trim((string) $scores['note']) : null,
                'rated_by' => $actor->id,
            ],
        );
    }

    /**
     * @return list<array{supplier: Supplier, ratings: int, schedule: float, cost: float, quality: float, overall: float}>
     */
    public function overview(): array {
        $rows = [];
        foreach (InvestmentSupplierRating::query()->with('supplier')->get()->groupBy('supplier_id') as $ratings) {
            $supplier = $ratings->first()?->supplier;
            if (! $supplier instanceof Supplier) {
                continue;
            }
            $schedule = round((float) $ratings->avg('schedule_score'), 1);
            $cost = round((float) $ratings->avg('cost_score'), 1);
            $quality = round((float) $ratings->avg('quality_score'), 1);
            $rows[] = ['supplier' => $supplier, 'ratings' => $ratings->count(), 'schedule' => $schedule, 'cost' => $cost, 'quality' => $quality, 'overall' => round(($schedule + $cost + $quality) / 3, 1)];
        }
        usort($rows, static fn (array $a, array $b): int => $b['overall'] <=> $a['overall']);

        return $rows;
    }
}
