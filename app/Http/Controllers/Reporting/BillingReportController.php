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
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Customer, Invoice, TimeEntry};
use App\Services\Reporting\ReportFilters;
use Carbon\Carbon;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Billing-/Rechnungs-Auswertung: Status, Aging, Top-Kunden, unbillte Zeiten.
 * Für Administratoren und Rollen mit timeEntry.viewAny (Buchhaltung, MVP-460).
 */
class BillingReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        abort_unless($this->viewerSeesAllTimes(), 403);

        // Bewusst Datumsstrings: die Queries filtern teils DATETIME-Spalten
        // (created_at/issued_on) über Datumsgrenzen — Timestamps wären eine
        // Verhaltensänderung.
        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();
        $today = Carbon::today();

        // Standard-Set (Feature 002): Kunde/Projekt wirken auf Rechnungs- und
        // Angebotsaggregate, Mitarbeiter nur auf die Zeiten (Rechnungen kennen
        // keinen Bearbeiter). Der E-Rechnungs-Eingang bleibt org-weit —
        // IncomingEInvoice trägt keinen Kunden-/Projektanker. Status bewusst
        // NICHT im Set: der Rechnungsstatus ist hier Ausweis-Dimension
        // (Tabellen je Status), kein Filter. include_excluded blendet org-weit
        // ausgeblendete Kunden aus (Rechnungen/Angebote/Zeiten).
        $filterFields = ['customer', 'project', 'user', 'include_excluded'];
        $filters = $this->standardFilters($request, $filterFields, $fromDate, $toDate);

        $status = $this->aggregateByStatus($from, $to, $filters);
        $aging = $this->aggregateAging($today, $filters);
        $perCustomer = $this->aggregatePerCustomer($from, $to, $filters);
        $unbilled = $this->aggregateUnbilled($from, $to, $filters);
        $einvoicing = $this->aggregateEInvoicing($from, $to);
        // Vollaudit 2026-07 (N18): Angebots-/Belegketten-Kennzahlen.
        $documentChain = $this->aggregateDocumentChain($from, $to, $filters);

        $monthly = $this->monthlyBillableSeries($filters);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($status, $aging, $perCustomer, $unbilled, $einvoicing, $documentChain, $from, $to, $filters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($status, $aging, $perCustomer, $unbilled, $from, $to, $monthly, $filters, $request);
        }

        return view('reports.billing', [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'aging' => $aging,
            'perCustomer' => $perCustomer,
            'unbilled' => $unbilled,
            'einvoicing' => $einvoicing,
            'documentChain' => $documentChain,
            'standardFilters' => $filters,
            'filterFields' => $filterFields,
            'monthlyBillableSeries' => $monthly['series'],
            'billableBands' => $monthly['bands'],
            'customerRevenueSeries' => $this->customerRevenueSeries($perCustomer, $filters),
            ...$this->standardFilterOptions($filterFields, $filters),
        ]);
    }

    /**
     * Abrechenbare vs. nicht abrechenbare Stunden je Monat (Feature 002) —
     * aus dem billable-Flag der Zeiten; leere Serie statt Null-Bändern.
     *
     * @return array{series: list<array{x: string, billable: float, non_billable: float}>, bands: list<array{key: string, label: string}>}
     */
    private function monthlyBillableSeries(ReportFilters $filters): array {
        $bands = [
            ['key' => 'billable', 'label' => (string) __('Abrechenbar')],
            ['key' => 'non_billable', 'label' => (string) __('Nicht abrechenbar')],
        ];

        /** @var Collection<int, TimeEntry> $entries */
        $entries = $filters->applyToTimeEntryQuery(
            TimeEntry::query()->whereBetween('date', [$filters->from->toDateString(), $filters->to->toDateString()])
        )->get(['date', 'minutes', 'billable']);

        if ($entries->isEmpty()) {
            return ['series' => [], 'bands' => $bands]; // Leerzustand (§Diagramm-UX).
        }

        /** @var array<string, array{billable: int, non_billable: int}> $byMonth */
        $byMonth = [];
        foreach ($entries as $entry) {
            $key = $entry->date?->format('Y-m');
            if ($key === null) {
                continue;
            }
            $byMonth[$key] ??= ['billable' => 0, 'non_billable' => 0];
            $byMonth[$key][$entry->billable ? 'billable' : 'non_billable'] += (int) $entry->minutes;
        }

        $series = [];
        for ($cursor = $filters->from->startOfMonth(); $cursor->lte($filters->to); $cursor = $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            $series[] = [
                'x' => $cursor->translatedFormat('M Y'),
                'billable' => round(($byMonth[$key]['billable'] ?? 0) / 60, 1),
                'non_billable' => round(($byMonth[$key]['non_billable'] ?? 0) / 60, 1),
            ];
        }

        return ['series' => $series, 'bands' => $bands];
    }

    /**
     * Umsatz (Brutto, ausgestellt + bezahlt) je Kunde, Top 15 — der Report
     * weist keine OFFENEN Beträge je Kunde aus (Aging ist org-weit ohne
     * Kundendimension), daher Pareto über den bestehenden Umsatz-Ausweis.
     * Drilldown öffnet den Kunden im Kunden-&-Projekte-Report.
     *
     * @param  array<int, array{customer: Customer, count: int, total: float}>  $perCustomer
     * @return list<array{x: string, y: float, url: string}>
     */
    private function customerRevenueSeries(array $perCustomer, ReportFilters $filters): array {
        return array_values(collect($perCustomer)
            ->filter(static fn(array $row): bool => $row['total'] > 0.0)
            ->take(15)
            ->map(fn(array $row): array => [
                'x' => (string) $row['customer']->name,
                'y' => round($row['total'], 2),
                'url' => route('reports.customer-project', array_merge($filters->toQueryParams(), [
                    'customer' => (string) $row['customer']->sqid,
                ])),
            ])
            ->all());
    }

    /**
     * Kunden-/Projektfilter auf eine Rechnungs-Query anwenden (Feature 002),
     * inkl. Ausblendung org-weit ausgeblendeter Kunden (Übersteuerungsregel wie
     * ReportFilters::customerExclusionActive(): nur ohne explizite Wahl).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Invoice>
     */
    private function applyInvoiceFilters($query, ReportFilters $filters) {
        return $query
            ->when($filters->customerId !== null, fn($q) => $q->where('customer_id', $filters->customerId))
            ->when($filters->projectId !== null, fn($q) => $q->where('project_id', $filters->projectId))
            // NOT IN würde NULL-Kunden mit verwerfen — kundenlose Belege bleiben sichtbar.
            ->when($this->activeExcludedCustomerIds($filters) !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $this->activeExcludedCustomerIds($filters)),
            ));
    }

    /**
     * Feature 002: Ausblendung greift nur ohne explizite Kunden-/Projektwahl.
     *
     * @return list<int>
     */
    private function activeExcludedCustomerIds(ReportFilters $filters): array {
        return $filters->customerId === null && $filters->projectId === null
            ? $filters->excludedCustomerIds
            : [];
    }

    /**
     * Eingangs-/Validierungs-/Übergabe-Kennzahlen (Feature 066, MVP-169):
     * Prüfbereich nach Status, Validierungsquote beim Empfang, Übergaben an
     * die Buchhaltung und Mahnstufen der offenen Ausgangsrechnungen.
     *
     * @return array{
     *   incoming: array<string, array{count:int, gross:float}>,
     *   incoming_transferred: int,
     *   validation: array{checked:int, passed:int, failed:int},
     *   dunning: array<int, int>
     * }
     */
    private function aggregateEInvoicing(string $from, string $to): array {
        $incoming = [];
        $validation = ['checked' => 0, 'passed' => 0, 'failed' => 0];
        $transferred = 0;

        /** @var Collection<int, \App\Models\IncomingEInvoice> $records */
        $records = \App\Models\IncomingEInvoice::query()
            ->whereBetween('received_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get(['status', 'summary', 'transferred_at']);

        foreach ($records as $record) {
            $st = (string) $record->status;
            $incoming[$st] ??= ['count' => 0, 'gross' => 0.0];
            $incoming[$st]['count']++;
            $incoming[$st]['gross'] += (float) data_get($record->summary, 'gross', 0);
            if ($record->transferred_at !== null) {
                $transferred++;
            }

            $result = data_get($record->summary, 'validation');
            if (is_array($result)) {
                $validation['checked']++;
                $failed = ($result['schema_errors'] ?? []) !== []
                    || ($result['kosit_valid'] ?? null) === false;
                $failed ? $validation['failed']++ : $validation['passed']++;
            }
        }

        $dunning = [1 => 0, 2 => 0, 3 => 0];
        /** @var Collection<int, Invoice> $dunned */
        $dunned = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
            ->where('dunning_level', '>', 0)
            ->get(['dunning_level']);
        foreach ($dunned as $inv) {
            $level = min(3, max(1, (int) $inv->dunning_level));
            $dunning[$level]++;
        }

        return [
            'incoming' => $incoming,
            'incoming_transferred' => $transferred,
            'validation' => $validation,
            'dunning' => $dunning,
        ];
    }

    /**
     * Angebots-/Belegketten-Kennzahlen (Vollaudit 2026-07, N18): Quotes je
     * Status, Annahmequote, Median Versand→Entscheidung, Pro-forma→Rechnung-
     * Überführungen und Storno-/Korrekturquote nach Belegtyp.
     *
     * @return array{
     *     quotes: array<string, int>,
     *     acceptance_rate: float|null,
     *     decision_median_days: float|null,
     *     conversions: array{quote_to_invoice: int, proforma_to_invoice: int},
     *     correction: array{invoices: int, cancellations: int, credit_notes: int, rate: float|null}
     * }
     */
    private function aggregateDocumentChain(string $from, string $to, ReportFilters $filters): array {
        $quotes = \App\Models\Quote::query()
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->when($filters->customerId !== null, fn($q) => $q->where('customer_id', $filters->customerId))
            ->when($filters->projectId !== null, fn($q) => $q->where('project_id', $filters->projectId))
            // Feature 002: Angebote org-weit ausgeblendeter Kunden entfallen (NULL-Kunden bleiben).
            ->when($this->activeExcludedCustomerIds($filters) !== [], fn($q) => $q->where(
                fn($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $this->activeExcludedCustomerIds($filters)),
            ))
            ->get(['status', 'decided_at', 'updated_at', 'decision_snapshot', 'created_at']);

        $byStatus = [];
        /** @var list<float> $decisionDays */
        $decisionDays = [];
        foreach ($quotes as $quote) {
            $byStatus[(string) $quote->status] = ($byStatus[(string) $quote->status] ?? 0) + 1;
            $decidedAt = $quote->decided_at ?? data_get($quote->decision_snapshot, 'decided_at');
            if ($decidedAt !== null) {
                $hours = Carbon::parse((string) $quote->created_at)->diffInHours(Carbon::parse((string) $decidedAt));
                $decisionDays[] = max(0.0, (float) $hours / 24);
            }
        }

        $accepted = ($byStatus['accepted'] ?? 0) + ($byStatus['partially_accepted'] ?? 0);
        $decided = $accepted + ($byStatus['rejected'] ?? 0) + ($byStatus['expired'] ?? 0);
        sort($decisionDays);
        $n = count($decisionDays);
        $median = $n === 0 ? null : round(
            $n % 2 === 1 ? $decisionDays[intdiv($n, 2)] : ($decisionDays[$n / 2 - 1] + $decisionDays[$n / 2]) / 2,
            1,
        );

        $invoices = $this->applyInvoiceFilters(
            Invoice::query()->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']),
            $filters,
        )->get(['type', 'quote_id', 'parent_invoice_id']);
        $regular = $invoices->where('type', Invoice::TYPE_INVOICE);

        return [
            'quotes' => $byStatus,
            'acceptance_rate' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
            'decision_median_days' => $median,
            'conversions' => [
                'quote_to_invoice' => $regular->whereNotNull('quote_id')->count(),
                'proforma_to_invoice' => $regular->whereNotNull('parent_invoice_id')->count(),
            ],
            'correction' => [
                'invoices' => $regular->count(),
                'cancellations' => $invoices->where('type', Invoice::TYPE_CANCELLATION)->count(),
                'credit_notes' => $invoices->where('type', Invoice::TYPE_CREDIT_NOTE)->count(),
                'rate' => $regular->count() > 0
                    ? round(($invoices->where('type', Invoice::TYPE_CANCELLATION)->count() + $invoices->where('type', Invoice::TYPE_CREDIT_NOTE)->count()) / $regular->count() * 100, 1)
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, array{count:int, subtotal:float, tax:float, total:float}>
     */
    private function aggregateByStatus(string $from, string $to, ReportFilters $filters): array {
        $statuses = Invoice::STATUSES;
        $result = [];
        foreach ($statuses as $st) {
            $result[$st] = ['count' => 0, 'subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
        }

        /** @var Collection<int, Invoice> $invoices */
        $invoices = $this->applyInvoiceFilters(
            Invoice::query()
                ->where(function ($w) use ($from, $to): void {
                    $w->whereBetween('issued_on', [$from, $to])
                        ->orWhere(function ($w2) use ($from, $to): void {
                            $w2->whereNull('issued_on')->whereBetween('created_at', [$from, $to]);
                        });
                }),
            $filters,
        )->get(['status', 'subtotal', 'tax_amount', 'total']);

        foreach ($invoices as $inv) {
            $st = $inv->status;
            if (! isset($result[$st])) {
                $result[$st] = ['count' => 0, 'subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0];
            }
            $result[$st]['count']++;
            $result[$st]['subtotal'] += ($inv->subtotal?->toFloat() ?? 0.0);
            $result[$st]['tax'] += ($inv->tax_amount?->toFloat() ?? 0.0);
            $result[$st]['total'] += ($inv->total?->toFloat() ?? 0.0);
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
    private function aggregateAging(Carbon $today, ReportFilters $filters): array {
        $buckets = [
            'current' => ['count' => 0, 'total' => 0.0],
            '1_7' => ['count' => 0, 'total' => 0.0],
            '8_14' => ['count' => 0, 'total' => 0.0],
            '15_30' => ['count' => 0, 'total' => 0.0],
            '30_plus' => ['count' => 0, 'total' => 0.0],
        ];

        /** @var Collection<int, Invoice> $invoices */
        $invoices = $this->applyInvoiceFilters(
            Invoice::query()->where('status', Invoice::STATUS_ISSUED),
            $filters,
        )->get(['due_on', 'issued_on', 'total']);

        $openTotal = 0.0;
        foreach ($invoices as $inv) {
            $total = ($inv->total?->toFloat() ?? 0.0);
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
    private function aggregatePerCustomer(string $from, string $to, ReportFilters $filters): array {
        /** @var Collection<int, Invoice> $invoices */
        $invoices = $this->applyInvoiceFilters(
            Invoice::query()
                ->whereBetween('issued_on', [$from, $to])
                ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID]),
            $filters,
        )->get(['customer_id', 'total']);

        /** @var array<int, array{count:int, total:float}> $agg */
        $agg = [];
        foreach ($invoices as $inv) {
            $cid = (int) $inv->customer_id;
            if (! isset($agg[$cid])) {
                $agg[$cid] = ['count' => 0, 'total' => 0.0];
            }
            $agg[$cid]['count']++;
            $agg[$cid]['total'] += ($inv->total?->toFloat() ?? 0.0);
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
     * Billable TimeEntries im Zeitraum, die noch kein Abrechnungspfad verbraucht hat.
     *
     * @return array{count:int, minutes:int, projected_revenue:float}
     */
    private function aggregateUnbilled(string $from, string $to, ReportFilters $filters): array {
        // MVP-460: exported ist der kanonische Verbraucht-Flag aller drei
        // Abrechnungspfade (Rechnung, Kontomodus, Faktura-Übergabe). Der
        // frühere Abgleich gegen invoice_items.time_entry_id übersah die per
        // Pivot gebündelten Nicht-Primär-Einträge — die KPI war überhöht.
        /** @var Collection<int, TimeEntry> $entries */
        $entries = $filters->applyToTimeEntryQuery(
            TimeEntry::query()
                ->where('billable', true)
                ->whereBetween('date', [$from, $to])
                ->where('exported', false)
        )->get(['minutes', 'rate']);

        $minutes = 0;
        $revenue = 0.0;
        foreach ($entries as $e) {
            $minutes += (int) $e->minutes;
            // TimeEntry.rate (kanonischer Abrechnungs-Snapshot vom RateCalculator) summieren
            // statt neu zu rechnen — vermeidet Rundungs-/Tarif-Drift.
            $revenue += ($e->rate?->toFloat() ?? 0.0);
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
     * @param  array{incoming: array<string, array{count:int, gross:float}>, incoming_transferred: int, validation: array{checked:int, passed:int, failed:int}, dunning: array<int, int>}  $einvoicing
     */
    /**
     * @param  array<string, mixed>  $status
     * @param  array<string, mixed>  $aging
     * @param  array<int, array<string, mixed>>  $perCustomer
     * @param  array<string, mixed>  $unbilled
     * @param  array<string, mixed>  $einvoicing
     * @param  array{quotes: array<string, int>, acceptance_rate: float|null, decision_median_days: float|null, conversions: array{quote_to_invoice: int, proforma_to_invoice: int}, correction: array{invoices: int, cancellations: int, credit_notes: int, rate: float|null}}  $documentChain
     */
    private function exportCsv(array $status, array $aging, array $perCustomer, array $unbilled, array $einvoicing, array $documentChain, string $from, string $to, ReportFilters $filters, Request $request): Response {
        $filename = sprintf('billing_%s_%s.csv', $from, $to);
        $rows = [];
        $rows[] = ['Bereich', 'Schlüssel', 'Anzahl', 'Wert €'];
        foreach ($status as $st => $s) {
            $rows[] = ['Status', $st, $s['count'], NumberHelper::toUSFormat($s['total'], 2)];
        }
        foreach ($aging['buckets'] as $k => $b) {
            $rows[] = ['Aging', $k, $b['count'], NumberHelper::toUSFormat($b['total'], 2)];
        }
        $rows[] = ['Aging', 'OFFEN_SUMME', '', NumberHelper::toUSFormat($aging['open_total'], 2)];
        foreach ($perCustomer as $r) {
            $rows[] = ['Kunde', $r['customer']->name, $r['count'], NumberHelper::toUSFormat($r['total'], 2)];
        }
        $rows[] = ['Unbillte Zeit', 'Einträge', $unbilled['count'], ''];
        $rows[] = ['Unbillte Zeit', 'Minuten', $unbilled['minutes'], ''];
        $rows[] = ['Unbillte Zeit', 'Projiziert', '', NumberHelper::toUSFormat($unbilled['projected_revenue'], 2)];
        foreach ($einvoicing['incoming'] as $st => $s) {
            $rows[] = ['Eingang', $st, $s['count'], NumberHelper::toUSFormat($s['gross'], 2)];
        }
        $rows[] = ['Eingang', 'UEBERGEBEN', $einvoicing['incoming_transferred'], ''];
        $rows[] = ['Eingangs-Validierung', 'geprüft', $einvoicing['validation']['checked'], ''];
        $rows[] = ['Eingangs-Validierung', 'bestanden', $einvoicing['validation']['passed'], ''];
        $rows[] = ['Eingangs-Validierung', 'fehlgeschlagen', $einvoicing['validation']['failed'], ''];
        foreach ($einvoicing['dunning'] as $level => $count) {
            $rows[] = ['Mahnstufe', (string) $level, $count, ''];
        }
        // Vollaudit 2026-07 (N18): Angebots-/Belegketten-Block.
        foreach ($documentChain['quotes'] as $st => $count) {
            $rows[] = ['Angebote', $st, $count, ''];
        }
        $rows[] = ['Angebote', 'ANNAHMEQUOTE_%', '', $documentChain['acceptance_rate'] !== null ? NumberHelper::toUSFormat($documentChain['acceptance_rate'], 1) : ''];
        $rows[] = ['Angebote', 'MEDIAN_ENTSCHEIDUNG_TAGE', '', $documentChain['decision_median_days'] !== null ? NumberHelper::toUSFormat($documentChain['decision_median_days'], 1) : ''];
        $rows[] = ['Belegkette', 'ANGEBOT_ZU_RECHNUNG', $documentChain['conversions']['quote_to_invoice'], ''];
        $rows[] = ['Belegkette', 'PROFORMA_ZU_RECHNUNG', $documentChain['conversions']['proforma_to_invoice'], ''];
        $rows[] = ['Korrektur', 'STORNOS', $documentChain['correction']['cancellations'], ''];
        $rows[] = ['Korrektur', 'GUTSCHRIFTEN', $documentChain['correction']['credit_notes'], ''];
        $rows[] = ['Korrektur', 'QUOTE_%', '', $documentChain['correction']['rate'] !== null ? NumberHelper::toUSFormat($documentChain['correction']['rate'], 1) : ''];

        return $this->csvWithMetadata($rows, $filename, 'billing', $filters->toAuditArray(), $request);
    }

    /**
     * @param  array<string, array{count:int, subtotal:float, tax:float, total:float}>  $status
     * @param  array{buckets: array<string, array{count:int, total:float}>, open_total: float}  $aging
     * @param  array<int, array{customer: Customer, count:int, total:float}>  $perCustomer
     * @param  array{count:int, minutes:int, projected_revenue:float}  $unbilled
     * @param  array{series: list<array{x: string, billable: float, non_billable: float}>, bands: list<array{key: string, label: string}>}  $monthly
     */
    private function exportPdf(array $status, array $aging, array $perCustomer, array $unbilled, string $from, string $to, array $monthly, ReportFilters $filters, Request $request): SymfonyResponse {
        $filename = sprintf('billing_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.billing', [
            'status' => $status,
            'aging' => $aging,
            'perCustomer' => $perCustomer,
            'unbilled' => $unbilled,
            'from' => $from,
            'to' => $to,
            'chart' => [
                'type' => 'stacked-bar-h',
                'title' => __('Abrechenbare und nicht abrechenbare Stunden je Monat'),
                'unit' => 'h',
                'xLabel' => __('Monat'),
                'series' => $monthly['series'],
                'bands' => $monthly['bands'],
            ],
        ], $filename, request: $request, reportCode: 'billing', filters: $filters->toAuditArray());
    }
}
