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
use Illuminate\Database\Eloquent\Builder;

/**
 * Abrechnungsbeginn eines Portalkontos (MVP-1746/1747): Domains, die ein Subuser
 * über das Anbieterportal abrechnet, berechnet das Portal erst, seit es das
 * Unterkonto gibt. Für die Lizenzjahre, die vorher begannen, entscheidet der
 * Reseller gesammelt: verzichten (etwa weil intern abgegolten) oder noch
 * selbst berechnen — dann bleiben sie offen und kommen trotz Portalabrechnung
 * der Domain in den Rechnungsvorschlag ({@see ResalePeriod::scopeBilledByRegister()}).
 *
 * @phpstan-type BillingStart array{account: string, since: CarbonImmutable, periods: list<ResalePeriod>}
 */
final class ProviderBillingStart {
    public const string WAIVE = 'waive';

    public const string BILL = 'bill';

    public const array DECISIONS = [self::WAIVE, self::BILL];

    public const string AUDIT_BILL = 'resale_period.direct_billing';

    public function __construct(private readonly PeriodLinker $linker) {}

    /**
     * Je Portalkonto des Kunden die offenen fälligen Perioden, die vor dem Kontobeginn begannen und
     * noch nicht entschieden sind. Teilweise gedeckte bleiben außen vor: dort wurde etwas berechnet.
     *
     * @return list<BillingStart>
     */
    public function candidates(Customer $customer): array {
        return $this->groups(ResaleSubscription::query()->forCustomer($customer));
    }

    /**
     * Das Portalkonto dieses Abos mit seinen Perioden vor dem Kontobeginn — null, wenn es keine gibt (Hinweis im Abo-Detail).
     *
     * @return BillingStart|null
     */
    public function forSubscription(ResaleSubscription $subscription): ?array {
        return $this->groups(ResaleSubscription::query()->whereKey($subscription->getKey()))[0] ?? null;
    }

    /**
     * Über alle Kunden: Zahl der Perioden und der Kunde mit den meisten — Aufgabe im Überblick.
     *
     * @return array{count: int, customer_id: int|null}
     */
    public function overview(): array {
        /** @var array<int, int> $byCustomer */
        $byCustomer = [];
        foreach ($this->groups(ResaleSubscription::query()->with(['customer', 'foreignCustomer.customer'])) as $group) {
            foreach ($group['periods'] as $period) {
                $customerId = $period->subscription->billedTo()?->id;
                if ($customerId !== null) {
                    $byCustomer[(int) $customerId] = ($byCustomer[(int) $customerId] ?? 0) + 1;
                }
            }
        }
        arsort($byCustomer);

        return ['count' => (int) array_sum($byCustomer), 'customer_id' => array_key_first($byCustomer)];
    }

    /**
     * @param  Builder<ResaleSubscription>  $query
     * @return list<BillingStart>
     */
    private function groups(Builder $query): array {
        $subscriptions = $query
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
                if (! $period->starts_on->lessThan($since) || ! $period->isDecidable() || $period->is_direct_billing) {
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
     * Entscheidet die Kandidaten; gerechnet wird neu, nicht aus dem Formular.
     *
     * @return int Zahl der entschiedenen Perioden
     */
    public function apply(Customer $customer, string $decision, ?string $note, ?int $userId): int {
        $note = trim((string) $note);
        $done = 0;
        foreach ($this->candidates($customer) as $group) {
            $reason = (string) __('resale.billing_start.reason', ['account' => $group['account'], 'date' => $group['since']->format('d.m.Y')]);
            foreach ($group['periods'] as $period) {
                if ($decision === self::BILL) {
                    $period->appendNote((string) __('resale.billing_start.note_bill', ['account' => $group['account'], 'date' => $group['since']->format('d.m.Y')]));
                    $period->forceFill(['is_direct_billing' => true])->save();
                    $period->audit(self::AUDIT_BILL, ['account' => $group['account'], 'since' => $group['since']->toDateString()]);
                } else {
                    $this->linker->decide($period, PeriodStatus::Waived, $note === '' ? $reason : $reason . ' — ' . $note, $userId);
                }
                $done++;
            }
        }

        return $done;
    }
}
