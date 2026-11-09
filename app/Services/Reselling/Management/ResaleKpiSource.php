<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Management;

use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\Reselling\Register\{ResaleMarginReport, ResaleOverview};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Kennzahlen des Reselling-Registers in der Leitungssicht (Phase 175): fällige
 * Perioden ohne Rechnung aus dem Überblick (Stand heute) und die Marge des
 * Zeitraums aus dem Margenbericht.
 */
final class ResaleKpiSource implements ManagementKpiSource {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly ResaleOverview $overview,
        private readonly ResaleMarginReport $margins,
    ) {}

    public function group(): string {
        return 'finance';
    }

    public function order(): int {
        return 60;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('finance.resale.index') || ! $viewer->can(Permission::ResellingView->value)) {
            return [];
        }
        $summary = $this->overview->build($organization)['summary'];
        if ($summary['active'] === 0 && $summary['open'] === 0) {
            return [];
        }
        $kpis = [
            new ManagementKpi(
                (string) __('reporting.management.kpi.resale_open'),
                (string) $summary['open'],
                $summary['open_amount'] !== '' ? (string) __('reporting.management.kpi.resale_open_hint', ['amount' => $summary['open_amount']]) : null,
                $summary['open'] > 0 ? 'warning' : 'success',
                route('finance.resale.report.unbilled', ['days' => 0]),
            ),
        ];

        $rows = $this->margins->build($from, $to)['by_product'];
        if ($rows !== []) {
            /** @var array<string, Money> $margin */
            $margin = [];
            $periods = 0;
            $withActual = 0;
            foreach ($rows as $row) {
                $code = $row['currency']->value;
                $margin[$code] = isset($margin[$code]) ? $margin[$code]->plus($row['margin']) : $row['margin'];
                $periods += $row['periods'];
                $withActual += $row['with_actual'];
            }
            ksort($margin);
            $negative = array_filter($margin, static fn(Money $m): bool => $m->isNegative()) !== [];
            $kpis[] = new ManagementKpi(
                (string) __('reporting.management.kpi.resale_margin'),
                implode(' · ', array_map(static fn(Money $m): string => $m->format(), $margin)),
                (string) __('reporting.management.kpi.resale_margin_hint', ['periods' => $periods, 'actual' => $withActual]),
                $negative ? 'warning' : 'neutral',
                route('finance.resale.report.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
            );
        }

        return $kpis;
    }
}
