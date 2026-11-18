<?php
/*
 * Created on   : Wed Nov 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderBillingStart.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{PeriodStatus, SubscriptionKind};
use App\Models\Customer\Customer;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use Carbon\CarbonImmutable;

/**
 * Abrechnungsbeginn eines Portalkontos (MVP-1746): Domains, die ein Subuser
 * über das Anbieterportal abrechnet, berechnet der Reseller erst, seit es das
 * Unterkonto gibt. Lizenzjahre, die vorher begannen, hat er dem Kunden nie in
 * Rechnung gestellt und wird es nicht — sie werden gesammelt abgeschlossen,
 * mit dem Kontobeginn als Grund. Kein Forderungsverzicht: ohne Rechnung gab es
 * keine Forderung ({@see ResalePeriod::isBeforeBillingStart()}).
 *
 * @phpstan-type BillingStart array{account: string, since: CarbonImmutable, periods: list<ResalePeriod>}
 */
final class ProviderBillingStart {
    public function __construct(private readonly PeriodLinker $linker) {}

    /**
     * Je Portalkonto des Kunden die offenen fälligen Perioden, die vor dem Kontobeginn begannen.
     * Teilweise gedeckte bleiben außen vor: dort wurde etwas berechnet, das gehört angesehen.
     *
     * @return list<BillingStart>
     */
    public function candidates(Customer $customer): array {
        $subscriptions = ResaleSubscription::query()->forCustomer($customer)
            ->where('is_own_holding', false)
            ->where('kind', SubscriptionKind::Domain->value)
            ->whereNotNull('domain_projection_id')
            ->with(ResaleSubscription::providerBillingRelations())
            ->with(['periods' => static fn ($q) => $q->where('status', PeriodStatus::Open->value)])
            ->orderBy('label')
            ->get();
        /** @var array<string, BillingStart> $groups */
        $groups = [];
        foreach ($subscriptions as $subscription) {
            $account = $subscription->isBilledByProvider() ? $subscription->domainProjection?->resellerAccount : null;
            if ($account === null || $account->provider_created_at === null) {
                continue;
            }
            $since = CarbonImmutable::parse($account->provider_created_at->toDateString());
            foreach ($subscription->periods as $period) {
                if (! $period->starts_on->lessThan($since) || ! $period->isDecidable()) {
                    continue;
                }
                $period->setRelation('subscription', $subscription);
                $key = (string) $account->external_user;
                $groups[$key] ??= ['account' => $key, 'since' => $since, 'periods' => []];
                $groups[$key]['periods'][] = $period;
            }
        }
        foreach ($groups as &$group) {
            usort($group['periods'], static fn(ResalePeriod $a, ResalePeriod $b): int => ($a->starts_on <=> $b->starts_on) ?: ($a->id <=> $b->id));
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * Schließt die Kandidaten ab; gerechnet wird neu, nicht aus dem Formular.
     *
     * @return int Zahl der abgeschlossenen Perioden
     */
    public function apply(Customer $customer, ?int $userId): int {
        $done = 0;
        foreach ($this->candidates($customer) as $group) {
            $reason = (string) __('resale.billing_start.reason', ['account' => $group['account'], 'date' => $group['since']->format('d.m.Y')]);
            foreach ($group['periods'] as $period) {
                $this->linker->decide($period, PeriodStatus::Waived, $reason, $userId);
                $done++;
            }
        }

        return $done;
    }
}
