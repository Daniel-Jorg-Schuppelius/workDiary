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

use App\Enums\Finance\{AccountingEntryStatus, DirectBookingKind};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingEntry;
use App\Models\Finance\CostCenter;
use App\Models\Platform\Organization;
use App\Services\Accounting\{JournalService, ManualEntryLines};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;

/**
 * Buchungsjournal (Feature 125, MVP-672): Liste, Erfassung, Festschreibung
 * und Storno. Schreibende Wege laufen ausschließlich über den
 * {@see JournalService} — der Controller entscheidet nichts fachlich.
 */
class JournalController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly JournalService $journal,
        private readonly ManualEntryLines $manualLines,
    ) {}

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

    public function show(Request $request, AccountingEntry $entry): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $this->assertSameOrganization($entry);
        $actor = $request->user();

        return view('finance.accounting.entry', [
            'entry' => $entry->load(['lines.account', 'lines.taxCode', 'lines.costCenter', 'lines.counterparty', 'postedBy', 'reversedBy', 'reverses']),
            'canPost' => Gate::allows(Permission::AccountingLedgerPost->value),
            'canPrepare' => Gate::allows(Permission::AccountingLedgerPrepare->value),
            'awaitsSecondPerson' => $actor !== null && $this->journal->awaitsSecondPerson($entry, $actor),
            // Wartende Direktbuchung (verwerfbar) bzw. wartendes Storno (E23/E24).
            'directKind' => $entry->status->isMutable() ? DirectBookingKind::of($entry) : null,
            'pendingReversal' => $entry->status === AccountingEntryStatus::Posted ? $this->journal->pendingReversalOf($entry) : null,
            // Hinweis auf überschrittene freigegebene Monatsbudgets (MVP-983), keine Sperre.
            'budgetOverruns' => app(\App\Services\Accounting\AccountingBudgetService::class)->overrunsFor($entry),
        ]);
    }

    /**
     * Erfassungsdialog der Handbuchung; mit `$entry` das Bearbeiten eines
     * Entwurfs (MVP-1556/1558): mehrere Zeilen, Steuerkennzeichen und
     * Kostenstelle je Zeile, Gegenpartei für OPOS- und Steuerzeilen.
     */
    public function form(?AccountingEntry $entry = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        if ($entry instanceof AccountingEntry) {
            $this->assertSameOrganization($entry);
            abort_unless($entry->status->isMutable() && DirectBookingKind::of($entry) === null, 404);
        }

        $lines = $entry instanceof AccountingEntry ? $this->manualLines->lineData($entry->load('lines')) : [];
        $rows = $this->manualLines->rows($lines);

        return view('finance.accounting._entry_dialog', [
            'entry' => $entry,
            'rows' => $rows,
            'rowCount' => $this->manualLines->rowCount($rows),
            'counterparty' => $this->manualLines->counterpartyValue($lines),
            'canPost' => Gate::allows(Permission::AccountingLedgerPost->value),
        ] + $this->manualLines->formOptions($organization, $entry?->booked_on !== null ? CarbonImmutable::parse($entry->booked_on) : CarbonImmutable::now()));
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $this->validatedEntry($request);
        $bookedOn = CarbonImmutable::parse((string) $data['booked_on']);
        $draft = $this->head($data) + [
            'lines' => $this->manualLines->build($organization, $bookedOn, $data['rows'], (string) ($data['counterparty'] ?? '')),
        ];

        // Recht vor dem Schreiben prüfen und atomar festschreiben — sonst
        // blieb bei 403 bzw. gesperrter Buchungshoheit ein Entwurf zurück.
        if ($request->boolean('post')) {
            abort_unless(Gate::allows(Permission::AccountingLedgerPost->value), 403);
            $entry = $this->journal->postDirect($organization, $draft, $actor);
        } else {
            $entry = $this->journal->draft($organization, $draft, $actor);
        }

        return redirect()
            ->route('finance.accounting.journal.show', $entry)
            ->with('status', __('accounting.ledger.flash.entry_saved'));
    }

    /** Entwurf ändern (MVP-1556); Festbuchungen und Fachvorgänge weist der Dienst ab. */
    public function update(Request $request, AccountingEntry $entry): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $this->assertSameOrganization($entry);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $this->validatedEntry($request);
        $bookedOn = CarbonImmutable::parse((string) $data['booked_on']);
        $lines = $this->manualLines->build(
            $organization,
            $bookedOn,
            $data['rows'],
            (string) ($data['counterparty'] ?? ''),
            $this->manualLines->lineData($entry->load('lines')),
        );

        $this->journal->updateDraft($entry, $this->head($data), $lines, $actor);

        return redirect()
            ->route('finance.accounting.journal.show', $entry)
            ->with('status', __('accounting.ledger.flash.entry_updated'));
    }

    /** Dialog: Kostenstelle je Zeile nachtragen (Feature 142, MVP-1556) — an jedem Entwurf, auch aus Inbox und Fachvorgang. */
    public function costCenterForm(AccountingEntry $entry): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $this->assertSameOrganization($entry);
        abort_unless($entry->status->isMutable(), 404);

        return view('finance.accounting._entry_cost_center_dialog', [
            'entry' => $entry->load('lines.account'),
            'costCenters' => $this->costCenters($this->currentOrganizationOrAbort()),
        ]);
    }

    public function assignCostCenters(Request $request, AccountingEntry $entry): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
        $this->assertSameOrganization($entry);

        $data = $request->validate([
            'cost_centers' => ['required', 'array'],
            'cost_centers.*' => ['nullable', 'string', 'max:64'],
        ]);

        DB::transaction(function () use ($entry, $data): void {
            foreach ($entry->lines as $line) {
                if (! array_key_exists($line->sqid, $data['cost_centers'])) {
                    continue;
                }
                $key = (string) ($data['cost_centers'][$line->sqid] ?? '');
                // Die Zugehörigkeit zur Organisation prüft der JournalService.
                $this->journal->assignCostCenter($line, $key === '' ? null : Sqid::decodeOrNumeric(CostCenter::class, $key));
            }
        });

        return redirect()
            ->route('finance.accounting.journal.show', $entry)
            ->with('status', __('accounting.ledger.flash.cost_centers_saved'));
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
            ->with('status', $reversal->status->isPosted()
                ? __('accounting.ledger.flash.entry_reversed')
                : __('accounting.ledger.flash.reversal_awaiting_approval'));
    }

    /**
     * Entwurf verwerfen (E24, MVP-1556). Zurück zur Liste, auf der der Vorgang
     * wieder offen steht — die Detailseite gibt es danach nicht mehr.
     * Fachvorgänge verwirft, wer festschreiben darf; alle anderen Entwürfe,
     * wer sie vorbereiten darf.
     */
    public function discard(Request $request, AccountingEntry $entry): RedirectResponse {
        $kind = DirectBookingKind::of($entry);
        abort_unless(Gate::allows(($kind instanceof DirectBookingKind ? Permission::AccountingLedgerPost : Permission::AccountingLedgerPrepare)->value), 403);
        $this->assertSameOrganization($entry);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $fromInbox = $entry->source_key !== null && $entry->source_key !== '';
        $this->journal->discard($entry, $actor);

        return redirect()
            ->toList(match (true) {
                $kind === DirectBookingKind::OpenItemSettlement => 'finance.accounting.open-items.index',
                $kind instanceof DirectBookingKind, $fromInbox => 'finance.accounting.inbox.index',
                default => 'finance.accounting.journal.index',
            })
            ->with('status', __('accounting.ledger.flash.entry_discarded'));
    }

    /** @return array<string, mixed> */
    private function validatedEntry(Request $request): array {
        return $request->validate([
            'booked_on' => ['required', 'date'],
            'document_on' => ['nullable', 'date'],
            'memo' => ['required', 'string', 'max:191'],
            'document_reference' => ['nullable', 'string', 'max:64'],
            'counterparty' => ['nullable', 'string', 'max:80'],
            'rows' => ['required', 'array', 'min:2', 'max:60'],
            'rows.*.line' => ['nullable', 'integer', 'min:0'],
            'rows.*.account' => ['nullable', 'string', 'max:64'],
            // decimal statt nur numeric: keine Exponentenschreibweise (bcmath).
            'rows.*.debit' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'rows.*.credit' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0'],
            'rows.*.tax_code' => ['nullable', 'string', 'max:64'],
            'rows.*.cost_center' => ['nullable', 'string', 'max:64'],
            'rows.*.memo' => ['nullable', 'string', 'max:191'],
            'post' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{booked_on: CarbonImmutable, document_on: CarbonImmutable|null, memo: string, document_reference: string|null}
     */
    private function head(array $data): array {
        return [
            'booked_on' => CarbonImmutable::parse((string) $data['booked_on']),
            'document_on' => isset($data['document_on']) ? CarbonImmutable::parse((string) $data['document_on']) : null,
            'memo' => (string) $data['memo'],
            'document_reference' => $data['document_reference'] ?? null,
        ];
    }

    /** @return Collection<int, CostCenter> */
    private function costCenters(Organization $organization): Collection {
        return CostCenter::query()->where('organization_id', $organization->id)->where('active', true)->orderBy('code')->get();
    }
}
