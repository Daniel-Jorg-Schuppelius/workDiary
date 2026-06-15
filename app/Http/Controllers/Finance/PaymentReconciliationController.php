<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentReconciliationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{AllocationKind, MatchStatus};
use App\Http\Controllers\Controller;
use App\Models\{Expense, Invoice};
use App\Models\Finance\{BankAccount, BankStatement, BankTransaction, PaymentAllocation};
use App\Services\Finance\{BankImportException, BankImportService, FinancialFormatsSupport, MatchingService, ReconciliationService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Zahlungsabgleich (Feature 045, „Priorität 3 / Phase 4"): Bankauszüge
 * importieren (CAMT.053/MT940), Umsätze prüfen, Zuordnungsvorschläge bestätigen
 * und reversibel zurücknehmen. Statusänderungen an Belegen erst nach
 * Bestätigung (ReconciliationService). Autorisierung über die Bank*-Policies
 * (finance.payment.import / finance.payment.reconcile); Modul-Gating
 * module.finance über die finance.*-Routen.
 */
class PaymentReconciliationController extends Controller {
    private const STORAGE_DISK = 'local';

    public function __construct(
        private readonly BankImportService $importService,
        private readonly MatchingService $matchingService,
        private readonly ReconciliationService $reconciliationService,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', BankStatement::class);

        $statements = BankStatement::query()
            ->with('bankAccount')
            ->withCount([
                'transactions',
                'transactions as open_count' => fn($q) => $q->whereIn('match_status', [MatchStatus::Unmatched->value, MatchStatus::Suggested->value]),
                'transactions as matched_count' => fn($q) => $q->where('match_status', MatchStatus::Matched->value),
            ])
            ->latest()
            ->paginate(25);

        $totals = [
            'open' => BankTransaction::query()->whereIn('match_status', [MatchStatus::Unmatched->value, MatchStatus::Suggested->value])->count(),
            'matched' => BankTransaction::query()->where('match_status', MatchStatus::Matched->value)->count(),
        ];

        $importAvailable = FinancialFormatsSupport::isAvailable();

        return view('finance.reconciliation.index', compact('statements', 'totals', 'importAvailable'));
    }

    public function create(): View {
        Gate::authorize('create', BankStatement::class);

        $accounts = BankAccount::query()->where('is_active', true)->orderBy('label')->get();

        return view('finance.reconciliation._upload_dialog', compact('accounts'));
    }

    public function upload(Request $request): RedirectResponse {
        Gate::authorize('create', BankStatement::class);

        if (! FinancialFormatsSupport::isAvailable()) {
            return back()->with('error', __('bank.import.error.unavailable'));
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'bank_account' => ['nullable', 'string'],
        ]);

        $bankAccount = null;
        if (! empty($validated['bank_account'])) {
            $accountId = Sqid::decode(BankAccount::class, (string) $validated['bank_account']);
            $bankAccount = $accountId !== null ? BankAccount::query()->find($accountId) : null;
        }

        $orgId = $this->currentOrganizationId();

        try {
            $statements = $this->importService->import($request->file('file'), $orgId, $bankAccount);
        } catch (BankImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        $first = $statements[0] ?? null;
        if ($first === null) {
            return back()->with('error', __('bank.import.error.empty'));
        }

        return redirect()
            ->route('finance.reconciliation.show', $first->sqid)
            ->with('success', __('bank.import.flash.imported', ['count' => array_sum(array_map(static fn(BankStatement $s): int => (int) $s->tx_count, $statements))]));
    }

    public function show(BankStatement $statement): View {
        Gate::authorize('view', $statement);

        $statement->load(['bankAccount', 'transactions.allocations']);

        $suggestions = [];
        foreach ($statement->transactions as $transaction) {
            if ($transaction->match_status->isOpen()) {
                $suggestions[$transaction->id] = $this->matchingService->suggestFor($transaction);
            }
        }

        return view('finance.reconciliation.show', [
            'statement' => $statement,
            'suggestions' => $suggestions,
            'kinds' => AllocationKind::cases(),
        ]);
    }

    public function confirm(Request $request, BankTransaction $transaction): RedirectResponse {
        Gate::authorize('reconcile', $transaction);

        $validated = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.type' => ['required', 'string', 'in:invoice,expense'],
            'allocations.*.id' => ['required', 'string'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
            'allocations.*.kind' => ['nullable', 'string'],
            'allocations.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $allocations = [];
        foreach ($validated['allocations'] as $row) {
            $targetClass = $row['type'] === 'invoice' ? Invoice::class : Expense::class;
            $targetId = Sqid::decode($targetClass, (string) $row['id']);
            if ($targetId === null) {
                return back()->with('error', __('bank.reconcile.error.target_not_found'));
            }
            $allocation = [
                'type' => $targetClass,
                'id' => $targetId,
                'amount' => (float) $row['amount'],
                'note' => $row['note'] ?? null,
            ];
            $kind = isset($row['kind']) && $row['kind'] !== '' ? AllocationKind::tryFrom((string) $row['kind']) : null;
            if ($kind !== null) {
                $allocation['kind'] = $kind;
            }
            $allocations[] = $allocation;
        }

        try {
            $this->reconciliationService->confirm($transaction, $allocations);
        } catch (BankImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('bank.reconcile.flash.confirmed'));
    }

    public function ignore(BankTransaction $transaction): RedirectResponse {
        Gate::authorize('reconcile', $transaction);
        $this->reconciliationService->ignore($transaction);

        return back()->with('success', __('bank.reconcile.flash.ignored'));
    }

    public function unassignable(BankTransaction $transaction): RedirectResponse {
        Gate::authorize('reconcile', $transaction);
        $this->reconciliationService->markUnassignable($transaction);

        return back()->with('success', __('bank.reconcile.flash.unassignable'));
    }

    public function unmatch(PaymentAllocation $allocation): RedirectResponse {
        $transaction = $allocation->transaction;
        abort_if($transaction === null, 404);
        Gate::authorize('reconcile', $transaction);

        $this->reconciliationService->unmatch($allocation);

        return back()->with('success', __('bank.reconcile.flash.unmatched'));
    }

    private function currentOrganizationId(): int {
        if (app()->bound('currentOrganization')) {
            /** @var \App\Models\Organization|null $org */
            $org = app('currentOrganization');
            if ($org !== null) {
                return (int) $org->id;
            }
        }
        $user = Auth::user();

        return (int) ($user->organization_id ?? 0);
    }

    public function download(BankStatement $statement): StreamedResponse {
        Gate::authorize('download', $statement);

        $path = (string) $statement->file_path;
        abort_unless($path !== '' && str_starts_with($path, 'imports/bank/'), 404);
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk(self::STORAGE_DISK);
        abort_unless($disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_if($stream === null, 404);

        return response()->streamDownload(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, basename($path));
    }
}
