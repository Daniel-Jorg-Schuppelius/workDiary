<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleRenewalReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\RenewalMode;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use Carbon\CarbonImmutable;

/**
 * Verlängerungsbericht (Feature 152, MVP-765): welche Abos sich im Zeitraum
 * verlängern oder enden. Verlängerung = Beginn der nächsten Periode nach dem
 * Stichtag laut {@see PeriodPlanner::plan()}; bei gekündigten Abos
 * (`RenewalMode::Cancel`) und bei bekanntem Ende vor der nächsten Periode
 * zählt das Ende.
 *
 * @phpstan-type RenewalRow array{subscription: ResaleSubscription, date: CarbonImmutable, days: int, mode: string}
 */
final class ResaleRenewalReport {
    public const MODE_RENEWS = 'renews';
    public const MODE_ENDS = 'ends';

    /** @var list<int> Fristen der Kennzahlen-Kacheln (Tage ab Stichtag) */
    public const BUCKETS = [30, 60, 90];

    public function __construct(private readonly PeriodPlanner $planner) {}

    /**
     * Zeilen mit Verlängerung/Ende im Zeitraum [von, bis], sortiert nach Datum.
     *
     * @return array{rows: list<RenewalRow>, buckets: array<int, int>}
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $today = null): array {
        $today ??= ResalePeriod::today();
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        $subscriptions = ResaleSubscription::query()->planning()
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name', 'article:id,number,name', 'lexofficeArticle:id,article_number,name'])
            ->orderBy('label')
            ->get();

        $rows = [];
        $buckets = array_fill_keys(self::BUCKETS, 0);
        foreach ($subscriptions as $subscription) {
            $event = $this->nextEvent($subscription, $today, $to);
            if ($event === null) {
                continue;
            }
            [$date, $mode] = $event;
            $days = (int) $today->diffInDays($date, false);
            foreach (self::BUCKETS as $bucket) {
                if ($days >= 0 && $days <= $bucket) {
                    $buckets[$bucket]++;
                }
            }
            if ($date->lessThan($from) || $date->greaterThan($to)) {
                continue;
            }
            $rows[] = ['subscription' => $subscription, 'date' => $date, 'days' => $days, 'mode' => $mode];
        }
        usort($rows, static fn(array $a, array $b): int => $a['date'] <=> $b['date'] ?: strcmp($a['subscription']->label, $b['subscription']->label));

        return ['rows' => $rows, 'buckets' => $buckets];
    }

    /**
     * @param  list<RenewalRow>  $rows
     * @return array{header: list<string>, rows: list<list<int|float|string|null>>}
     */
    public function exportRows(array $rows): array {
        $header = [
            (string) __('resale.renewals.date'), (string) __('resale.renewals.mode'), (string) __('resale.field.label'), (string) __('resale.field.article'),
            (string) __('resale.field.holder'), (string) __('resale.field.billed_to'), (string) __('resale.field.provider'), (string) __('resale.field.quantity'),
            (string) __('resale.field.interval'), (string) __('resale.field.external_id'),
        ];
        $out = [];
        foreach ($rows as $row) {
            $subscription = $row['subscription'];
            $out[] = [
                $row['date']->toDateString(),
                (string) __('resale.renewals.mode_' . $row['mode']),
                $subscription->label,
                $subscription->productLabel() ?? '',
                $subscription->holderLabel(),
                $subscription->billedTo()->name ?? '',
                $subscription->provider->label(),
                $subscription->quantity,
                $subscription->interval->label(),
                $subscription->external_id ?? '',
            ];
        }

        return ['header' => $header, 'rows' => $out];
    }

    /**
     * Nächstes Ereignis nach dem Stichtag: Ende (gekündigt oder Ende vor der
     * nächsten Periode) oder Beginn der nächsten geplanten Periode. Der
     * Planungshorizont wird auf das Zeitraumende gezogen.
     *
     * @return array{0: CarbonImmutable, 1: string}|null
     */
    private function nextEvent(ResaleSubscription $subscription, CarbonImmutable $today, CarbonImmutable $to): ?array {
        $endsOn = $subscription->ends_on;
        if ($endsOn !== null && $endsOn->lessThan($today)) {
            return null; // schon vorbei
        }
        if ($subscription->renewal === RenewalMode::Cancel && $endsOn !== null) {
            return [$endsOn, self::MODE_ENDS];
        }
        // plan() plant bis Stichtag + HORIZON_DAYS; für aktive/gekündigte Abos bestimmt
        // der Stichtag nur den Horizont — Zeitraumende − Horizont deckt den Zeitraum ab.
        $reference = $to->subDays(PeriodPlanner::HORIZON_DAYS);
        $next = null;
        foreach ($this->planner->plan($subscription, $reference->greaterThan($today) ? $reference : $today) as $slot) {
            // Der erste Beginn ist der Vertragsstart, keine Verlängerung.
            if ($slot['starts_on']->greaterThan($today) && ! $slot['starts_on']->equalTo($subscription->starts_on)) {
                $next = $slot['starts_on'];
                break;
            }
        }
        if ($endsOn !== null && ($next === null || $next->greaterThan($endsOn))) {
            return [$endsOn, self::MODE_ENDS];
        }

        return $next === null ? null : [$next, self::MODE_RENEWS];
    }
}
