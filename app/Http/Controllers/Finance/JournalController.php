<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JournalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\AccountingEntryStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingEntry};
use App\Services\Accounting\JournalService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Buchungsjournal (Feature 125, MVP-672): Liste, Erfassung, Festschreibung
 * und Storno. Schreibende Wege laufen ausschließlich über den
 * {@see JournalService} — der Controller entscheidet nichts fachlich.
 */
class JournalController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(private readonly JournalService $journal) {}

    public function index(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        [$from, $to] = $this->globalDateRangeBounds();

        $query = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->with(['lines.account', 'postedBy'])
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString());

        $status = AccountingEntryStatus::tryFrom((string) $request->query('status', ''));
        if ($status instanceof AccountingEntryStatus) {
            $query->where('status', $status->value);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->whereLikeEscaped('memo', $search)->orWhereLikeEscaped('document_reference', $search);
            });
        }

        return view('finance.accounting.journal', [
            'entries' => $query->orderByDesc('booked_on')->orderByDesc('journal_no')->paginate(50)->withQueryString(),
            'statuses' => AccountingEntryStatus::cases(),
            'selectedStatus' => $status,
            'search' => $search,
            'canPrepare' => Gate::allows(Permission::AccountingLedgerPrepare->value),
            'canPost' => Gate::allows(Permission::AccountingLedgerPost->value),
        ]);
    }

    public function show(AccountingEntry $entry): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $this->assertSameOrganization($entry);

        return view('finance.accounting.entry', [
            'entry' => $entry->load(['lines.account', 'lines.taxCode', 'postedBy', 'reversedBy', 'reverses']),
            'canPost' => Gate::allows(Permission::AccountingLedgerPost->value),
        ]);
    }

    /** Erfassungsdialog: zwei Zeilen (Soll/Haben) reichen für die Handbuchung. */
    public function form(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting._entry_dialog', [
            'accounts' => AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->active()
                ->orderBy('number')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'booked_on' => ['required', 'date'],
            'document_on' => ['nullable', 'date'],
            'memo' => ['required', 'string', 'max:191'],
            'document_reference' => ['nullable', 'string', 'max:64'],
            'debit_account' => ['required', 'string'],
            'credit_account' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'post' => ['nullable', 'boolean'],
        ]);

        $debitId = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['debit_account']);
        $creditId = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['credit_account']);
        $this->assertOwnAccounts($organization->id, [$debitId, $creditId]);

        $amount = number_format((float) $data['amount'], 2, '.', '');
        $entry = $this->journal->draft($organization, [
            'booked_on' => CarbonImmutable::parse((string) $data['booked_on']),
            'document_on' => isset($data['document_on']) ? CarbonImmutable::parse((string) $data['document_on']) : null,
            'memo' => (string) $data['memo'],
            'document_reference' => $data['document_reference'] ?? null,
            'lines' => [
                ['accounting_account_id' => $debitId, 'debit' => $amount, 'credit' => '0.00'],
                ['accounting_account_id' => $creditId, 'debit' => '0.00', 'credit' => $amount],
            ],
        ], $actor);

        if ($request->boolean('post')) {
            abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
            $entry = $this->journal->post($entry, $actor);
        }

        return redirect()
            ->route('finance.accounting.journal.show', $entry)
            ->with('status', __('accounting.ledger.flash.entry_saved'));
    }

    public function post(Request $request, AccountingEntry $entry): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($entry);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->journal->post($entry, $actor);

        return back()->with('status', __('accounting.ledger.flash.entry_posted'));
    }

    public function reverseForm(AccountingEntry $entry): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($entry);

        return view('finance.accounting._reverse_dialog', ['entry' => $entry]);
    }

    public function reverse(Request $request, AccountingEntry $entry): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $this->assertSameOrganization($entry);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'reversal_reason' => ['required', 'string', 'max:500'],
            'booked_on' => ['nullable', 'date'],
        ]);

        $reversal = $this->journal->reverse(
            $entry,
            (string) $data['reversal_reason'],
            $actor,
            isset($data['booked_on']) ? CarbonImmutable::parse((string) $data['booked_on']) : null,
        );

        return redirect()
            ->route('finance.accounting.journal.show', $reversal)
            ->with('status', __('accounting.ledger.flash.entry_reversed'));
    }

    private function assertSameOrganization(AccountingEntry $entry): void {
        abort_unless((int) $entry->organization_id === (int) $this->currentOrganizationOrAbort()->id, 404);
    }

    /** @param list<int> $ids */
    private function assertOwnAccounts(int $organizationId, array $ids): void {
        $count = AccountingAccount::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->count();

        abort_unless($count === count(array_unique($ids)), 422);
    }
}
