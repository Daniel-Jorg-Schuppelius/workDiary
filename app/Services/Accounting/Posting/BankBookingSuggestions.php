<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankBookingSuggestions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\{AccountingEntryStatus, BookingSuggestionBasis, DirectBookingKind, MatchStatus, TransactionDirection};
use App\Models\Accounting\{AccountingEntry, AccountingEntryLine, AccountingTaxCode};
use App\Models\Finance\BankTransaction;
use App\Models\Platform\{Organization, User};
use App\Support\Crypto\{BlindIndex, PurposeKeywordIndex};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Kontierungshilfe für Bankumsätze ohne Beleg (Feature 207, MVP-1660).
 *
 * Miete, Leasing, Versicherung, Kontoführung: Jeden Monat derselbe Umsatz,
 * jeden Monat dieselbe Direktbuchung aufs Sachkonto. Die früheren
 * Direktbuchungen (`DirectBookingKind::BankDirect`) der Organisation **sind**
 * die Regel — es gibt keine eigene Regeltabelle, und eine Korrektur wirkt beim
 * nächsten Mal ohne Pflegeschritt, weil immer die jüngste Buchung den
 * Vorschlag bestimmt.
 *
 * Merkmale in Rangfolge ({@see BookingSuggestionBasis}): Mandatsreferenz,
 * Gläubiger-ID, IBAN der Gegenpartei, Stichwörter des Verwendungszwecks — je
 * nur bei gleicher Richtung. Das erste Merkmal mit früheren Buchungen
 * entscheidet. Kein KI-Vorschlag (Feature 025) und keine Festbuchung ohne
 * Bestätigung (Feature 125): {@see confirm()} bucht nur, was jemand gesehen
 * und bestätigt hat, und auch dann über den gewöhnlichen Weg
 * ({@see PostingInboxService::postBankTransactionToAccount()}) samt
 * Vier-Augen-Prinzip.
 */
class BankBookingSuggestions {
    /** Abweichung zum zuletzt so gebuchten Betrag, ab der ein Umsatz einzeln steht (Prozent). */
    public const string DEVIATION_PERCENT = '10';

    /** Höchstzahl früherer Direktbuchungen, aus denen gelernt wird (jüngste zuerst). */
    private const int LEARNING_LIMIT = 2000;

    /** Höchstzahl offener Umsätze in der Übersicht. */
    private const int OPEN_LIMIT = 500;

    public function __construct(
        private readonly PostingInboxService $inbox,
    ) {}

    /**
     * Vorschläge je Umsatz-ID; Umsätze ohne gleichartige frühere Buchung fehlen.
     *
     * @param  iterable<BankTransaction>  $transactions
     * @return array<int, BankBookingSuggestion>
     */
    public function for(Organization $organization, iterable $transactions): array {
        $learned = $this->learned($organization);
        if ($learned === []) {
            return [];
        }
        $index = $this->index($learned);

        $result = [];
        foreach ($transactions as $transaction) {
            $suggestion = $this->suggest($transaction, $index);
            if ($suggestion instanceof BankBookingSuggestion) {
                $result[(int) $transaction->id] = $suggestion;
            }
        }

        return $result;
    }

    /** Vorschlag für einen einzelnen Umsatz (Dialog „Auf Sachkonto buchen“). */
    public function forTransaction(Organization $organization, BankTransaction $transaction): ?BankBookingSuggestion {
        return $this->for($organization, [$transaction])[(int) $transaction->id] ?? null;
    }

    /**
     * Offene Umsätze ohne Beleg: verbindlicher Auszug, keine Zuordnung, keine
     * Klärungs- oder Direktbuchung, keine interne Umbuchung.
     *
     * @return Collection<int, BankTransaction>
     */
    public function openTransactions(Organization $organization): Collection {
        $transactions = BankTransaction::query()
            ->where('organization_id', $organization->id)
            ->booked()
            ->whereIn('match_status', [MatchStatus::Unmatched->value, MatchStatus::Suggested->value])
            ->whereDoesntHave('allocations')
            ->with('allocations')
            ->orderBy('booking_date')
            ->orderBy('id')
            ->limit(self::OPEN_LIMIT)
            ->get();

        $states = $this->inbox->bankTransactionStates($organization, $transactions);

        return $transactions
            ->filter(fn (BankTransaction $transaction): bool => ($states[$transaction->id]['state'] ?? null) === 'open')
            ->values();
    }

    /**
     * Übersicht: gleichartige Umsätze gebündelt, Abweichungen einzeln.
     *
     * @return array{
     *     groups: list<array{key: string, suggestion: BankBookingSuggestion, items: list<array{transaction: BankTransaction, suggestion: BankBookingSuggestion}>}>,
     *     singles: list<array{transaction: BankTransaction, suggestion: BankBookingSuggestion}>,
     *     without: int
     * }
     */
    public function overview(Organization $organization): array {
        $open = $this->openTransactions($organization);
        $suggestions = $this->for($organization, $open);

        $groups = [];
        $singles = [];
        foreach ($open as $transaction) {
            $suggestion = $suggestions[(int) $transaction->id] ?? null;
            if (! $suggestion instanceof BankBookingSuggestion) {
                continue;
            }
            $item = ['transaction' => $transaction, 'suggestion' => $suggestion];
            if (! $suggestion->confirmable()) {
                $singles[] = $item;

                continue;
            }
            $key = $suggestion->groupKey();
            $groups[$key] ??= ['key' => $key, 'suggestion' => $suggestion, 'items' => []];
            $groups[$key]['items'][] = $item;
        }

        // Größte Gruppe zuerst — dort spart eine Bestätigung am meisten.
        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => count($b['items']) <=> count($a['items']) ?: strcmp($a['key'], $b['key']));

        return ['groups' => $groups, 'singles' => $singles, 'without' => $open->count() - count($suggestions)];
    }

    /**
     * Gebündelte Bestätigung: bucht jeden genannten Umsatz, der noch offen ist
     * und noch genau diesen Vorschlag trägt. Was sich seit der Anzeige geändert
     * hat oder einzeln steht, bleibt liegen. Jeder Umsatz bucht für sich — ein
     * Fehler hält die übrigen nicht auf.
     *
     * @param  list<int>  $transactionIds
     * @return array{posted: int, submitted: int, skipped: int, failed: list<string>}
     */
    public function confirm(Organization $organization, string $groupKey, array $transactionIds, User $actor): array {
        $transactions = BankTransaction::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $transactionIds)
            ->with(['allocations', 'statement'])
            ->orderBy('booking_date')
            ->orderBy('id')
            ->get();

        $states = $this->inbox->bankTransactionStates($organization, $transactions);
        // Vorschläge vor der ersten Buchung bilden: Jede Buchung verschiebt den Lernstand.
        $suggestions = $this->for($organization, $transactions);

        $result = ['posted' => 0, 'submitted' => 0, 'skipped' => count($transactionIds) - $transactions->count(), 'failed' => []];
        foreach ($transactions as $transaction) {
            $suggestion = $suggestions[(int) $transaction->id] ?? null;
            if (($states[$transaction->id]['state'] ?? null) !== 'open'
                || $transaction->isProvisional()
                || ! $suggestion instanceof BankBookingSuggestion
                || ! $suggestion->confirmable()
                || $suggestion->groupKey() !== $groupKey) {
                $result['skipped']++;

                continue;
            }

            try {
                $entry = $this->inbox->postBankTransactionToAccount(
                    $organization,
                    $transaction,
                    $suggestion->account,
                    $suggestion->taxCode,
                    $suggestion->costCenterId,
                    $suggestion->note,
                    $actor,
                );
                $entry->status->isPosted() ? $result['posted']++ : $result['submitted']++;
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    foreach ((array) $messages as $message) {
                        $result['failed'][] = (string) $message;
                    }
                }
            } catch (\RuntimeException $exception) {
                $result['failed'][] = $exception->getMessage();
            }
        }

        return $result;
    }

    /**
     * Frühere, festgeschriebene Direktbuchungen von Bankumsätzen — jüngste
     * zuerst. Entwürfe zählen nicht (noch nicht entschieden), stornierte nicht
     * (zurückgenommen).
     *
     * @return list<array{entry: AccountingEntry, line: AccountingEntryLine, direction: TransactionDirection, booked_on: CarbonImmutable, amount: numeric-string, mandate: ?string, creditor: ?string, iban: ?string, keywords: ?string, note: string}>
     */
    private function learned(Organization $organization): array {
        $entries = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->where('status', AccountingEntryStatus::Posted->value)
            ->where('source_type', MorphMap::alias(BankTransaction::class))
            ->where('source_key', 'like', 'bankdirect:bank:%')
            ->with(['lines.account', 'lines.taxCode'])
            ->orderByDesc('booked_on')
            ->orderByDesc('id')
            ->limit(self::LEARNING_LIMIT)
            ->get()
            ->filter(static fn (AccountingEntry $entry): bool => DirectBookingKind::of($entry) === DirectBookingKind::BankDirect);
        if ($entries->isEmpty()) {
            return [];
        }

        $transactions = BankTransaction::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $entries->pluck('source_id')->map(intval(...))->unique()->values()->all())
            ->get()
            ->keyBy('id');

        $learned = [];
        foreach ($entries as $entry) {
            $transaction = $transactions->get((int) $entry->source_id);
            $line = $this->accountLine($entry);
            if (! $transaction instanceof BankTransaction || ! $line instanceof AccountingEntryLine) {
                continue;
            }
            $snapshot = is_array($entry->snapshot) ? $entry->snapshot : [];

            $learned[] = [
                'entry' => $entry,
                'line' => $line,
                'direction' => $transaction->direction,
                'booked_on' => CarbonImmutable::parse($transaction->booking_date),
                'amount' => $this->amount($transaction),
                'mandate' => $this->normalized($transaction->mandate_ref),
                'creditor' => $this->normalized($transaction->creditor_identifier),
                'iban' => $transaction->counterparty_iban_hash,
                'keywords' => $this->keywordHash($transaction),
                'note' => (string) ($snapshot['bank_direct']['note'] ?? $line->memo ?? ''),
            ];
        }

        // Nach Buchungstag des Umsatzes: „zuletzt“ meint den jüngsten Umsatz, nicht die jüngste Erfassung.
        usort($learned, static fn (array $a, array $b): int => $b['booked_on'] <=> $a['booked_on'] ?: (int) $b['entry']->id <=> (int) $a['entry']->id);

        return $learned;
    }

    /**
     * Sachkontozeile einer Direktbuchung: das Konto aus dem Nachweis, sonst die
     * erste Zeile, die weder Geld- noch Steuerkonto ist.
     */
    private function accountLine(AccountingEntry $entry): ?AccountingEntryLine {
        $snapshot = is_array($entry->snapshot) ? $entry->snapshot : [];
        $number = (string) ($snapshot['bank_direct']['account'] ?? '');

        $byNumber = $entry->lines->first(static fn (AccountingEntryLine $line): bool => $number !== '' && $line->account?->number === $number);
        if ($byNumber instanceof AccountingEntryLine) {
            return $byNumber;
        }

        $candidates = $entry->lines->filter(static fn (AccountingEntryLine $line): bool => $line->account !== null
            && ! $line->account->is_bank && ! $line->account->is_cash && ! $line->account->is_clearing);

        return $candidates->first(static fn (AccountingEntryLine $line): bool => $line->accounting_tax_code_id !== null) ?? $candidates->first();
    }

    /**
     * Merkmal → Lernzeilen, je Rang.
     *
     * @param  list<array<string, mixed>>  $learned
     * @return array{positions: array<string, array<string, list<int>>>, rows: list<array<string, mixed>>}
     */
    private function index(array $learned): array {
        $index = [];
        foreach ($learned as $position => $row) {
            $direction = $row['direction']->value;
            foreach ([
                BookingSuggestionBasis::Mandate->value => $row['mandate'],
                BookingSuggestionBasis::Creditor->value => $row['creditor'],
                BookingSuggestionBasis::Iban->value => $row['iban'],
                BookingSuggestionBasis::Keywords->value => $row['keywords'],
            ] as $basis => $value) {
                if (is_string($value) && $value !== '') {
                    $index[$basis][$direction . '|' . $value][] = $position;
                }
            }
        }

        return ['positions' => $index, 'rows' => $learned];
    }

    /**
     * @param  array{positions: array<string, array<string, list<int>>>, rows: list<array<string, mixed>>}  $index
     */
    private function suggest(BankTransaction $transaction, array $index): ?BankBookingSuggestion {
        /** @var list<array<string, mixed>> $rows */
        $rows = $index['rows'];
        $direction = $transaction->direction->value;
        $creditor = $this->normalized($transaction->creditor_identifier);

        foreach (BookingSuggestionBasis::cases() as $basis) {
            $positions = [];
            foreach ($this->featureValues($transaction, $basis) as $value) {
                foreach ($index['positions'][$basis->value][$direction . '|' . $value] ?? [] as $position) {
                    $positions[$position] = true;
                }
            }

            $matches = [];
            foreach (array_keys($positions) as $position) {
                $row = $rows[$position];
                if ((int) $row['entry']->source_id === (int) $transaction->id) {
                    continue;
                }
                // Gleiche Mandatsreferenz bei einem anderen Gläubiger ist ein anderes Mandat.
                if ($basis === BookingSuggestionBasis::Mandate && $creditor !== null && $row['creditor'] !== null && $row['creditor'] !== $creditor) {
                    continue;
                }
                $matches[$position] = $row;
            }

            if ($matches !== []) {
                ksort($matches);

                return $this->build($transaction, $basis, array_values($matches));
            }
        }

        return null;
    }

    /**
     * Die jüngste Buchung bestimmt die Kontierung; gezählt werden die früheren
     * Buchungen mit genau dieser Kontierung.
     *
     * @param  non-empty-list<array<string, mixed>>  $matches  jüngste zuerst
     */
    private function build(BankTransaction $transaction, BookingSuggestionBasis $basis, array $matches): ?BankBookingSuggestion {
        $latest = $matches[0];
        /** @var AccountingEntryLine $line */
        $line = $latest['line'];
        $account = $line->account;
        if ($account === null) {
            return null;
        }
        $combination = static fn (AccountingEntryLine $candidate): string => $candidate->accounting_account_id . '|' . ($candidate->accounting_tax_code_id ?? '') . '|' . ($candidate->cost_center_id ?? '');
        $same = count(array_filter($matches, static fn (array $row): bool => $combination($row['line']) === $combination($line)));

        $bookingDate = CarbonImmutable::parse($transaction->booking_date);
        $taxCode = $line->taxCode;
        $warning = match (true) {
            ! $account->is_active => BankBookingSuggestion::WARNING_ACCOUNT_INACTIVE,
            $taxCode instanceof AccountingTaxCode && ! $this->validOn($taxCode, $bookingDate) => BankBookingSuggestion::WARNING_TAX_CODE_INVALID,
            default => null,
        };

        return new BankBookingSuggestion(
            transactionId: (int) $transaction->id,
            basis: $basis,
            account: $account,
            taxCode: $taxCode,
            costCenterId: $line->cost_center_id !== null ? (int) $line->cost_center_id : null,
            note: $latest['note'],
            matches: $same,
            total: count($matches),
            lastBookedOn: $latest['booked_on'],
            lastAmount: $latest['amount'],
            deviates: $this->deviates($this->amount($transaction), $latest['amount']),
            warning: $warning,
            learnedEntryId: (int) $latest['entry']->id,
        );
    }

    /**
     * Werte eines Merkmals am offenen Umsatz. Die IBAN liefert beide Abdrücke
     * (geschlüsselt und alt), damit Bestandsumsätze vor der Umrechnung passen.
     *
     * @return list<string>
     */
    private function featureValues(BankTransaction $transaction, BookingSuggestionBasis $basis): array {
        $values = match ($basis) {
            BookingSuggestionBasis::Mandate => [$this->normalized($transaction->mandate_ref)],
            BookingSuggestionBasis::Creditor => [$this->normalized($transaction->creditor_identifier)],
            BookingSuggestionBasis::Iban => [$transaction->counterparty_iban_hash, ...BlindIndex::ibanCandidates($transaction->counterparty_iban)],
            BookingSuggestionBasis::Keywords => [$this->keywordHash($transaction)],
        };

        return array_values(array_unique(array_filter($values, static fn (?string $value): bool => $value !== null && $value !== '')));
    }

    /** Abdruck der Zweck-Stichwörter: gespeichert, sonst im Speicher aus dem Zweck (Bestand vor Feature 207). */
    private function keywordHash(BankTransaction $transaction): ?string {
        return $transaction->purpose_keyword_hash ?? PurposeKeywordIndex::hash($transaction->purpose);
    }

    private function normalized(?string $value): ?string {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }

    /** @return numeric-string */
    private function amount(BankTransaction $transaction): string {
        return NumberHelper::roundPrecise(NumberHelper::absPrecise(NumberHelper::normalizeDecimalString((string) $transaction->amount)), 2);
    }

    /**
     * Mehr als {@see DEVIATION_PERCENT} Prozent Abstand zum zuletzt so gebuchten Betrag.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $last
     */
    private function deviates(string $amount, string $last): bool {
        if (NumberHelper::isZeroPrecise($last)) {
            return ! NumberHelper::isZeroPrecise($amount);
        }
        $difference = NumberHelper::absPrecise(NumberHelper::subtractPrecise($amount, $last, 2));
        $allowed = NumberHelper::percentOfPrecise($last, self::DEVIATION_PERCENT, 2);

        return NumberHelper::comparePrecise($difference, $allowed, 2) > 0;
    }

    private function validOn(AccountingTaxCode $code, CarbonImmutable $date): bool {
        return $code->is_active
            && $code->valid_from->toDateString() <= $date->toDateString()
            && ($code->valid_to === null || $code->valid_to->toDateString() >= $date->toDateString());
    }
}
