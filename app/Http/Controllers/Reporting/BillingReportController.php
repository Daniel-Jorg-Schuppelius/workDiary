<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TimeEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Billing-/Rechnungs-Auswertung: Status, Aging, Top-Kunden, unbillte Zeiten.
 * Nur für Administratoren (Org-weite Finanzdaten).
 */
class BillingReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse {
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        abort_unless($isAdmin, 403);

        $range = $this->globalDateRange();
        $from = $range['from']->toDateString();
        $to = $range['to']->toDateString();
        $today = Carbon::today();

        $status = $this->aggregateByStatus($from, $to);
        $aging = $this->aggregateAging($today);
        $perCustomer = $this->aggregatePerCustomer($from, $to);
        $unbilled = $this->aggregateUnbilled($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($status, $aging, $perCustomer, $unbilled, $from, $to);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($status, $aging, $perCustomer, $unbilled, $from, $to);
        }

        return view('reports.billing', [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'aging' => $aging,
            'perCustomer' => $perCustomer,
            'unbilled' => $unbilled,
        ]);
    }

    /**
     * @return array<string, array{count:int, subtotal:float, tax:float, total:float}>
     */
    private function aggregateByStatus(string $from, string $to): array {
        $statuses = Invoice::STATUSES;
        $result = [];
        foreach ($statuses as $st) {
            $result[$st] = ['count' => 0, 'subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
        }

        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->where(function ($w) use ($from, $to): void {
                $w->whereBetween('issued_on', [$from, $to])
                    ->orWhere(function ($w2) use ($from, $to): void {
                        $w2->whereNull('issued_on')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->get(['status', 'subtotal', 'tax_amount', 'total']);

        foreach ($invoices as $inv) {
            $st = $inv->status;
            if (! isset($result[$st])) {
                $result[$st] = ['count' => 0, 'subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
            }
            $result[$st]['count']++;
            $result[$st]['subtotal'] += (float) $inv->subtotal;
            $result[$st]['tax'] += (float) $inv->tax_amount;
            $result[$st]['total'] += (float) $inv->total;
        }

        return $result;
    }

    /**
     * Offene (ausgestellte, nicht bezahlte, nicht stornierte) Rechnungen
     * in Aging-Buckets relativ zu due_on (oder issued_on falls due_on null).
     *
     * @return array{
     *   buckets: array<string, array{count:int, total:float}>,
     *   open_total: float
     * }
     */
    private function aggregateAging(Carbon $today): array {
        $buckets = [
            'current' => ['count' => 0, 'total' => 0.0],
            '1_7' => ['count' => 0, 'total' => 0.0],
            '8_14' => ['count' => 0, 'total' => 0.0],
            '15_30' => ['count' => 0, 'total' => 0.0],
            '30_plus' => ['count' => 0, 'total' => 0.0],
        ];

        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->where('status', Invoice::STATUS_ISSUED)
            ->get(['due_on', 'issued_on', 'total']);

        $openTotal = 0.0;
        foreach ($invoices as $inv) {
            $total = (float) $inv->total;
            $openTotal += $total;
            $reference = $inv->due_on ?? $inv->issued_on;
            if ($reference === null) {
                $buckets['current']['count']++;
                $buckets['current']['total'] += $total;

                continue;
            }
            $daysOverdue = (int) Carbon::parse($reference)->startOfDay()->diffInDays($today, false);
            if ($daysOverdue <= 0) {
                $key = 'current';
            } elseif ($daysOverdue <= 7) {
                $key = '1_7';
            } elseif ($daysOverdue <= 14) {
                $key = '8_14';
            } elseif ($daysOverdue <= 30) {
                $key = '15_30';
            } else {
                $key = '30_plus';
            }
            $buckets[$key]['count']++;
            $buckets[$key]['total'] += $total;
        }

        return ['buckets' => $buckets, 'open_total' => $openTotal];
    }

    /**
     * @return array<int, array{customer: Customer, count:int, total:float}>
     */
    private function aggregatePerCustomer(string $from, string $to): array {
        /** @var Collection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->whereBetween('issued_on', [$from, $to])
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID])
            ->get(['customer_id', 'total']);

        /** @var array<int, array{count:int, total:float}> $agg */
        $agg = [];
        foreach ($invoices as $inv) {
            $cid = (int) $inv->customer_id;
            if (! isset($agg[$cid])) {
                $agg[$cid] = ['count' => 0, 'total' => 0.0];
            }
            $agg[$cid]['count']++;
            $agg[$cid]['total'] += (float) $inv->total;
        }

        if ($agg === []) {
            return [];
        }

        /** @var Collection<int, Customer> $customers */
        $customers = Customer::query()->whereIn('id', array_keys($agg))->orderBy('name')->get();
        $rows = [];
        foreach ($customers as $c) {
            $cid = (int) $c->id;
            $rows[] = [
                'customer' => $c,
                'count' => $agg[$cid]['count'],
                'total' => $agg[$cid]['total'],
            ];
        }
        usort($rows, static fn($a, $b): int => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * Billable TimeEntries im Zeitraum, die nicht in einer InvoiceItem auftauchen.
     *
     * @return array{count:int, minutes:int, projected_revenue:float}
     */
    private function aggregateUnbilled(string $from, string $to): array {
        $billedIds = InvoiceItem::query()
            ->whereNotNull('time_entry_id')
            ->select('time_entry_id');

        /** @var Collection<int, TimeEntry> $entries */
        $entries = TimeEntry::query()
            ->where('billable', true)
            ->whereBetween('date', [$from, $to])
            ->whereNotIn('id', $billedIds)
            ->get(['minutes', 'hourly_rate']);

        $minutes = 0;
        $revenue = 0.0;
        foreach ($entries as $e) {
            $m = (int) $e->minutes;
            $minutes += $m;
            if ($e->hourly_rate !== null) {
                $revenue += $m / 60.0 * (float) $e->hourly_rate;
            }
        }

        return [
            'count' => $entries->count(),
            'minutes' => $minutes,
            'projected_revenue' => $revenue,
        ];
    }

    /**
     * @param  array<string, array{count:int, subtotal:float, tax:float, total:float}>  $status
     * @param  array{buckets: array<string, array{count:int, total:float}>, open_total: float}  $aging
     * @param  array<int, array{customer: Customer, count:int, total:float}>  $perCustomer
     * @param  array{count:int, minutes:int, projected_revenue:float}  $unbilled
     */
    private function exportCsv(array $status, array $aging, array $perCustomer, array $unbilled, string $from, string $to): Response {
        $filename = sprintf('billing_%s_%s.csv', $from, $to);
        $rows = [];
        $rows[] = ['Bereich', 'Schlüssel', 'Anzahl', 'Wert €'];
        foreach ($status as $st => $s) {
            $rows[] = ['Status', $st, $s['count'], number_format($s['total'], 2, '.', '')];
        }
        foreach ($aging['buckets'] as $k => $b) {
            $rows[] = ['Aging', $k, $b['count'], number_format($b['total'], 2, '.', '')];
        }
        $rows[] = ['Aging', 'OFFEN_SUMME', '', number_format($aging['open_total'], 2, '.', '')];
        foreach ($perCustomer as $r) {
            $rows[] = ['Kunde', $r['customer']->name, $r['count'], number_format($r['total'], 2, '.', '')];
        }
        $rows[] = ['Unbillte Zeit', 'Einträge', $unbilled['count'], ''];
        $rows[] = ['Unbillte Zeit', 'Minuten', $unbilled['minutes'], ''];
        $rows[] = ['Unbillte Zeit', 'Projiziert', '', number_format($unbilled['projected_revenue'], 2, '.', '')];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }

                return $s;
            }, $row)) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param  array<string, array{count:int, subtotal:float, tax:float, total:float}>  $status
     * @param  array{buckets: array<string, array{count:int, total:float}>, open_total: float}  $aging
     * @param  array<int, array{customer: Customer, count:int, total:float}>  $perCustomer
     * @param  array{count:int, minutes:int, projected_revenue:float}  $unbilled
     */
    private function exportPdf(array $status, array $aging, array $perCustomer, array $unbilled, string $from, string $to): SymfonyResponse {
        $filename = sprintf('billing_%s_%s.pdf', $from, $to);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.billing', [
            'status' => $status,
            'aging' => $aging,
            'perCustomer' => $perCustomer,
            'unbilled' => $unbilled,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
