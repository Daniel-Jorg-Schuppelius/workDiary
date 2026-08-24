<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingInboxService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\{AccountingEntryStatus, PostingAccountRole, PostingSourceKind};
use App\Models\Accounting\{AccountingAccount, AccountingEntry};
use App\Models\Finance\BankTransaction;
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingSovereigntyResolver, InternalTransferService, JournalService};
use App\Support\Setting;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Str};
use Illuminate\Validation\ValidationException;

/**
 * Buchungs-Inbox (Feature 125, MVP-673): eine Liste über alle Quellen statt
 * eines Formularzwangs je Beleg.
 *
 * Die Inbox führt keinen eigenen Bestand — sie verbindet die vorhandenen
 * Fachobjekte mit ihrem Buchungsstatus. Eine fünfte Belegtabelle hätte nur
 * einen weiteren Ort geschaffen, an dem etwas veralten kann.
 *
 * Grundsatz „Vorschlag vor Festbuchung": Auch ein eindeutiger Adapter erzeugt
 * `ready`, nie `posted`. Ein Auto-Post-Modus wäre erst nach belastbaren
 * Pilotdaten und eigenem Freigabeentscheid zulässig.
 */
class PostingInboxService {
    /** Org-Einstellung: Vorbereiter darf nicht selbst festschreiben. */
    public const FOUR_EYES_KEY = 'finance.accounting_four_eyes';

    public function __construct(
        private readonly PostingSourceRegistry $registry,
        private readonly JournalService $journal,
        private readonly AccountingSovereigntyResolver $sovereignty,
        private readonly InternalTransferService $transfers,
        private readonly PostingRuleResolver $rules,
    ) {}

    /**
     * Offene und vorbereitete Vorgänge des Zeitraums.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function items(
        Organization $organization,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?PostingSourceKind $kind = null,
        bool $includePosted = false,
    ): Collection {
        $items = collect();

        foreach ($this->registry->all() as $adapter) {
            if ($kind instanceof PostingSourceKind && $adapter->kind() !== $kind) {
                continue;
            }

            foreach ($adapter->candidates($organization, $from, $to) as $source) {
                $sourceKey = $adapter->sourceKey($source);
                $entry = $this->journal->activeEntryForSource($organization, $sourceKey);

                if ($entry?->status->isPosted() === true && ! $includePosted) {
                    continue;
                }

                $proposal = $entry === null
                    ? $adapter->proposalFor($organization, $source)
                    : null;

                $items->push([
                    'kind' => $adapter->kind(),
                    'source' => $source,
                    'source_key' => $sourceKey,
                    'entry' => $entry,
                    'proposal' => $proposal,
                    'blockers' => $proposal instanceof PostingProposal ? $proposal->blockers : $this->sovereigntyBlockers($organization, $entry),
                    'state' => $this->stateOf($entry, $proposal),
                ]);
            }
        }

        return $items->sortBy(fn (array $item): string => $this->sortKey($item))->values();
    }

    /**
     * Bewusste Klärungsbuchung für einen Bankumsatz (Feature 125, MVP-681).
     *
     * Ein Umsatz, den niemand zuordnen kann, darf auf ein Klärungskonto —
     * aber nur mit Notiz und Wiedervorlage und nur auf ausdrückliche Auswahl.
     * Automatisch wäre das Klärungskonto ein Auffangbecken, und der
     * Jahresabschluss fände dort alles wieder, was niemand angesehen hat.
     *
     * @throws ValidationException
     */
    public function postBankTransactionToClearing(
        Organization $organization,
        BankTransaction $transaction,
        AccountingAccount $clearing,
        string $note,
        CarbonImmutable $followUpOn,
        User $actor,
    ): AccountingEntry {
        if (! $clearing->is_clearing) {
            throw ValidationException::withMessages([
                'clearing_account' => [(string) __('accounting.clearing.error.not_a_clearing_account')],
            ]);
        }

        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => [(string) __('accounting.clearing.error.note_required')],
            ]);
        }

        $sourceKey = $this->clearingKey($transaction);
        $existing = $this->journal->activeEntryForSource($organization, $sourceKey);
        if ($existing instanceof AccountingEntry) {
            return $existing;
        }

        $bookedOn = CarbonImmutable::parse($transaction->booking_date)->startOfDay();
        $this->sovereignty->assertLocalPostingAllowed($organization, $bookedOn);

        $bankRule = $this->rules->resolve($organization, PostingSourceKind::Payment, PostingAccountRole::Bank, [], $bookedOn);
        if ($bankRule === null) {
            throw ValidationException::withMessages([
                'clearing_account' => [(string) __('accounting.inbox.blocker.missing_rule', ['role' => PostingAccountRole::Bank->label(), 'criteria' => ''])],
            ]);
        }

        $amount = NumberHelper::roundPrecise(NumberHelper::absPrecise(NumberHelper::normalizeDecimalString((string) $transaction->amount)), 2);
        if (NumberHelper::isZeroPrecise($amount)) {
            throw ValidationException::withMessages([
                'clearing_account' => [(string) __('accounting.inbox.blocker.no_amount')],
            ]);
        }

        // Die Geldseite folgt dem Umsatz; nur die offene Gegenseite wandert
        // aufs Klärungskonto.
        $moneyOnDebit = $transaction->isCredit();

        return $this->journal->postDirect($organization, [
            'booked_on' => $bookedOn,
            'memo' => (string) __('accounting.clearing.memo', ['purpose' => Str::limit((string) $transaction->purpose, 80) ?: '—']),
            'source_type' => $transaction->getMorphClass(),
            'source_id' => $transaction->getKey(),
            'source_key' => $sourceKey,
            'snapshot' => [
                'clearing' => [
                    'note' => $note,
                    'follow_up_on' => $followUpOn->toDateString(),
                    'account' => $clearing->number,
                ],
            ],
            'lines' => [
                [
                    'accounting_account_id' => $bankRule->accounting_account_id,
                    'debit' => $moneyOnDebit ? $amount : '0.00',
                    'credit' => $moneyOnDebit ? '0.00' : $amount,
                ],
                [
                    'accounting_account_id' => $clearing->id,
                    'debit' => $moneyOnDebit ? '0.00' : $amount,
                    'credit' => $moneyOnDebit ? $amount : '0.00',
                    'memo' => $note,
                ],
            ],
        ], $actor);
    }

    /** Idempotenzschlüssel der Klärungsbuchung eines Bankumsatzes. */
    public function clearingKey(BankTransaction $transaction): string {
        return 'clearing:bank:' . $transaction->getKey();
    }

    /**
     * Buchungsstand je Bankumsatz (Feature 125, MVP-681).
     *
     * Drei Wege führen zu einer Buchung: die Zahlungszuordnung, eine bewusste
     * Klärungsbuchung und die interne Umbuchung. Alle drei werden gelesen —
     * die Bankseite speichert keinen eigenen Stand.
     *
     * @param  iterable<BankTransaction>  $transactions
     * @return array<int|string, array{state: string, entry: ?AccountingEntry, blockers: list<string>}>
     */
    public function bankTransactionStates(Organization $organization, iterable $transactions): array {
        $adapter = $this->registry->for(PostingSourceKind::Payment);
        $coupled = $this->transfers->coupledSources($organization);

        $rows = [];
        $keys = [];
        foreach ($transactions as $transaction) {
            $rows[$transaction->getKey()] = $transaction;
            $keys[] = $this->clearingKey($transaction);
            foreach ($transaction->allocations as $allocation) {
                $keys[] = $adapter->sourceKey($allocation);
            }
        }

        $entries = $this->journal->activeEntriesForSources($organization, $keys);
        $result = [];

        foreach ($rows as $id => $transaction) {
            $clearing = $entries[$this->clearingKey($transaction)] ?? null;
            if ($clearing instanceof AccountingEntry) {
                $result[$id] = ['state' => $this->stateOf($clearing, null), 'entry' => $clearing, 'blockers' => []];

                continue;
            }

            $transfer = $coupled[$transaction->getMorphClass() . ':' . $transaction->getKey()] ?? null;
            if ($transfer !== null) {
                $result[$id] = ['state' => 'posted', 'entry' => $transfer->entry, 'blockers' => []];

                continue;
            }

            if ($transaction->allocations->isEmpty()) {
                $result[$id] = ['state' => 'open', 'entry' => null, 'blockers' => [(string) __('accounting.clearing.blocker.unassigned')]];

                continue;
            }

            $result[$id] = $this->aggregateAllocationStates($organization, $adapter, $transaction, $entries);
        }

        return $result;
    }

    /**
     * Zustand eines Umsatzes über seine Zuordnungen — der schwächste zählt.
     *
     * Eine teilweise gebuchte Sammelbuchung ist nicht gebucht: Der Rest wäre
     * sonst unsichtbar.
     *
     * @param  array<string, AccountingEntry>  $entries
     * @return array{state: string, entry: ?AccountingEntry, blockers: list<string>}
     */
    private function aggregateAllocationStates(Organization $organization, PostingSourceAdapter $adapter, BankTransaction $transaction, array $entries): array {
        $rank = ['blocked' => 0, 'open' => 1, 'ready' => 2, 'posted' => 3];
        $weakest = null;
        $entry = null;
        $blockers = [];

        foreach ($transaction->allocations as $allocation) {
            $found = $entries[$adapter->sourceKey($allocation)] ?? null;
            $proposal = $found === null ? $adapter->proposalFor($organization, $allocation) : null;
            $state = $this->stateOf($found, $proposal);

            if ($proposal instanceof PostingProposal) {
                $blockers = [...$blockers, ...$proposal->blockers];
            }

            if ($weakest === null || $rank[$state] < $rank[$weakest]) {
                $weakest = $state;
                $entry = $found;
            }
        }

        return [
            'state' => $weakest ?? 'open',
            'entry' => $entry,
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    /**
     * Buchungsstand je Quelle für die Bestandsseiten (Feature 125, MVP-681).
     *
     * Die Bank- und Kassenseiten bekommen ihren Stand **gelesen** — sie führen
     * keine eigene Statusspalte. Eine zweite Spalte wäre ein zweiter Bestand
     * und würde beim ersten Storno auseinanderlaufen.
     *
     * @param  iterable<Model>  $sources
     * @return array<int|string, array{state: string, entry: ?AccountingEntry, blockers: list<string>}>
     */
    public function statusFor(Organization $organization, PostingSourceKind $kind, iterable $sources, bool $resolveBlockers = false): array {
        $adapter = $this->registry->for($kind);
        $keyed = [];
        foreach ($sources as $source) {
            $keyed[(string) $source->getKey()] = $source;
        }

        $entries = $this->journal->activeEntriesForSources(
            $organization,
            array_values(array_map(fn (Model $source): string => $adapter->sourceKey($source), $keyed)),
        );

        $coupled = $this->transfers->coupledSources($organization);
        $result = [];

        foreach ($keyed as $id => $source) {
            // Ein gekoppelter Beleg ist über die interne Umbuchung gebucht.
            $transfer = $coupled[$source->getMorphClass() . ':' . $source->getKey()] ?? null;
            if ($transfer !== null) {
                $result[$id] = ['state' => 'posted', 'entry' => $transfer->entry, 'blockers' => []];

                continue;
            }

            $entry = $entries[$adapter->sourceKey($source)] ?? null;
            $proposal = null;

            // Der Vorschlag kostet Abfragen — er wird nur gebildet, wenn die
            // Seite die Blockergründe wirklich anzeigt.
            if ($entry === null && $resolveBlockers) {
                $proposal = $adapter->proposalFor($organization, $source);
            }

            $result[$id] = [
                'state' => $this->stateOf($entry, $proposal),
                'entry' => $entry,
                'blockers' => $proposal instanceof PostingProposal ? $proposal->blockers : $this->sovereigntyBlockers($organization, $entry),
            ];
        }

        return $result;
    }

    /**
     * Vorschlag als geprüften Entwurf anlegen (Status `ready`).
     *
     * @throws ValidationException bei Blockern
     */
    public function prepare(Organization $organization, PostingProposal $proposal, User $actor): AccountingEntry {
        if (! $proposal->isPostable()) {
            throw ValidationException::withMessages([
                'proposal' => $proposal->blockers === []
                    ? [(string) __('accounting.inbox.blocker.no_lines')]
                    : $proposal->blockers,
            ]);
        }

        $entry = $this->journal->draft($organization, [
            'booked_on' => $proposal->bookedOn,
            'document_on' => $proposal->documentOn,
            'memo' => $proposal->memo,
            'document_reference' => $proposal->documentReference,
            'source_type' => $proposal->source::class,
            'source_id' => (int) $proposal->source->getKey(),
            'source_key' => $proposal->sourceKey,
            'rule_version' => $proposal->ruleVersion,
            'snapshot' => $proposal->toSnapshot(),
            'lines' => $proposal->lineData(),
        ], $actor);

        return $entry->status->isMutable() ? $this->journal->markReady($entry) : $entry;
    }

    /**
     * Festschreiben mit Vier-Augen-Prüfung.
     *
     * @throws ValidationException wenn dieselbe Person vorbereitet und bucht
     */
    public function post(AccountingEntry $entry, User $actor): AccountingEntry {
        $this->assertFourEyes($entry, $actor);

        return $this->journal->post($entry, $actor);
    }

    /**
     * Stapelverarbeitung: Jede Quelle wird einzeln behandelt; ein Blocker
     * stoppt nur seinen Vorgang, nicht den Lauf. Ein Stapel, der beim ersten
     * Problem abbricht, lässt den Rest der Arbeit liegen.
     *
     * @param  array<int, array{proposal?: PostingProposal|null, entry?: AccountingEntry|null}>  $batch
     * @return array{prepared: int, posted: int, failed: list<string>}
     */
    public function processBatch(Organization $organization, array $batch, User $actor, bool $post): array {
        $prepared = 0;
        $posted = 0;
        $failed = [];

        foreach ($batch as $item) {
            try {
                $entry = $item['entry'] ?? null;
                if ($entry === null && isset($item['proposal'])) {
                    $entry = $this->prepare($organization, $item['proposal'], $actor);
                    $prepared++;
                }

                if ($post && $entry instanceof AccountingEntry && $entry->status->isMutable()) {
                    $this->post($entry, $actor);
                    $posted++;
                }
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    foreach ((array) $messages as $message) {
                        $failed[] = (string) $message;
                    }
                }
            } catch (\RuntimeException $exception) {
                $failed[] = $exception->getMessage();
            }
        }

        return ['prepared' => $prepared, 'posted' => $posted, 'failed' => $failed];
    }

    public function fourEyesEnabled(): bool {
        return (bool) Setting::get(self::FOUR_EYES_KEY, false);
    }

    /** @throws ValidationException */
    private function assertFourEyes(AccountingEntry $entry, User $actor): void {
        if (! $this->fourEyesEnabled()) {
            return;
        }

        if ($entry->created_by !== null && (int) $entry->created_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'four_eyes' => (string) __('accounting.inbox.error.four_eyes'),
            ]);
        }
    }

    /** @return list<string> */
    private function sovereigntyBlockers(Organization $organization, ?AccountingEntry $entry): array {
        if ($entry === null) {
            return [];
        }

        return $this->sovereignty->allowsLocalPosting($organization, CarbonImmutable::parse($entry->booked_on))
            ? []
            : [(string) __('accounting.inbox.blocker.sovereignty')];
    }

    private function stateOf(?AccountingEntry $entry, ?PostingProposal $proposal): string {
        if ($entry?->status === AccountingEntryStatus::Posted || $entry?->status === AccountingEntryStatus::Reversed) {
            return 'posted';
        }
        if ($entry !== null) {
            return 'ready';
        }
        if ($proposal !== null && ! $proposal->isPostable()) {
            return 'blocked';
        }

        return 'open';
    }

    /**
     * Blockiertes zuerst — es ist die Arbeit, die jemand anfassen muss.
     *
     * @param  array<string, mixed>  $item
     */
    private function sortKey(array $item): string {
        $rank = match ($item['state']) {
            'blocked' => '0',
            'open' => '1',
            'ready' => '2',
            default => '3',
        };

        $proposal = $item['proposal'] ?? null;
        $entry = $item['entry'] ?? null;
        $date = $proposal?->bookedOn->toDateString() ?? $entry?->booked_on->toDateString() ?? '9999-12-31';

        return $rank . $date;
    }
}
