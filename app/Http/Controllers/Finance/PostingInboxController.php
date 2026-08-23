<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\PostingSourceKind;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingEntry};
use App\Models\Finance\BankTransaction;
use App\Services\Accounting\InternalTransferService;
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Buchungs-Inbox (Feature 125, MVP-673): ungebucht, blockiert, bereit.
 *
 * Der Controller erzeugt keine Buchungen — er reicht Vorschläge an den
 * {@see PostingInboxService} weiter, der über den JournalService schreibt.
 */
class PostingInboxController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly PostingInboxService $inbox,
        private readonly PostingSourceRegistry $registry,
        private readonly InternalTransferService $transfers,
    ) {}

    public function index(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        [$from, $to] = $this->globalDateRangeBounds();

        $kind = PostingSourceKind::tryFrom((string) $request->query('kind', ''));
        $items = $this->inbox->items(
            $organization,
            \Carbon\CarbonImmutable::parse($from),
            \Carbon\CarbonImmutable::parse($to),
            $kind,
            $request->boolean('include_posted'),
        );

        return view('finance.accounting.inbox', [
            'items' => $items,
            'kinds' => PostingSourceKind::cases(),
            'selectedKind' => $kind,
            'includePosted' => $request->boolean('include_posted'),
            'canPrepare' => Gate::allows(Permission::AccountingLedgerPrepare->value),
            'canPost' => Gate::allows(Permission::AccountingLedgerPost->value),
            'fourEyes' => $this->inbox->fourEyesEnabled(),
            'counts' => $items->groupBy('state')->map->count(),
        ]);
    }

    /** Einen Vorschlag als geprüften Entwurf anlegen. */
    public function prepare(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:' . implode(',', array_column(PostingSourceKind::cases(), 'value'))],
            'source_id' => ['required', 'integer'],
            'post' => ['nullable', 'boolean'],
        ]);

        $kind = PostingSourceKind::from((string) $data['kind']);
        $adapter = $this->registry->for($kind);

        [$from, $to] = $this->globalDateRangeBounds();
        $source = $adapter
            ->candidates($organization, \Carbon\CarbonImmutable::parse($from), \Carbon\CarbonImmutable::parse($to))
            ->first(fn ($candidate): bool => (int) $candidate->getKey() === (int) $data['source_id']);
        abort_if($source === null, 404);

        $entry = $this->inbox->prepare($organization, $adapter->proposalFor($organization, $source), $actor);

        if ($request->boolean('post')) {
            abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
            $this->inbox->post($entry, $actor);
        }

        return back()->with('status', __('accounting.inbox.flash.prepared'));
    }

    /** Dialog: Bankumsatz bewusst auf ein Klärungskonto buchen (MVP-681). */
    public function clearingForm(BankTransaction $transaction): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $transaction->organization_id === (int) $organization->id, 404);

        return view('finance.accounting._clearing_dialog', [
            'transaction' => $transaction,
            'accounts' => AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->where('is_clearing', true)
                ->active()
                ->orderBy('number')
                ->get(),
        ]);
    }

    /**
     * Klärungsbuchung anlegen. Notiz und Wiedervorlage sind Pflicht — ein
     * Klärungskonto ohne beides ist ein Auffangbecken.
     */
    public function storeClearing(Request $request, BankTransaction $transaction): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $transaction->organization_id === (int) $organization->id, 404);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'clearing_account' => ['required', 'integer'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
            'follow_up_on' => ['required', 'date'],
        ]);

        $clearing = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['clearing_account']))
            ->firstOrFail();

        $this->inbox->postBankTransactionToClearing(
            $organization,
            $transaction,
            $clearing,
            (string) $data['note'],
            CarbonImmutable::parse((string) $data['follow_up_on']),
            $actor,
        );

        return back()->with('status', __('accounting.clearing.flash.posted'));
    }

    /** Dialog: interne Umbuchung zwischen Geldkonten (MVP-681). */
    public function transferForm(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting._transfer_dialog', [
            'accounts' => AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->where(function ($query): void {
                    $query->where('is_bank', true)->orWhere('is_cash', true)->orWhere('is_clearing', true);
                })
                ->active()
                ->orderBy('number')
                ->get(),
        ]);
    }

    /** Interne Umbuchung festschreiben. */
    public function storeTransfer(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'from_account' => ['required', 'integer'],
            'to_account' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'booked_on' => ['required', 'date'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $accounts = AccountingAccount::query()->where('organization_id', $organization->id);

        $this->transfers->record($organization, [
            'booked_on' => CarbonImmutable::parse((string) $data['booked_on']),
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'from_account' => (clone $accounts)->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['from_account']))->firstOrFail(),
            'to_account' => (clone $accounts)->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['to_account']))->firstOrFail(),
            'note' => (string) $data['note'],
        ], $actor);

        return back()->with('status', __('accounting.transfer.flash.recorded'));
    }

    /** Vorbereiteten Entwurf festschreiben (mit Vier-Augen-Prüfung). */
    public function post(Request $request, AccountingEntry $entry): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $entry->organization_id === (int) $organization->id, 404);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->inbox->post($entry, $actor);

        return back()->with('status', __('accounting.ledger.flash.entry_posted'));
    }

    /**
     * Stapel: alle nicht blockierten Vorgänge des Zeitraums vorbereiten und
     * auf Wunsch festschreiben. Blocker stoppen nur ihren eigenen Vorgang.
     */
    public function batch(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $post = $request->boolean('post');
        if ($post) {
            abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        }

        [$from, $to] = $this->globalDateRangeBounds();
        $kind = PostingSourceKind::tryFrom((string) $request->input('kind', ''));

        $batch = [];
        foreach ($this->inbox->items($organization, \Carbon\CarbonImmutable::parse($from), \Carbon\CarbonImmutable::parse($to), $kind) as $item) {
            if ($item['state'] !== 'open' && $item['state'] !== 'ready') {
                continue;
            }

            $batch[] = ['proposal' => $item['proposal'], 'entry' => $item['entry']];
        }

        $result = $this->inbox->processBatch($organization, $batch, $actor, $post);

        return back()->with('status', __('accounting.inbox.flash.batch', [
            'prepared' => $result['prepared'],
            'posted' => $result['posted'],
            'failed' => count($result['failed']),
        ]));
    }
}
