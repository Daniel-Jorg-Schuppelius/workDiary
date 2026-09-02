<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentBehaviorReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\{Customer, Invoice};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Zahlungsverhalten & Forderungstrend (MVP-468, Feature 002). Verhaltens-
 * und Trendsicht auf Rechnungen — grenzt sich vom Billing-Report
 * (Bestandsaufnahme/Aging heute) bewusst ab. Referenzdatum ist das
 * Zeitraumende, nicht „heute" — reproduzierbare Berichte.
 *
 * Quellen: lokale Rechnungen (Typen invoice/partial/final, Status ohne
 * draft/cancelled) PLUS der Lexoffice-Beleg-Spiegel (Phase-54-Nachtrag,
 * {@see LexofficeRevenueMirror::invoiceRows()}) — bei externer
 * Rechnungshoheit kämen sonst keine Zahlungsdaten zusammen. Zahldaten der
 * Spiegelbelege stammen aus der Payments-Anreicherung des Belegsyncs.
 *
 * DSO als Countback: offene Forderungen am Monatsende ÷ Umsatz der letzten
 * 90 Tage × 90. Zahldauer = paid_on − issued_on, Verzug = max(0, paid_on −
 * due_on).
 */
class PaymentBehaviorReportBuilder {
    private const DSO_WINDOW_DAYS = 90;

    public function __construct(private readonly LexofficeRevenueMirror $externalInvoices) {}

    /**
     * @param  list<int>  $excludedCustomerIds
     * @return array{
     *   kpis: array{dso:?float, avgPayDays:?float, onTimeShare:?float, overdueCount:int, overdueTotal:float, paidCount:int},
     *   monthly: list<array{month:string, dso:?float, avgPayDays:?float}>,
     *   payBox: list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int, customerId:?int}>,
     *   delayTop: list<array{customerId:int, customerName:string, avgDelay:float, invoices:int}>,
     *   overdue: list<array{invoiceId:?int, number:string, customerId:int, customerName:string, dueOn:string, daysOverdue:int, total:float}>,
     *   hasData: bool,
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, ?int $customerId, array $excludedCustomerIds = []): array {
        $invoices = Invoice::query()
            ->whereNotNull('issued_on')
            ->where('issued_on', '<', DateRange::dayAfter($to))
            ->whereIn('type', [Invoice::TYPE_INVOICE, Invoice::TYPE_PARTIAL, Invoice::TYPE_FINAL])
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID])
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($customerId === null && $excludedCustomerIds !== [], fn($q) => $q->whereNotIn('customer_id', $excludedCustomerIds))
            ->get(['id', 'customer_id', 'number', 'issued_on', 'due_on', 'paid_on', 'total', 'status'])
            ->map(static fn(Invoice $inv): array => [
                'id' => (int) $inv->id,
                'customerId' => (int) $inv->customer_id,
                'number' => (string) $inv->number,
                'issuedOn' => $inv->issued_on?->toDateString(),
                'dueOn' => $inv->due_on?->toDateString(),
                'paidOn' => $inv->paid_on?->toDateString(),
                'total' => $inv->total?->toFloat() ?? 0.0,
                'paid' => $inv->status === Invoice::STATUS_PAID,
            ])
            ->values()
            ->all();

        $invoices = array_merge($invoices, $this->externalInvoices->invoiceRows(
            $to->toDateString(),
            $customerId,
            $customerId === null ? $excludedCustomerIds : [],
        ));

        if ($invoices === []) {
            return [
                'kpis' => ['dso' => null, 'avgPayDays' => null, 'onTimeShare' => null, 'overdueCount' => 0, 'overdueTotal' => 0.0, 'paidCount' => 0],
                'monthly' => [],
                'payBox' => [],
                'delayTop' => [],
                'overdue' => [],
                'hasData' => false,
            ];
        }

        $names = Customer::query()
            ->whereIn('id', array_values(array_unique(array_column($invoices, 'customerId'))))
            ->pluck('name', 'id')
            ->mapWithKeys(static fn($name, $id): array => [(int) $id => (string) $name])
            ->all();

        // Im Zeitraum bezahlte Rechnungen → Zahldauer/Verzug.
        $paidInPeriod = array_values(array_filter($invoices, static fn(array $inv): bool => $inv['paid']
            && $inv['paidOn'] !== null
            && $inv['issuedOn'] !== null
            && $inv['paidOn'] >= $from->toDateString()
            && $inv['paidOn'] <= $to->toDateString()));

        $payDays = static fn(array $inv): int => (int) max(0, CarbonImmutable::parse($inv['issuedOn'])->diffInDays(CarbonImmutable::parse($inv['paidOn']), false));
        $delayDays = static fn(array $inv): int => $inv['dueOn'] !== null
            ? (int) max(0, CarbonImmutable::parse($inv['dueOn'])->diffInDays(CarbonImmutable::parse($inv['paidOn']), false))
            : 0;

        $withDue = array_values(array_filter($paidInPeriod, static fn(array $inv): bool => $inv['dueOn'] !== null));
        $onTime = array_filter($withDue, static fn(array $inv): bool => $inv['paidOn'] <= $inv['dueOn']);

        // Offene, überfällige Rechnungen zum Zeitraumende.
        $overdue = [];
        foreach ($invoices as $inv) {
            if ($inv['paid'] || $inv['dueOn'] === null || $inv['dueOn'] >= $to->toDateString()) {
                continue;
            }
            $overdue[] = [
                'invoiceId' => $inv['id'],
                'number' => $inv['number'],
                'customerId' => $inv['customerId'],
                'customerName' => $names[$inv['customerId']] ?? ('#' . $inv['customerId']),
                'dueOn' => $inv['dueOn'],
                'daysOverdue' => (int) max(0, CarbonImmutable::parse($inv['dueOn'])->diffInDays($to, false)),
                'total' => $inv['total'],
            ];
        }
        usort($overdue, static fn(array $a, array $b): int => $b['daysOverdue'] <=> $a['daysOverdue']);

        return [
            'kpis' => [
                'dso' => $this->dsoAt($invoices, $to),
                'avgPayDays' => $paidInPeriod !== [] ? round(array_sum(array_map($payDays, $paidInPeriod)) / count($paidInPeriod), 1) : null,
                'onTimeShare' => $withDue !== [] ? round(count($onTime) / count($withDue) * 100, 1) : null,
                'overdueCount' => count($overdue),
                'overdueTotal' => round(array_sum(array_column($overdue, 'total')), 2),
                'paidCount' => count($paidInPeriod),
            ],
            'monthly' => $this->monthly($invoices, $from, $to, $payDays),
            'payBox' => $this->payBox($paidInPeriod, $names, $payDays),
            'delayTop' => $this->delayTop($withDue, $names, $delayDays),
            'overdue' => array_slice($overdue, 0, 15),
            'hasData' => true,
        ];
    }

    /**
     * @param  array<int, array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>  $invoices
     */
    private function dsoAt(array $invoices, CarbonImmutable $at): ?float {
        $atDate = $at->toDateString();
        $windowFrom = $at->subDays(self::DSO_WINDOW_DAYS)->toDateString();

        $openAr = 0.0;
        $sales = 0.0;
        foreach ($invoices as $inv) {
            if ($inv['issuedOn'] === null || $inv['issuedOn'] > $atDate) {
                continue;
            }
            if (! $inv['paid'] || ($inv['paidOn'] !== null && $inv['paidOn'] > $atDate)) {
                $openAr += $inv['total'];
            }
            if ($inv['issuedOn'] > $windowFrom) {
                $sales += $inv['total'];
            }
        }

        return $sales > 0 ? round($openAr / $sales * self::DSO_WINDOW_DAYS, 1) : null;
    }

    /**
     * @param  array<int, array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>  $invoices
     * @param  callable(array{issuedOn:?string, paidOn:?string}): int  $payDays
     * @return list<array{month:string, dso:?float, avgPayDays:?float}>
     */
    private function monthly(array $invoices, CarbonImmutable $from, CarbonImmutable $to, callable $payDays): array {
        $monthly = [];
        $cursor = $from->startOfMonth();
        while ($cursor <= $to) {
            $monthEnd = $cursor->endOfMonth()->min($to);
            $month = $cursor->format('Y-m');

            $paidThisMonth = array_values(array_filter($invoices, static fn(array $inv): bool => $inv['paid']
                && $inv['paidOn'] !== null
                && $inv['issuedOn'] !== null
                && substr($inv['paidOn'], 0, 7) === $month));

            $monthly[] = [
                'month' => $month,
                'dso' => $this->dsoAt($invoices, $monthEnd),
                'avgPayDays' => $paidThisMonth !== [] ? round(array_sum(array_map($payDays, $paidThisMonth)) / count($paidThisMonth), 1) : null,
            ];

            $cursor = $cursor->addMonthsNoOverflow(1);
        }

        return $monthly;
    }

    /**
     * Zahldauer-Verteilung (Boxplot): alle Kunden gesamt plus die fünf
     * Kunden mit den meisten bezahlten Rechnungen (n ≥ 3).
     *
     * @param  list<array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>  $paidInPeriod
     * @param  array<int, string>  $names
     * @param  callable(array{issuedOn:?string, paidOn:?string}): int  $payDays
     * @return list<array{x:string, min:float, q1:float, median:float, q3:float, max:float, n:int, customerId:?int}>
     */
    private function payBox(array $paidInPeriod, array $names, callable $payDays): array {
        if ($paidInPeriod === []) {
            return [];
        }

        $box = function (string $label, array $values, ?int $customerId): array {
            sort($values);

            return [
                'x' => $label,
                'min' => (float) $values[0],
                'q1' => round($this->percentile($values, 0.25), 1),
                'median' => round($this->percentile($values, 0.5), 1),
                'q3' => round($this->percentile($values, 0.75), 1),
                'max' => (float) $values[count($values) - 1],
                'n' => count($values),
                'customerId' => $customerId,
            ];
        };

        $all = array_map($payDays, $paidInPeriod);
        $result = [$box((string) __('Alle Kunden'), $all, null)];

        $byCustomer = [];
        foreach ($paidInPeriod as $inv) {
            $byCustomer[$inv['customerId']][] = $payDays($inv);
        }
        uasort($byCustomer, static fn(array $a, array $b): int => count($b) <=> count($a));
        foreach (array_slice($byCustomer, 0, 5, true) as $cid => $values) {
            if (count($values) < 3) {
                continue;
            }
            $result[] = $box($names[$cid] ?? ('#' . $cid), $values, (int) $cid);
        }

        return $result;
    }

    /**
     * @param  list<array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>  $withDue
     * @param  array<int, string>  $names
     * @param  callable(array{dueOn:?string, paidOn:?string}): int  $delayDays
     * @return list<array{customerId:int, customerName:string, avgDelay:float, invoices:int}>
     */
    private function delayTop(array $withDue, array $names, callable $delayDays): array {
        $byCustomer = [];
        foreach ($withDue as $inv) {
            $byCustomer[$inv['customerId']][] = $delayDays($inv);
        }

        $rows = [];
        foreach ($byCustomer as $cid => $delays) {
            $rows[] = [
                'customerId' => (int) $cid,
                'customerName' => $names[$cid] ?? ('#' . $cid),
                'avgDelay' => round(array_sum($delays) / count($delays), 1),
                'invoices' => count($delays),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $b['avgDelay'] <=> $a['avgDelay']);

        return array_slice($rows, 0, 10);
    }

    /**
     * Lineare Interpolation zwischen den Rängen (Standard-Perzentil).
     *
     * @param  list<int|float>  $sorted  aufsteigend sortiert, nicht leer
     */
    private function percentile(array $sorted, float $p): float {
        $n = count($sorted);
        if ($n === 1) {
            return (float) $sorted[0];
        }

        $idx = ($n - 1) * $p;
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);

        return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($idx - $lo);
    }
}
