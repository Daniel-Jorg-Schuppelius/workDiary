<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StrategicObjectiveService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments;

use App\Models\Investments\{InvestmentCase, StrategicObjective};
use App\Models\Platform\{Organization, User};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\DB;

/**
 * Strategische Ziele (MVP-942): Ziel mit Kennzahlen pflegen und das
 * Portfolio der zugeordneten Investitionen verdichten (Planwert wie im
 * Investitionsprogramm).
 */
final class StrategicObjectiveService {
    public function __construct(private readonly InvestmentProgramService $programs) {}

    /**
     * @param array{title: string, description?: ?string, owner_user_id?: ?int, valid_from?: ?string, valid_until?: ?string, is_active?: bool} $data
     * @param list<array{label?: ?string, unit?: ?string, baseline_value?: ?string, target_value?: ?string, current_value?: ?string}> $keyResults
     */
    public function save(Organization $organization, ?StrategicObjective $objective, array $data, array $keyResults, User $actor): StrategicObjective {
        return DB::transaction(function () use ($organization, $objective, $data, $keyResults, $actor): StrategicObjective {
            if ($objective === null) {
                $objective = StrategicObjective::query()->create($data + ['organization_id' => $organization->id, 'created_by' => $actor->id]);
            } else {
                $objective->update($data);
            }
            $objective->keyResults()->delete();
            $position = 0;
            foreach ($keyResults as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '' || ! is_numeric($row['target_value'] ?? null)) {
                    continue;
                }
                $objective->keyResults()->create([
                    'organization_id' => $organization->id,
                    'label' => $label,
                    'unit' => filled($row['unit'] ?? null) ? trim((string) $row['unit']) : null,
                    'baseline_value' => is_numeric($row['baseline_value'] ?? null) ? $row['baseline_value'] : null,
                    'target_value' => $row['target_value'],
                    'current_value' => is_numeric($row['current_value'] ?? null) ? $row['current_value'] : null,
                    'position' => ++$position,
                ]);
            }

            return $objective;
        });
    }

    /**
     * @return array{cases: list<array{case: InvestmentCase, planned: string}>, planned: string, approved: int, by_status: array<string, int>}
     */
    public function portfolio(StrategicObjective $objective): array {
        $cases = [];
        $planned = '0.00';
        $approved = 0;
        $byStatus = [];
        foreach ($objective->cases()->orderBy('title')->get() as $case) {
            $amount = $this->programs->plannedAmount($case);
            $cases[] = ['case' => $case, 'planned' => $amount];
            $planned = NumberHelper::addPrecise($planned, $amount, 2);
            $approved += $case->approvedBudget() !== null ? 1 : 0;
            $byStatus[$case->status->value] = ($byStatus[$case->status->value] ?? 0) + 1;
        }

        return ['cases' => $cases, 'planned' => $planned, 'approved' => $approved, 'by_status' => $byStatus];
    }
}
