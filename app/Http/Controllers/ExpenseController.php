<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Expense\{ExpenseStatus, PaymentMethod};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SaveExpenseRequest;
use App\Models\{Customer, Expense, ExpenseCategory, Project, User};
use App\Services\Billing\ExpenseLinkProviderResolver;
use App\Services\Expense\ExpenseService;
use App\Support\{CsvExport, SortableQuery};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly ExpenseService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Expense::class);

        [$from, $to] = $this->resolveRange($request);

        $statusFilter = $request->string('status')->toString();
        $statusEnum = $statusFilter !== '' ? ExpenseStatus::tryFrom($statusFilter) : null;

        $query = Expense::query()
            ->with(['category:id,label,color,icon', 'project:id,name', 'customer:id,name'])
            ->withCount('attachments')
            ->where('user_id', Auth::id())
            ->whereBetween('date', DateRange::days($from, $to));

        if ($statusEnum !== null) {
            $query->where('status', $statusEnum->value);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'date' => 'date',
            'vendor' => 'vendor',
            'description' => 'description',
            'amount' => 'amount_gross',
            'status' => 'status',
        ], 'date', 'desc');

        $expenses = $query->paginate(25)->withQueryString();

        $totalsQuery = Expense::query()
            ->where('user_id', Auth::id())
            ->whereBetween('date', DateRange::days($from, $to));

        $totals = [
            'gross' => (float) (clone $totalsQuery)->sum('amount_gross'),
            'reimbursable' => (float) (clone $totalsQuery)
                ->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Reimbursed->value, ExpenseStatus::Invoiced->value])
                ->where('payment_method', PaymentMethod::PrivatePaid->value)
                ->sum('amount_gross'),
            'pending' => (int) (clone $totalsQuery)->where('status', ExpenseStatus::Pending->value)->count(),
            'reimbursement_pending' => (float) Expense::query()
                ->where('user_id', Auth::id())
                ->where('status', ExpenseStatus::Approved->value)
                ->where('payment_method', PaymentMethod::PrivatePaid->value)
                ->whereNull('reimbursed_at')
                ->sum('amount_gross'),
            'current_month' => (float) Expense::query()
                ->where('user_id', Auth::id())
                ->whereBetween('date', [
                    CarbonImmutable::today()->startOfMonth()->toDateString(),
                    CarbonImmutable::today()->endOfMonth()->toDateString(),
                ])
                ->sum('amount_gross'),
        ];

        return view('expenses.index', [
            'expenses' => $expenses,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'sort' => $sort,
            'dir' => $dir,
            'statusFilter' => $statusFilter,
            'statusOptions' => ExpenseStatus::cases(),
        ]);
    }

    /**
     * Scan-Beleg → Auslagen-Vorschlag (Feature 088 P3, MVP-669): erzeugt aus
     * einem PDF-Scan eine Entwurfs-Auslage mit extrahierten Werten und dem
     * Beleg als Anhang — der Mensch bestätigt im Formular, nie Auto-Buchung.
     */
    public function scan(Request $request, \App\Services\Expense\ExpenseScanService $scanner): RedirectResponse {
        Gate::authorize('create', Expense::class);

        $request->validate([
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,tif,tiff', 'max:20480'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('receipt');
        /** @var \App\Models\User $actor */
        $actor = Auth::user();
        /** @var \App\Models\Organization $organization */
        $organization = app('currentOrganization');

        $result = $scanner->createDraftFromScan($file, $actor, $organization);

        return redirect()->route('expenses.edit', $result['expense'])
            ->with('success', __('Beleg gelesen — bitte Werte prüfen und speichern. Kategorie und Händler ergänzt der Mensch, nicht die Maschine.'));
    }

    public function create(Request $request): View {
        Gate::authorize('create', Expense::class);

        return view('expenses._form_dialog', [
            'expense' => null,
            'date' => $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString(),
        ] + $this->formData());
    }

    public function store(SaveExpenseRequest $request): RedirectResponse {
        Gate::authorize('create', Expense::class);

        $data = $request->validated();
        $data['user_id'] = Auth::id();
        /** @var User $user */
        $user = Auth::user();
        $data['organization_id'] = $user->organization_id;

        $expense = $this->service->create($data);

        if ($request->boolean('submit_after_save')) {
            $this->service->submitForApproval($expense);

            return redirect()->route('expenses.index')
                ->with('success', __('Spese erfasst und zur Genehmigung eingereicht.'));
        }

        return redirect()->route('expenses.index')
            ->with('success', __('Spese als Entwurf gespeichert.'));
    }

    public function edit(Expense $expense): View {
        Gate::authorize('update', $expense);

        return view('expenses._form_dialog', [
            'expense' => $expense,
            'date' => $expense->date->toDateString(),
        ] + $this->formData());
    }

    /**
     * Belegdatei zur Auslage (Feature 105, MVP-550). Erst mit hinterlegtem
     * Beleg ist die Auslage für sich prüfbar — und erst dann lässt sie sich
     * später überhaupt in die Buchhaltung übernehmen (Feature 106).
     */
    public function receipt(Expense $expense, ExpenseLinkProviderResolver $providers): View {
        Gate::authorize('view', $expense);

        $provider = $providers->current();
        $linked = $provider->voucherFor($expense);

        return view('expenses._receipt_dialog', [
            'expense' => $expense,
            'attachments' => $expense->attachments()->get(),
            'canUpload' => Gate::allows('update', $expense),
            'canLink' => Gate::allows('link', $expense),
            'linkedVoucher' => $linked,
            // Ohne angebundene Buchhaltung (NullExpenseLinkProvider) sagt der
            // Dialog das klar, statt nur „keine Vorschläge" zu zeigen (B9).
            'hasProvider' => $provider->isAvailable(),
            'providerLabel' => $provider->label(),
            // Feature 106: aktiver Belegpush - nur anbieten, wo er möglich ist.
            'canPush' => Gate::allows('link', $expense) && $provider->canPush($expense),
            'wasPushed' => $linked !== null && $provider->wasPushed($expense),
            // Vorschläge nur, solange nichts zugeordnet ist — sonst lädt der
            // Dialog Kandidaten, die niemand mehr braucht.
            'suggestions' => $linked === null ? $provider->suggestionsFor($expense) : collect(),
        ]);
    }

    /**
     * Bestätigt die Zuordnung zu einem Buchhaltungsbeleg (Feature 105,
     * MVP-551). Ab dann führt der Beleg: die Auslage zählt nicht mehr
     * eigenständig in den Aufwand.
     */
    public function linkVoucher(Request $request, Expense $expense, ExpenseLinkProviderResolver $providers): RedirectResponse {
        Gate::authorize('link', $expense);

        try {
            $voucher = $providers->current()->link($expense, (string) $request->input('voucher'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $expense->audit('expense.voucher_linked', ['voucher_number' => $voucher->number]);

        return back()->with('success', __('expenses.receipt.linked', ['number' => $voucher->number ?? '—']));
    }

    public function unlinkVoucher(Expense $expense, ExpenseLinkProviderResolver $providers): RedirectResponse {
        Gate::authorize('link', $expense);

        try {
            $providers->current()->unlink($expense);
        } catch (\RuntimeException $e) {
            // Gepushte Verknüpfung (Feature 106): der Beleg existiert
            // unwiderruflich - die Verknüpfung bleibt.
            return back()->with('error', $e->getMessage());
        }
        $expense->audit('expense.voucher_unlinked', []);

        return back()->with('success', __('expenses.receipt.unlinked'));
    }

    /**
     * Aktiver Belegpush in die Buchhaltung (Feature 106): legt die genehmigte
     * Auslage als Einkaufsbeleg im führenden System an — die Dublette kann
     * gar nicht erst entstehen, die externe ID kommt beim Anlegen zurück.
     *
     * Der Push ist terminal (kein Update/Delete im Zielsystem); Korrekturen
     * laufen als Gegenbeleg dort, die Auslage bleibt verknüpft und gesperrt.
     */
    public function pushVoucher(Expense $expense, ExpenseLinkProviderResolver $providers): RedirectResponse {
        Gate::authorize('link', $expense);

        try {
            $voucher = $providers->current()->pushVoucher($expense);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Die Übergabe an die Buchhaltung ist fehlgeschlagen — es wurde kein Beleg angelegt.'));
        }

        $expense->audit('expense.voucher_pushed', ['external_id' => $voucher->externalId]);

        return back()->with('success', __('Auslage als Beleg übergeben (ID :id). Ab jetzt führt der Beleg.', ['id' => $voucher->externalId]));
    }

    public function update(SaveExpenseRequest $request, Expense $expense): RedirectResponse {
        Gate::authorize('update', $expense);

        $this->service->update($expense, $request->validated());

        if ($request->boolean('submit_after_save') && $expense->fresh()?->status === ExpenseStatus::Draft) {
            $this->service->submitForApproval($expense);

            return redirect()->route('expenses.index')
                ->with('success', __('Spese gespeichert und zur Genehmigung eingereicht.'));
        }

        return redirect()->route('expenses.index')
            ->with('success', __('Spese aktualisiert.'));
    }

    public function destroy(Expense $expense): RedirectResponse {
        Gate::authorize('delete', $expense);

        $this->service->delete($expense);

        return redirect()->route('expenses.index')
            ->with('success', __('Spese gelöscht.'));
    }

    public function submit(Expense $expense): RedirectResponse {
        Gate::authorize('submit', $expense);

        $this->service->submitForApproval($expense);

        return redirect()->route('expenses.index')
            ->with('success', __('Spese zur Genehmigung eingereicht.'));
    }

    public function cancel(Expense $expense): RedirectResponse {
        Gate::authorize('cancel', $expense);

        $this->service->cancel($expense);

        return redirect()->route('expenses.index')
            ->with('success', __('Spese storniert.'));
    }

    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', Expense::class);

        [$from, $to] = $this->resolveRange($request);

        $filename = sprintf('expenses-%s_%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d'));

        $expenses = Expense::query()
            ->with([
                'user:id,name',
                'category:id,label',
                'project:id,name,customer_id',
                'customer:id,name',
            ])
            ->where('user_id', Auth::id())
            ->whereBetween('date', DateRange::days($from, $to))
            ->orderBy('date')
            ->get();

        $rows = (static function () use ($expenses): \Generator {
            foreach ($expenses as $expense) {
                yield [
                    $expense->date->format('Y-m-d'),
                    $expense->user->name ?? '',
                    $expense->category->label ?? '',
                    (string) $expense->vendor,
                    $expense->project->name ?? '',
                    $expense->customer->name ?? '',
                    number_format(($expense->amount_gross?->toFloat() ?? 0.0), 2, ',', ''),
                    number_format(($expense->amount_net?->toFloat() ?? 0.0), 2, ',', ''),
                    number_format(($expense->tax_amount?->toFloat() ?? 0.0), 2, ',', ''),
                    number_format(($expense->tax_rate !== null ? (float) $expense->tax_rate->getNumericValue() : 0.0), 2, ',', ''),
                    $expense->currency->value,
                    $expense->payment_method->label(),
                    $expense->status->label(),
                    $expense->billable ? 'ja' : 'nein',
                    $expense->decided_at?->format('Y-m-d'),
                    $expense->reimbursed_at?->format('Y-m-d'),
                    (string) $expense->reimbursement_reference,
                    (string) $expense->description,
                ];
            }
        })();

        return CsvExport::streamFromRows($filename, [
            'Datum',
            'Mitarbeiter',
            'Kategorie',
            'Händler',
            'Projekt',
            'Kunde',
            'Brutto',
            'Netto',
            'Steuer',
            'Steuersatz %',
            'Währung',
            'Zahlungsweise',
            'Status',
            'Abrechenbar',
            'Genehmigt am',
            'Erstattet am',
            'Erstattungsreferenz',
            'Beschreibung',
        ], $rows);
    }

    /** @return array<string, mixed> */
    private function formData(): array {
        return [
            'categories' => ExpenseCategory::query()
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderBy('label')
                ->get(['id', 'label', 'default_tax_rate', 'default_billable', 'icon', 'color']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::allowed(),
        ];
    }
}
