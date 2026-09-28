<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlannedInvestmentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments\Liquidity;

use App\Models\Investments\InvestmentCase;
use App\Models\Platform\Organization;
use App\Services\Accounting\Contracts\LiquidityForecastSource;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Geplante Investitionen als Auszahlung zum Projektbeginn (MVP-954):
 * genehmigtes Budget, sonst der geschätzte Betrag.
 */
class PlannedInvestmentSource implements LiquidityForecastSource {
    private const OPEN_STATUSES = ['idea', 'screening', 'comparison', 'budget_request', 'in_approval', 'approved', 'in_progress'];

    public function key(): string {
        return 'investments';
    }

    public function items(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $items = [];
        $cases = InvestmentCase::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('starts_on')
            ->where('starts_on', '>=', $from->toDateString())
            ->where('starts_on', '<', DateRange::dayAfter($to))
            ->get();
        foreach ($cases as $case) {
            $amount = $case->approvedBudget()->amount ?? $case->estimated_amount;
            if ($amount === null || $case->starts_on === null || bccomp($amount, '0', 2) <= 0) {
                continue;
            }
            $items[] = [
                'source' => $this->key(),
                'direction' => 'out',
                'amount' => (string) $amount,
                'expected_on' => CarbonImmutable::parse($case->starts_on->toDateString()),
                'label' => $case->title,
                'note' => null,
            ];
        }

        return $items;
    }
}
