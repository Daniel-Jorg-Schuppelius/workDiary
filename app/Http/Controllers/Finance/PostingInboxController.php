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
use App\Services\Accounting\{InternalTransferService, JournalService};
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Decimal;
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
        private readonly JournalService $journal,
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

        $actor = $request->user();
        // Vier-Augen: Eigene Entwürfe bieten kein Festschreiben an, es scheiterte sicher.
        $items = $items->map(fn (array $item): array => $item + [
            'awaits_second_person' => ($item['entry'] ?? null) instanceof AccountingEntry && $actor !== null
                && $this->journal->awaitsSecondPerson($item['entry'], $actor),
        ]);

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
            // Quellen mit Kontext (AfA: Anlage × Jahr) sind nur über den
            // Idempotenzschlüssel eindeutig — die ID allein reicht dort nicht.
            'source_key' => ['nullable', 'string', 'max:96'],
            'post' => ['nullable', 'boolean'],
        ]);

        $kind = PostingSourceKind::from((string) $data['kind']);
        $adapter = $this->registry->for($kind);
        $sourceKey = (string) ($data['source_key'] ?? '');

        [$from, $to] = $this->globalDateRangeBounds();
        $source = $adapter
            ->candidates($organization, \Carbon\CarbonImmutable::parse($from), \Carbon\CarbonImmutable::parse($to))
            ->first(fn ($candidate): bool => $sourceKey !== ''
                ? $adapter->sourceKey($candidate) === $sourceKey
                : (int) $candidate->getKey() === (int) $data['source_id']);
        abort_if($source === null, 404);

        $entry = $this->inbox->prepare($organization, $adapter->proposalFor($organization, $source), $actor);

        if ($request->boolean('post')) {
            abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
            $this->journal->post($entry, $actor);
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
            // Sqid aus dem Dialog (numerisch nur für Altaufrufer) — `integer` wies jede Auswahl ab.
            'clearing_account' => ['required', 'string', 'max:64'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
            'follow_up_on' => ['required', 'date'],
        ]);

        $clearing = AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['clearing_account']))
            ->firstOrFail();

        $entry = $this->inbox->postBankTransactionToClearing(
            $organization,
            $transaction,
            $clearing,
            (string) $data['note'],
            CarbonImmutable::parse((string) $data['follow_up_on']),
            $actor,
        );

        return back()->with('status', $entry->status->isPosted()
            ? __('accounting.clearing.flash.posted')
            : __('accounting.inbox.flash.awaiting_approval'));
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

    /** Interne Umbuchung festschreiben (bei Vier-Augen: Entwurf zur Freigabe). */
    public function storeTransfer(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'from_account' => ['required', 'string', 'max:64'],
            'to_account' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'decimal:0,8', 'gt:0'],
            'booked_on' => ['required', 'date'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $accounts = AccountingAccount::query()->where('organization_id', $organization->id);

        $transfer = $this->transfers->record($organization, [
            'booked_on' => CarbonImmutable::parse((string) $data['booked_on']),
            'amount' => Decimal::of((string) $data['amount'], 2)->getValue(),
            'from_account' => (clone $accounts)->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['from_account']))->firstOrFail(),
            'to_account' => (clone $accounts)->whereKey(Sqid::decodeOrNumeric(AccountingAccount::class, (string) $data['to_account']))->firstOrFail(),
            'note' => (string) $data['note'],
        ], $actor);

        return back()->with('status', $transfer->entry?->status->isPosted() === false
            ? __('accounting.inbox.flash.awaiting_approval')
            : __('accounting.transfer.flash.recorded'));
    }

    /** Vorbereiteten Entwurf festschreiben (mit Vier-Augen-Prüfung). */
    public function post(Request $request, AccountingEntry $entry): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $entry->organization_id === (int) $organization->id, 404);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->journal->post($entry, $actor);

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
