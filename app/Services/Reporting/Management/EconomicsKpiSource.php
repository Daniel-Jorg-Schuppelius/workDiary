<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Management;

use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\Reporting\EconomicsReportBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Erlös und Deckungsbeitrag über alle Kunden — dieselbe Summe wie die
 * Wirtschaftlichkeitsauswertung ohne Filter (ausgeblendete Kunden bleiben außen vor).
 */
final class EconomicsKpiSource implements ManagementKpiSource {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly EconomicsReportBuilder $economics,
    ) {}

    public function group(): string {
        return 'operations';
    }

    public function order(): int {
        return 10;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('reports.economics') || ! ($viewer->isAdmin() || $viewer->can(Permission::ReportView->value))) {
            return [];
        }
        $excluded = array_values(array_map('intval', Customer::query()->where('organization_id', $organization->id)->where('exclude_from_reports', true)->pluck('id')->all()));
        $rows = collect($this->economics->byCustomer($from, $to, excludedCustomerIds: $excluded));
        $revenue = (float) $rows->sum('revenue');
        $contribution = (float) $rows->sum('contribution');
        $url = route('reports.economics', ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $eur = static fn (float $v): string => NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';

        return [
            new ManagementKpi(__('Umsatz'), $eur($revenue), url: $url),
            new ManagementKpi(
                __('Deckungsbeitrag'),
                $eur($contribution),
                $revenue > 0.0 ? (string) __('Marge :value', ['value' => NumberHelper::toGermanFormat($contribution / $revenue * 100, 1) . ' %']) : null,
                $contribution < 0 ? 'error' : 'success',
                $url,
            ),
        ];
    }
}
