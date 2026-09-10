<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleUnbilledReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * „Abo ohne Rechnung > N Tage" (Feature 152, Prozesse 6): Abos, deren
 * älteste offene fällige Periode ({@see ResalePeriod::scopeDue()}) länger
 * als N Tage zurückliegt — je Abo die Zahl offener Perioden und der offene
 * Betrag ({@see ResalePeriod::openAmount()}), je Währung getrennt.
 *
 * @phpstan-type UnbilledRow array{subscription: ResaleSubscription, oldest: ResalePeriod, days: int, open_periods: int, open_amount: Money|null}
 */
final class ResaleUnbilledReport {
    public const DEFAULT_DAYS = 60;

    /**
     * @return list<UnbilledRow>
     */
    public function build(int $days, ?CarbonImmutable $today = null): array {
        $today ??= ResalePeriod::today();
        $threshold = $today->subDays(max(0, $days));
        $stale = ResalePeriod::query()->due($today)->where('starts_on', '<', DateRange::day($threshold))->distinct()->pluck('subscription_id');
        if ($stale->isEmpty()) {
            return [];
        }
        $periods = ResalePeriod::query()->due($today)->whereIn('subscription_id', $stale)
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'links'])
            ->orderBy('starts_on')
            ->get();

        /** @var array<int, UnbilledRow> $rows */
        $rows = [];
        foreach ($periods as $period) {
            $id = $period->subscription_id;
            $amount = $period->openAmount();
            if (! isset($rows[$id])) {
                $rows[$id] = ['subscription' => $period->subscription, 'oldest' => $period, 'days' => (int) $period->starts_on->diffInDays($today), 'open_periods' => 0, 'open_amount' => null];
            }
            $rows[$id]['open_periods']++;
            if ($amount !== null) {
                $current = $rows[$id]['open_amount'];
                $rows[$id]['open_amount'] = $current === null || ! $current->isSameCurrency($amount) ? ($current ?? $amount) : $current->plus($amount);
            }
        }
        $list = array_values($rows);
        usort($list, static fn(array $a, array $b): int => $b['days'] <=> $a['days'] ?: strcmp($a['subscription']->label, $b['subscription']->label));

        return $list;
    }

    /**
     * @param  list<UnbilledRow>  $rows
     * @return array{header: list<string>, rows: list<list<int|float|string|null>>}
     */
    public function exportRows(array $rows): array {
        $header = [
            (string) __('resale.field.label'), (string) __('resale.field.holder'), (string) __('resale.field.billed_to'), (string) __('resale.field.provider'),
            (string) __('resale.unbilled.oldest'), (string) __('resale.unbilled.days'), (string) __('resale.unbilled.open_periods'), (string) __('resale.export.open_amount'), (string) __('resale.margin.currency'),
        ];
        $out = [];
        foreach ($rows as $row) {
            $subscription = $row['subscription'];
            $out[] = [
                $subscription->label,
                $subscription->holderLabel(),
                $subscription->billedTo()->name ?? '',
                $subscription->provider->label(),
                $row['oldest']->starts_on->toDateString(),
                $row['days'],
                $row['open_periods'],
                $row['open_amount']?->format(withSymbol: false, withThousandsSeparator: false) ?? '',
                $row['open_amount']?->getCurrency()->value ?? '',
            ];
        }

        return ['header' => $header, 'rows' => $out];
    }
}
