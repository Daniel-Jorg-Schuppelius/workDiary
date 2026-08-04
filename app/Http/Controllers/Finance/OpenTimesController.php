<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenTimesController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Finance;

use App\Enums\Diary\Status;
use App\Http\Controllers\Controller;
use App\Models\{Customer, DiaryEntry, Project, TimeEntry, User};
use App\Services\Invoicing\LateTimeEntryDetector;
use App\Support\{CsvExport, Formats, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;

/**
 * Offene Zeiten (MVP-460): Arbeitsliste der Buchhaltung über alle noch nicht
 * abgerechneten Zeiteinträge (exported = false) — org-weit, mit Summen je
 * Kunde/Projekt. Sicht-Gate ist die Permission timeEntry.viewAny (E1);
 * `exported` ist der kanonische Verbraucht-Flag aller Abrechnungspfade
 * (InvoiceGenerator, Kontomodus, Faktura-Übergabe).
 *
 * Zeitraum: die Liste ist bewusst zeitraum-los — nur explizite from/to-Filter
 * schränken ein, die Header-Zeitauswahl gilt hier NICHT. Sonst verschwinden
 * Kunden, deren offene Zeiten komplett vor dem gewählten Fenster liegen,
 * lautlos aus der Arbeitsliste. Die Warn-KPIs (Nachzügler, älter als 45 Tage)
 * und der Prüfhinweis ignorieren zusätzlich auch die expliziten Filter.
 */
class OpenTimesController extends Controller {
    /** Ab diesem Alter (Tage seit Leistungsdatum) gilt ein offener Eintrag als überfällig. */
    public const STALE_AFTER_DAYS = 45;

    public function __construct(private readonly LateTimeEntryDetector $lateDetector) {}

    public function index(Request $request): View {
        $this->authorizeView($request);

        $filters = $this->filters($request);
        $query = $this->baseQuery($filters);

        $entries = (clone $query)
            ->with(['project.customer:id,name', 'user:id,name'])
            ->paginate(50)
            ->withQueryString();

        // Nachzügler-Badges (MVP-461): jüngstes fakturiertes Leistungsdatum je
        // Kunde der aktuellen Seite; Vergleich pro Zeile in der View.
        $customerIds = [];
        foreach ($entries as $entry) {
            $cid = $entry->project->customer_id ?? null;
            if ($cid !== null) {
                $customerIds[] = (int) $cid;
            }
        }
        $latestBilledByCustomer = $this->lateDetector->latestBilledServiceDates(array_values(array_unique($customerIds)));

        // Warn-KPIs ohne Zeitraumbegrenzung: Altbestand außerhalb des
        // Header-Zeitraums soll sichtbar bleiben (Zweck der Arbeitsliste).
        $unranged = $this->baseQuery($filters, withDateRange: false);

        return view('finance.open-times.index', [
            'entries' => $entries,
            'filters' => $filters,
            'hasActiveFilters' => $this->hasActiveFilters($filters),
            'totals' => $this->totals(clone $query),
            'groups' => $this->groupTotals(clone $query),
            'lateCount' => $this->lateDetector->countLateInQuery(clone $unranged),
            'staleCount' => (clone $unranged)->reorder()
                ->whereDate('time_entries.date', '<', now()->subDays(self::STALE_AFTER_DAYS)->toDateString())
                ->count(),
            'staleAfterDays' => self::STALE_AFTER_DAYS,
            'latestBilledByCustomer' => $latestBilledByCustomer,
            'ledgerManagedCount' => $this->ledgerManagedCount(),
            'invoicedMismatches' => $this->invoicedDiaryMismatches(),
            'canMarkBilled' => $this->canMarkBilled($request),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'customer_id']),
            'users' => User::query()
                ->where('organization_id', $request->user()?->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Prüfhinweis (MVP-461): Aufträge im Status „Berechnet", deren abrechenbare
     * Zeiten noch keinem Abrechnungspfad übergeben wurden — Hinweis auf eine
     * nur im Auftrag, nicht in den Zeiten abgeschlossene Abrechnung.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DiaryEntry>
     */
    private function invoicedDiaryMismatches(): \Illuminate\Database\Eloquent\Collection {
        return DiaryEntry::query()
            ->where('status', Status::Invoiced)
            ->whereHas('timeEntries', fn(Builder $q) => $q->where('billable', true)->where('exported', false))
            ->with('customer:id,name')
            ->withCount(['timeEntries as open_time_entries_count' => fn(Builder $q) => $q->where('billable', true)->where('exported', false)])
            ->orderByDesc('invoiced_at')
            ->limit(20)
            ->get();
    }

    public function export(Request $request): Response {
        $this->authorizeView($request);

        $filters = $this->filters($request);
        $entries = $this->baseQuery($filters)
            ->with(['project.customer:id,name', 'user:id,name'])
            ->get();

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                $entry->date?->format('d.m.Y') ?? '',
                $entry->project->customer->name ?? '',
                $entry->project->name ?? '',
                $entry->user->name ?? '',
                (string) $entry->description,
                $entry->billable ? __('Ja') : __('Nein'),
                Formats::duration((int) $entry->minutes, 'clock'),
                Formats::duration((int) $entry->minutes, 'decimal', withUnit: false),
                $entry->rate?->toFloat() ?? 0.0,
            ];
        }

        $csv = CsvExport::toString([
            __('finance.open_times.column.date'),
            __('finance.open_times.column.customer'),
            __('finance.open_times.column.project'),
            __('finance.open_times.column.user'),
            __('finance.open_times.column.description'),
            __('finance.open_times.column.billable'),
            __('finance.open_times.column.duration'),
            __('finance.open_times.column.duration_decimal'),
            __('finance.open_times.column.amount'),
        ], $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="offene-zeiten.csv"',
        ]);
    }

    /**
     * Altbestand-Abschluss (Programmeinführung): alle offenen Zeiten bis zu
     * einem Stichtag als abgerechnet (exported) markieren — sie wurden vor
     * der Einführung bereits außerhalb des Systems fakturiert.
     */
    public function markBilledDialog(Request $request): View {
        abort_unless($this->canMarkBilled($request), 403);

        return view('finance.open-times._mark_billed_dialog', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function markBilled(Request $request): RedirectResponse {
        abort_unless($this->canMarkBilled($request), 403);

        $validated = $request->validate([
            'cutoff' => ['required', 'date'],
            'customer' => ['nullable', 'string'],
            'include_non_billable' => ['nullable', 'boolean'],
        ]);

        $customerSqid = (string) ($validated['customer'] ?? '');
        $customerId = Sqid::decode(Customer::class, $customerSqid);
        if ($customerSqid !== '' && ($customerId === null || ! Customer::query()->whereKey($customerId)->exists())) {
            return back()->withErrors(['customer' => __('finance.open_times.mark_billed.error_customer')]);
        }

        $cutoff = CarbonImmutable::parse($validated['cutoff']);

        // Mass-Update bewusst ohne Model-Events — wie InvoiceGenerator: keine
        // Writeback-/Audit-Kaskade für den einmaligen Altbestand-Abschluss.
        // Saldo-geführte Kunden bleiben außen vor: die Aktion darf nie mehr
        // treffen als die Liste zeigt, und dort schließt der Monatsabschluss ab.
        $count = TimeEntry::query()
            ->withoutLedgerManagedCustomers()
            ->where('exported', false)
            ->whereDate('date', '<=', $cutoff->toDateString())
            ->when(! ($validated['include_non_billable'] ?? false), fn($q) => $q->where('billable', true))
            ->when($customerId !== null, fn($q) => $q->whereHas('project', fn($p) => $p->where('customer_id', $customerId)))
            ->update(['exported' => true]);

        return redirect()->route('finance.open-times.index')
            ->with('success', trans_choice('finance.open_times.mark_billed.flash', $count, [
                'count' => $count,
                'date' => $cutoff->format(Formats::date()),
            ]));
    }

    private function authorizeView(Request $request): void {
        $user = $request->user();
        abort_unless($user instanceof User && ($user->isAdmin() || Gate::allows('timeEntry.viewAny')), 403);
    }

    /** Schreibende Massenaktion: Admin oder Buchhaltung (timeEntry.approve). */
    private function canMarkBilled(Request $request): bool {
        $user = $request->user();

        return $user instanceof User && ($user->isAdmin() || Gate::allows('timeEntry.approve'));
    }

    /**
     * @return array{customer:string, project:string, user:string, from:string, to:string, billable:string}
     */
    private function filters(Request $request): array {
        $billable = (string) $request->query('billable', 'yes');

        return [
            'customer' => (string) $request->query('customer', ''),
            'project' => (string) $request->query('project', ''),
            'user' => (string) $request->query('user', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'billable' => in_array($billable, ['yes', 'no', 'all'], true) ? $billable : 'yes',
        ];
    }

    /**
     * @param  array{customer:string, project:string, user:string, from:string, to:string, billable:string}  $filters
     */
    private function hasActiveFilters(array $filters): bool {
        return $filters['customer'] !== '' || $filters['project'] !== '' || $filters['user'] !== ''
            || $filters['from'] !== '' || $filters['to'] !== '' || $filters['billable'] !== 'yes';
    }

    /**
     * Effektiver Listen-Zeitraum: nur explizite from/to-Filter (auch einseitig)
     * schränken ein. Die Header-Zeitauswahl gilt hier bewusst nicht — eine
     * Offene-Posten-Liste darf Altbestand nie lautlos ausblenden.
     *
     * @param  array{customer:string, project:string, user:string, from:string, to:string, billable:string}  $filters
     * @return array{from: ?string, to: ?string}
     */
    private function effectiveRange(array $filters): array {
        return [
            'from' => $filters['from'] !== '' ? $filters['from'] : null,
            'to' => $filters['to'] !== '' ? $filters['to'] : null,
        ];
    }

    /**
     * Offene (nicht abgerechnete) Einträge, sortiert Kunde → Projekt → Datum.
     * Left-Joins nur fürs Sortieren — Einträge ohne Projekt (z. B. Verwaltung)
     * bleiben sichtbar, gerade sie rutschen sonst durch.
     *
     * @param  array{customer:string, project:string, user:string, from:string, to:string, billable:string}  $filters
     * @return Builder<TimeEntry>
     */
    private function baseQuery(array $filters, bool $withDateRange = true): Builder {
        $customerId = Sqid::decode(Customer::class, $filters['customer']);
        $projectId = Sqid::decode(Project::class, $filters['project']);
        $userId = Sqid::decode(User::class, $filters['user']);
        $range = $withDateRange ? $this->effectiveRange($filters) : ['from' => null, 'to' => null];

        return TimeEntry::query()
            ->withoutLedgerManagedCustomers()
            ->where('time_entries.exported', false)
            ->when($filters['billable'] === 'yes', fn($q) => $q->where('time_entries.billable', true))
            ->when($filters['billable'] === 'no', fn($q) => $q->where('time_entries.billable', false))
            ->when($customerId !== null, fn($q) => $q->whereHas('project', fn($p) => $p->where('customer_id', $customerId)))
            ->when($projectId !== null, fn($q) => $q->where('time_entries.project_id', $projectId))
            ->when($userId !== null, fn($q) => $q->where('time_entries.user_id', $userId))
            ->when($range['from'] !== null, fn($q) => $q->whereDate('time_entries.date', '>=', $range['from']))
            ->when($range['to'] !== null, fn($q) => $q->whereDate('time_entries.date', '<=', $range['to']))
            ->leftJoin('projects', 'projects.id', '=', 'time_entries.project_id')
            ->leftJoin('customers', 'customers.id', '=', 'projects.customer_id')
            ->select('time_entries.*')
            ->orderBy('customers.name')
            ->orderBy('projects.name')
            ->orderBy('time_entries.date');
    }

    /**
     * Gegenprobe zur Ausblendung: offene Zeiten, die über einen Leistungssaldo
     * laufen. Erhält die Kontrollfunktion der Liste — die Buchhaltung sieht,
     * dass da etwas ist, statt dass es lautlos verschwindet.
     */
    private function ledgerManagedCount(): int {
        return TimeEntry::query()
            ->where('time_entries.exported', false)
            ->where('time_entries.billable', true)
            ->onlyLedgerManagedCustomers()
            ->count();
    }

    /**
     * @param  Builder<TimeEntry>  $query
     * @return array{count:int, minutes:int, amount:float}
     */
    private function totals(Builder $query): array {
        /** @var object{entry_count:int|string|null, minutes_sum:int|string|null, rate_sum:float|string|null}|null $row */
        $row = $query
            ->reorder()
            ->select(DB::raw('COUNT(*) as entry_count, COALESCE(SUM(time_entries.minutes), 0) as minutes_sum, COALESCE(SUM(time_entries.rate), 0) as rate_sum'))
            ->first();

        return [
            'count' => (int) ($row->entry_count ?? 0),
            'minutes' => (int) ($row->minutes_sum ?? 0),
            'amount' => (float) ($row->rate_sum ?? 0.0),
        ];
    }

    /**
     * Zwischensummen je Kunde/Projekt (volle Treffermenge, nicht nur die Seite).
     *
     * @param  Builder<TimeEntry>  $query
     * @return list<array{customer_name:string|null, project_name:string|null, entry_count:int, minutes_sum:int, rate_sum:float}>
     */
    private function groupTotals(Builder $query): array {
        $rows = [];
        foreach ($query
            ->reorder()
            ->select(DB::raw('customers.name as customer_name, projects.name as project_name, COUNT(*) as entry_count, COALESCE(SUM(time_entries.minutes), 0) as minutes_sum, COALESCE(SUM(time_entries.rate), 0) as rate_sum'))
            ->groupBy('customers.name', 'projects.name')
            ->orderBy('customers.name')
            ->orderBy('projects.name')
            ->get() as $row) {
            $rows[] = [
                'customer_name' => is_string($row->getAttribute('customer_name')) ? $row->getAttribute('customer_name') : null,
                'project_name' => is_string($row->getAttribute('project_name')) ? $row->getAttribute('project_name') : null,
                'entry_count' => (int) $row->getAttribute('entry_count'),
                'minutes_sum' => (int) $row->getAttribute('minutes_sum'),
                'rate_sum' => (float) $row->getAttribute('rate_sum'),
            ];
        }

        return $rows;
    }
}
