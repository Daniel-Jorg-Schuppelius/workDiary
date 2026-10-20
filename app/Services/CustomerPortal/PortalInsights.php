<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalInsights.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Enums\CustomerPortal\{PortalCapability, PortalTimeDetail};
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Billing\CustomerBillingStatement;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Project\Project;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Billing\CustomerAccountStatementService;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceQuery};
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Auswertungen im Kundenportal (Phase 154, MVP-1242–1244): Kennzahlen und
 * Verläufe je Kalenderjahr — nur aus Bereichen, die der Kunde ohnehin sieht,
 * und aus denselben Grundmengen wie die Listen (L2): Zeiten über
 * {@see PortalTimeEntries}, Rechnungen über die Belegregel der Rechnungsliste
 * (lokale Nicht-Entwürfe plus Belege des Buchhaltungsprogramms),
 * Abrechnungskonto über die Monatsabschlüsse, Tickets über die eigenen Tickets.
 * Jeder Abschnitt liefert null, wenn sein Bereich nicht freigegeben ist.
 *
 * @phpstan-type InvoiceRow array{credit: bool, cancelled: bool, issued: CarbonImmutable, due: ?CarbonImmutable, paid: ?CarbonImmutable, gross: float, open: float, currency: string}
 */
final class PortalInsights {
    /** Mehr Projekte als das wandern in „Sonstige“. */
    private const TOP_PROJECTS = 10;

    /** Bänder im Monat-nach-Projekt-Diagramm (die Komponente hat fünf Füllungen). */
    private const STACK_BANDS = 4;

    /** Unter so vielen gelösten Tickets bleibt die mittlere Lösungszeit ein Hinweis statt einer Zahl. */
    private const MIN_RESOLVED_FOR_AVERAGE = 5;

    /** @var array<int, list<InvoiceRow>> Belege je Kunde, einmal je Request */
    private array $invoiceRows = [];

    public function __construct(
        private readonly PortalVisibility $visibility,
        private readonly PortalTimeEntries $times,
        private readonly ExternalRevenue $revenue,
        private readonly CustomerAccountStatementService $statements,
    ) {}

    /**
     * Jahre mit Daten in einem freigegebenen Bereich, neueste zuerst.
     *
     * @return list<int>
     */
    public function years(Customer $customer): array {
        $years = [];
        if ($this->visibility->timeDetail($customer) !== PortalTimeDetail::None) {
            $years = $this->times->years($customer);
        }
        if ($this->visibility->allows($customer, PortalCapability::Invoices)) {
            foreach ($this->invoiceRows($customer) as $row) {
                $years[] = (int) $row['issued']->year;
            }
            $agreement = $customer->billingAgreement;
            if ($agreement !== null && $agreement->keepsLedger()) {
                array_push($years, ...$agreement->statements()->toBase()->distinct()->pluck('year')->map(static fn (mixed $y): int => (int) $y)->all());
            }
        }
        if ($this->visibility->allows($customer, PortalCapability::Tickets)) {
            $expression = 'SUBSTR(service_tickets.reported_at, 1, 4)';
            array_push($years, ...ServiceTicket::query()->where('customer_id', $customer->id)->whereNotNull('reported_at')
                ->toBase()->selectRaw($expression . ' as year')->groupBy(DB::raw($expression))->pluck('year')
                ->map(static fn (mixed $y): int => (int) $y)->all());
        }
        $years = array_values(array_unique(array_filter($years, static fn (int $y): bool => $y > 0)));
        rsort($years);

        return $years;
    }

    /**
     * Gewähltes Jahr: nur Jahre mit Daten; sonst das laufende, sonst das jüngste.
     *
     * @param  list<int>  $years
     */
    public static function pickYear(int $requested, array $years): int {
        if (in_array($requested, $years, true)) {
            return $requested;
        }
        $current = (int) now()->year;

        return in_array($current, $years, true) ? $current : ($years[0] ?? $current);
    }

    /**
     * Kurzfassung für die Startseite: Stunden und Rechnungsstand des Jahres.
     *
     * @return array{year: int, minutes: ?int, count: ?int, gross: ?float, open: ?float, currency: ?string}
     */
    public function summary(Customer $customer, int $year): array {
        $minutes = $this->visibility->timeDetail($customer) !== PortalTimeDetail::None
            ? (int) $this->times->yearQuery($customer, $year)->sum('time_entries.minutes')
            : null;
        $invoices = $this->invoices($customer, $year);
        $main = $invoices !== null ? ($invoices['currencies'][0] ?? null) : null;

        return [
            'year' => $year,
            'minutes' => $minutes,
            'count' => $main['count'] ?? null,
            'gross' => $main['gross'] ?? null,
            'open' => $main['open'] ?? null,
            'currency' => $main['currency'] ?? null,
        ];
    }

    /**
     * Zeiten des Jahres: Summen, Monate (mit Vorjahr), Projekte, Monat nach Projekt.
     *
     * @return array<string, mixed>|null
     */
    public function time(Customer $customer, int $year): ?array {
        $detail = $this->visibility->timeDetail($customer);
        if ($detail === PortalTimeDetail::None) {
            return null;
        }
        $query = $this->times->yearQuery($customer, $year);
        $byMonth = $this->minutesByMonth(clone $query);
        $oldest = $this->times->oldestVisibleDate($customer);
        $previousVisible = $oldest === null || $oldest->year <= $year - 1;
        $previous = $previousVisible ? $this->minutesByMonth($this->times->yearQuery($customer, $year - 1)) : [];
        $total = array_sum($byMonth);
        $previousTotal = array_sum($previous);

        $monthly = [];
        foreach (range(1, 12) as $month) {
            $point = ['x' => $this->monthLabel($year, $month), 'y' => $this->hours($byMonth[$month] ?? 0), 'url' => $this->timeListUrl($year, $month)];
            if ($previousVisible) {
                $point['compare'] = $this->hours($previous[$month] ?? 0);
            }
            $monthly[] = $point;
        }

        $projectRows = (clone $query)->toBase()
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->selectRaw('projects.id as id, projects.name as name, SUM(time_entries.minutes) as minutes')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('minutes')
            ->orderBy('projects.name')
            ->get();
        $byProject = [];
        $otherMinutes = 0;
        foreach ($projectRows as $index => $row) {
            if ($index < self::TOP_PROJECTS) {
                $byProject[] = ['x' => (string) $row->name, 'y' => $this->hours((int) $row->minutes), 'url' => $this->timeListUrl($year, null, (int) $row->id)];
            } else {
                $otherMinutes += (int) $row->minutes;
            }
        }
        if ($otherMinutes > 0) {
            $byProject[] = ['x' => (string) __('customer_portal.insights.other_projects'), 'y' => $this->hours($otherMinutes), 'url' => null];
        }

        $stacked = null;
        if ($detail->showsEntries() && $projectRows->isNotEmpty()) {
            $topIds = $projectRows->take(self::STACK_BANDS)->map(static fn (object $row): int => (int) $row->id)->all();
            $bands = [];
            foreach ($projectRows->take(self::STACK_BANDS) as $row) {
                $bands[] = ['key' => 'p' . $row->id, 'label' => (string) $row->name];
            }
            if ($projectRows->count() > self::STACK_BANDS) {
                $bands[] = ['key' => 'other', 'label' => (string) __('customer_portal.insights.other_projects'), 'hatch' => true];
            }
            $cells = (clone $query)->toBase()
                ->selectRaw('SUBSTR(time_entries.date, 6, 2) as month, time_entries.project_id as project_id, SUM(time_entries.minutes) as minutes')
                ->groupBy(DB::raw('SUBSTR(time_entries.date, 6, 2)'), 'time_entries.project_id')
                ->get();
            /** @var array<int, array<string, mixed>> $rows */
            $rows = [];
            foreach (range(1, 12) as $month) {
                $row = ['x' => $this->monthLabel($year, $month), 'url' => $this->timeListUrl($year, $month)];
                foreach ($bands as $band) {
                    $row[$band['key']] = 0.0;
                }
                $rows[$month] = $row;
            }
            foreach ($cells as $cell) {
                $month = (int) $cell->month;
                $key = in_array((int) $cell->project_id, $topIds, true) ? 'p' . $cell->project_id : 'other';
                if (isset($rows[$month]) && array_key_exists($key, $rows[$month])) {
                    $rows[$month][$key] += $this->hours((int) $cell->minutes);
                }
            }
            $stacked = ['series' => array_values($rows), 'bands' => $bands];
        }

        return [
            'minutes' => $total,
            'previous_minutes' => $previousVisible ? $previousTotal : null,
            'entries' => (clone $query)->count(),
            'projects' => $projectRows->count(),
            'monthly' => $total > 0 || $previousTotal > 0 ? $monthly : [],
            'by_project' => $byProject,
            'stacked' => $stacked,
            'detail' => $detail,
        ];
    }

    /**
     * Rechnungen des Jahres je Währung: Kennzahlen, Beträge je Monat
     * (Gutschriften gesondert), offener Betrag je Monatsende, Zahlungsstand.
     *
     * @return array{currencies: list<array<string, mixed>>}|null
     */
    public function invoices(Customer $customer, int $year): ?array {
        if (! $this->visibility->allows($customer, PortalCapability::Invoices)) {
            return null;
        }
        $rows = $this->invoiceRows($customer);
        $today = CarbonImmutable::today();
        $currencies = [];
        $currencyCodes = [];
        foreach ($rows as $row) {
            $currencyCodes[$row['currency']] = true;
        }
        foreach (array_keys($currencyCodes) as $currency) {
            $all = array_values(array_filter($rows, static fn (array $row): bool => $row['currency'] === $currency && ! $row['cancelled']));
            $inYear = array_values(array_filter($all, static fn (array $row): bool => $row['issued']->year === $year));
            $invoices = array_values(array_filter($inYear, static fn (array $row): bool => ! $row['credit']));
            $credits = array_values(array_filter($inYear, static fn (array $row): bool => $row['credit']));
            $openRows = array_values(array_filter($invoices, static fn (array $row): bool => $row['open'] > 0));
            $overdue = array_values(array_filter($openRows, static fn (array $row): bool => $row['due'] !== null && $row['due']->lt($today)));

            $monthly = [];
            $table = [];
            $hasMonthly = false;
            foreach (range(1, 12) as $month) {
                $gross = $this->sum(array_filter($invoices, static fn (array $row): bool => $row['issued']->month === $month), 'gross');
                $credit = $this->sum(array_filter($credits, static fn (array $row): bool => $row['issued']->month === $month), 'gross');
                $open = $this->sum(array_filter($openRows, static fn (array $row): bool => $row['issued']->month === $month), 'open');
                $count = count(array_filter($invoices, static fn (array $row): bool => $row['issued']->month === $month));
                $hasMonthly = $hasMonthly || $gross > 0 || $credit > 0;
                $monthly[] = ['x' => $this->monthLabel($year, $month), 'y' => $gross, 'y2' => $credit > 0 ? $credit : null, 'url' => $this->invoiceListUrl($year, $month)];
                $table[] = ['month' => $this->monthLabel($year, $month, true), 'count' => $count, 'gross' => $gross, 'credit' => $credit, 'open' => $open, 'url' => $this->invoiceListUrl($year, $month)];
            }

            // Offener Betrag je Monatsende: ausgestellt bis dahin und bis dahin nicht bezahlt
            // (Teilzahlungen gelten bis zur vollständigen Zahlung als offen).
            $trend = [];
            $hasTrend = false;
            $claims = array_values(array_filter($all, static fn (array $row): bool => ! $row['credit']));
            foreach (range(1, 12) as $month) {
                $monthEnd = $this->monthStart($year, $month)->endOfMonth();
                $openAt = 0.0;
                foreach ($claims as $row) {
                    if ($row['issued']->gt($monthEnd)) {
                        continue;
                    }
                    $settledBy = $row['paid'] !== null ? $row['paid']->lte($monthEnd) : $row['open'] <= 0;
                    if (! $settledBy) {
                        $openAt += $row['open'] > 0 ? $row['open'] : $row['gross'];
                    }
                }
                $hasTrend = $hasTrend || $openAt > 0;
                $trend[] = ['x' => $this->monthLabel($year, $month), 'y' => round($openAt, 2), 'url' => $this->invoiceListUrl($year, $month, 'open')];
            }

            $currencies[] = [
                'currency' => $currency,
                'count' => count($invoices),
                'gross' => $this->sum($invoices, 'gross'),
                'credit' => $this->sum($credits, 'gross'),
                'open' => $this->sum($openRows, 'open'),
                'open_count' => count($openRows),
                'overdue' => $this->sum($overdue, 'open'),
                'overdue_count' => count($overdue),
                'monthly' => $hasMonthly ? $monthly : [],
                'open_trend' => $hasTrend ? $trend : [],
                'table' => $table,
                'has_year_data' => $inYear !== [],
            ];
        }
        // Hauptwährung zuerst: die mit den meisten Belegen im Jahr.
        usort($currencies, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['currency'], $b['currency']));

        return ['currencies' => $currencies];
    }

    /**
     * Abrechnungskonto: Saldo je Monatsende des Jahres und der jüngste Monat.
     *
     * @return array<string, mixed>|null
     */
    public function billing(Customer $customer, int $year): ?array {
        if (! $this->visibility->allows($customer, PortalCapability::Invoices)) {
            return null;
        }
        $agreement = $customer->billingAgreement;
        if ($agreement === null || ! $agreement->keepsLedger()) {
            return null;
        }
        $this->statements->recalculateOpen($agreement);
        /** @var \Illuminate\Database\Eloquent\Collection<int, CustomerBillingStatement> $statements */
        $statements = $agreement->statements()->where('year', $year)->orderBy('month')->get();
        $series = [];
        foreach ($statements as $statement) {
            $series[] = [
                'x' => $this->monthLabel($year, (int) $statement->month),
                'y' => round($statement->balance?->toFloat() ?? 0.0, 2),
                'url' => route('customer.billing.show', ['year' => $statement->year, 'month' => $statement->month]),
            ];
        }
        $last = $statements->last();

        return [
            'series' => $series,
            'last' => $last === null ? null : [
                'label' => $last->periodLabel(),
                'gross_value' => $last->gross_value?->toFloat() ?? 0.0,
                'payments_total' => $last->payments_total?->toFloat() ?? 0.0,
                'carry_in' => $last->carry_in?->toFloat() ?? 0.0,
                'balance' => $last->balance?->toFloat() ?? 0.0,
                'locked' => (bool) $last->locked,
                'url' => route('customer.billing.show', ['year' => $last->year, 'month' => $last->month]),
            ],
        ];
    }

    /**
     * Tickets des Jahres: eröffnet und gelöst je Monat, mittlere Lösungszeit.
     *
     * @return array<string, mixed>|null
     */
    public function tickets(Customer $customer, int $year): ?array {
        if (! $this->visibility->allows($customer, PortalCapability::Tickets)) {
            return null;
        }
        $start = DateRange::dayStart(sprintf('%04d-01-01', $year));
        $end = DateRange::dayAfter(sprintf('%04d-12-31', $year));
        $tickets = ServiceTicket::query()
            ->where('customer_id', $customer->id)
            ->where(function (Builder $q) use ($start, $end): void {
                $q->where(fn (Builder $w) => $w->where('reported_at', '>=', $start)->where('reported_at', '<', $end))
                    ->orWhere(fn (Builder $w) => $w->where('resolved_at', '>=', $start)->where('resolved_at', '<', $end));
            })
            ->get(['id', 'reported_at', 'resolved_at', 'status']);

        $opened = array_fill(1, 12, 0);
        $resolved = array_fill(1, 12, 0);
        $durations = [];
        foreach ($tickets as $ticket) {
            if ($ticket->reported_at !== null && (int) $ticket->reported_at->year === $year) {
                $opened[(int) $ticket->reported_at->month]++;
            }
            if ($ticket->resolved_at !== null && (int) $ticket->resolved_at->year === $year) {
                $resolved[(int) $ticket->resolved_at->month]++;
                if ($ticket->reported_at !== null) {
                    $durations[] = $ticket->reported_at->diffInSeconds($ticket->resolved_at) / 86400;
                }
            }
        }
        $series = [];
        foreach (range(1, 12) as $month) {
            $series[] = ['x' => $this->monthLabel($year, $month), 'opened' => $opened[$month], 'resolved' => $resolved[$month]];
        }
        $openedTotal = array_sum($opened);
        $resolvedTotal = array_sum($resolved);

        return [
            'opened' => $openedTotal,
            'resolved' => $resolvedTotal,
            'average_days' => count($durations) >= self::MIN_RESOLVED_FOR_AVERAGE ? round(array_sum($durations) / count($durations), 1) : null,
            'resolved_count' => count($durations),
            'series' => $openedTotal > 0 || $resolvedTotal > 0 ? $series : [],
            'bands' => [
                ['key' => 'opened', 'label' => (string) __('customer_portal.insights.opened')],
                ['key' => 'resolved', 'label' => (string) __('customer_portal.insights.resolved'), 'hatch' => true],
            ],
        ];
    }

    /**
     * Belegregel der Rechnungsliste (MVP-1118/1019): lokale Nicht-Entwürfe ohne
     * Pro-forma plus die Belege des Buchhaltungsprogramms. Stornierte Belege
     * bleiben in der Liste, zählen aber in keiner Summe.
     *
     * @return list<InvoiceRow>
     */
    private function invoiceRows(Customer $customer): array {
        if (isset($this->invoiceRows[$customer->id])) {
            return $this->invoiceRows[$customer->id];
        }
        $rows = [];
        $local = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', InvoiceStatus::Draft)
            ->whereNotNull('issued_on')
            ->get();
        foreach ($local as $invoice) {
            if ($invoice->isProforma() || $invoice->issued_on === null) {
                continue;
            }
            $gross = round(abs($invoice->total?->toFloat() ?? 0.0), 2);
            $settled = in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true);
            $rows[] = [
                'credit' => in_array($invoice->type, [Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION], true),
                'cancelled' => $invoice->status === InvoiceStatus::Cancelled,
                'issued' => CarbonImmutable::instance($invoice->issued_on),
                'due' => $invoice->due_on !== null ? CarbonImmutable::instance($invoice->due_on) : null,
                'paid' => $invoice->paid_on !== null ? CarbonImmutable::instance($invoice->paid_on) : null,
                'gross' => $gross,
                'open' => $settled ? 0.0 : $gross,
                'currency' => $invoice->currency->value,
            ];
        }
        foreach ($this->revenue->invoices(new ExternalInvoiceQuery(customerId: (int) $customer->id, organizationId: (int) $customer->organization_id)) as $invoice) {
            $row = $this->externalRow($invoice);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $this->invoiceRows[$customer->id] = $rows;
    }

    /**
     * @return InvoiceRow|null null ohne Belegdatum
     */
    private function externalRow(ExternalInvoice $invoice): ?array {
        if ($invoice->issuedOn === null) {
            return null;
        }

        return [
            'credit' => $invoice->isCreditNote(),
            'cancelled' => $invoice->status === InvoiceStatus::Cancelled,
            'issued' => CarbonImmutable::instance($invoice->issuedOn),
            'due' => $invoice->dueOn !== null ? CarbonImmutable::instance($invoice->dueOn) : null,
            'paid' => $invoice->paidOn !== null ? CarbonImmutable::instance($invoice->paidOn) : null,
            'gross' => round(abs($invoice->gross), 2),
            'open' => round(max(0.0, $invoice->open), 2),
            'currency' => $invoice->currency,
        ];
    }

    /**
     * @param  Builder<\App\Models\Time\TimeEntry>  $query
     * @return array<int, int> Monat (1–12) => Minuten
     */
    private function minutesByMonth(Builder $query): array {
        $expression = 'SUBSTR(time_entries.date, 6, 2)';
        $result = [];
        foreach ($query->toBase()->selectRaw($expression . ' as month, SUM(time_entries.minutes) as minutes')->groupBy(DB::raw($expression))->get() as $row) {
            $result[(int) $row->month] = (int) $row->minutes;
        }

        return $result;
    }

    /**
     * @param  iterable<InvoiceRow>  $rows
     * @param  'gross'|'open'  $field
     */
    private function sum(iterable $rows, string $field): float {
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) $row[$field];
        }

        return round($sum, 2);
    }

    private function hours(int $minutes): float {
        return round($minutes / 60, 2);
    }

    private function monthLabel(int $year, int $month, bool $long = false): string {
        return $this->monthStart($year, $month)->isoFormat($long ? 'MMMM YYYY' : 'MMM');
    }

    private function monthStart(int $year, int $month): CarbonImmutable {
        return CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month));
    }

    private function timeListUrl(int $year, ?int $month, ?int $projectId = null): string {
        $params = ['year' => $year];
        if ($month !== null) {
            $first = $this->monthStart($year, $month);
            $params['from'] = $first->format('Y-m-d');
            $params['to'] = $first->endOfMonth()->format('Y-m-d');
        }
        if ($projectId !== null) {
            $params['project'] = Sqid::encode(Project::class, $projectId);
        }

        return route('customer.time-entries.index', $params);
    }

    private function invoiceListUrl(int $year, ?int $month, ?string $status = null): string {
        return route('customer.invoices.index', array_filter(['year' => $year, 'month' => $month, 'status' => $status]));
    }
}
