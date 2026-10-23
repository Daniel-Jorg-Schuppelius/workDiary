<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Management;

use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\Reports\{BwaBuilder, LiquidityForecastBuilder};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\Gate;

/** Gesamtleistung und Ergebnis aus der BWA, Liquiditäts-Tiefststand aus der Vorschau — Recht wie die Buchhaltungsauswertungen. */
final class AccountingKpiSource implements ManagementKpiSource {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly BwaBuilder $bwa,
        private readonly LiquidityForecastBuilder $liquidity,
    ) {}

    public function group(): string {
        return 'finance';
    }

    public function order(): int {
        return 10;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('reports.accounting.bwa') || ! Gate::forUser($viewer)->allows(Permission::AccountingLedgerView->value)) {
            return [];
        }
        $eur = static fn (float $v): string => NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
        $range = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
        $kpis = [];

        $bwa = $this->bwa->build($organization, $from, $to);
        $output = (float) ($bwa['subtotals']['total_output'][BwaBuilder::COL_ACTUAL] ?? 0);
        $result = (float) ($bwa['subtotals']['result'][BwaBuilder::COL_ACTUAL] ?? 0);
        // Die BWA trägt ihre Gliederung auch ohne Buchungen — dann gibt es nichts zu berichten.
        if ($output !== 0.0 || $result !== 0.0) {
            $kpis[] = new ManagementKpi(__('reporting.management.kpi.total_output'), $eur($output), url: route('reports.accounting.bwa', $range));
            $kpis[] = new ManagementKpi(__('reporting.management.kpi.result'), $eur($result), tone: $result < 0 ? 'error' : 'success', url: route('reports.accounting.bwa', $range));
        }

        $forecast = $this->liquidity->build($organization, CarbonImmutable::today());
        if ($forecast['totals']['items'] > 0 || (float) $forecast['opening_balance'] !== 0.0) {
            $low = (float) $forecast['totals']['min_closing'];
            $kpis[] = new ManagementKpi(
                __('reporting.management.kpi.liquidity_low'),
                $eur($low),
                __('reporting.management.kpi.liquidity_low_hint', ['week' => $forecast['totals']['min_week'], 'weeks' => $forecast['weeks']]),
                $low < 0 ? 'error' : 'neutral',
                route('reports.accounting.liquidity'),
            );
        }

        return $kpis;
    }
}
