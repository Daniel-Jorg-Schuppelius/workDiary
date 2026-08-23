<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JournalService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\AccountingEntryStatus;
use App\Models\Accounting\{AccountingEntry, AccountingEntryLine, AccountingPeriod, AccountingProfile};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type EntryDraftData array{booked_on: CarbonImmutable, memo: string,
 *     document_on?: CarbonImmutable|null, document_reference?: string|null,
 *     source_type?: string|null, source_id?: int|null, source_key?: string|null,
 *     rule_version?: string|null, snapshot?: array<string, mixed>|null,
 *     lines: array<int, array<string, mixed>>}
 *
 * Buchungsjournal (Feature 125, MVP-672) — EINZIGE Schreibstelle für
 * Buchungen und Buchungszeilen (Muster {@see \App\Services\Finance\CashBookService}).
 *
 * Die Festschreibung prüft in dieser Reihenfolge:
 *   1. Buchungshoheit am **Buchungsdatum** (MVP-671),
 *   2. Periode existiert und ist offen,
 *   3. mindestens zwei Zeilen, gültige und aktive Konten derselben Organisation,
 *   4. Basiswährung,
 *   5. Soll = Haben, centgenau und ungleich null.
 *
 * Erst danach entsteht die Journalnummer — sie ist lückenlos, also darf sie
 * nicht an einer Buchung hängen bleiben, die am Guard scheitert.
 *
 * Korrektur ausschließlich über {@see self::reverse()}: eine echte
 * Gegenbuchung mit Pflichtbegründung, nachgewiesen in der Hash-Kette.
 */
class JournalService {
    public function __construct(
        private readonly AccountingSovereigntyResolver $sovereignty,
        private readonly FiscalYearService $fiscalYears,
        private readonly AccountingEventRecorder $events,
        private readonly OpenItemService $openItems,
    ) {}

    /**
     * Buchungsentwurf anlegen. Ist für den Idempotenzschlüssel bereits eine
     * aktive Buchung vorhanden, wird diese zurückgegeben — dieselbe Quelle
     * darf nie zweimal im Journal landen.
     *
     * @param  EntryDraftData  $data
     */
    public function draft(Organization $organization, array $data, User $actor): AccountingEntry {
        $existing = $this->activeEntryForSource($organization, $data['source_key'] ?? null);
        if ($existing instanceof AccountingEntry) {
            return $existing;
        }

        $bookedOn = $data['booked_on'];
        $period = $this->periodOrFail($organization, $bookedOn);

        return DB::transaction(function () use ($organization, $data, $actor, $bookedOn, $period): AccountingEntry {
            $entry = AccountingEntry::query()->create([
                'organization_id' => $organization->id,
                'accounting_fiscal_year_id' => $period->accounting_fiscal_year_id,
                'accounting_period_id' => $period->id,
                'booked_on' => $bookedOn->toDateString(),
                'document_on' => ($data['document_on'] ?? null)?->toDateString(),
                'status' => AccountingEntryStatus::Draft,
                'memo' => $data['memo'],
                'document_reference' => $data['document_reference'] ?? null,
                'currency' => $this->baseCurrency($organization),
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'source_key' => $this->availableSourceKey($organization, $data['source_key'] ?? null),
                // Zusammenfassung der Regelfassungen; der vollständige Nachweis
                // je Zeile liegt im Snapshot, deshalb ist Kürzen hier unkritisch.
                'rule_version' => ($data['rule_version'] ?? null) !== null ? mb_substr((string) $data['rule_version'], 0, 191) : null,
                'snapshot' => $data['snapshot'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->replaceLines($entry, $data['lines']);

            return $entry->fresh(['lines']) ?? $entry;
        });
    }

    /**
     * Zeilen eines Arbeitsstands ersetzen.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function updateLines(AccountingEntry $entry, array $lines): AccountingEntry {
        $this->assertMutable($entry);

        return DB::transaction(function () use ($entry, $lines): AccountingEntry {
            $entry->lines()->delete();
            $this->replaceLines($entry, $lines);

            return $entry->fresh(['lines']) ?? $entry;
        });
    }

    /** Arbeitsstand als geprüft markieren (Vorstufe der Festschreibung). */
    public function markReady(AccountingEntry $entry): AccountingEntry {
        $this->assertMutable($entry);
        $entry->update(['status' => AccountingEntryStatus::Ready]);

        return $entry->refresh();
    }

    /**
     * Festschreiben. Danach ist die Buchung fachlich unveränderlich.
     *
     * @throws ValidationException wenn ein Guard greift
     */
    public function post(AccountingEntry $entry, User $actor): AccountingEntry {
        $this->assertMutable($entry);

        $organization = $this->organizationOf($entry);
        $bookedOn = CarbonImmutable::parse($entry->booked_on)->startOfDay();

        // 1. Buchungshoheit am Buchungsdatum — nicht "heute".
        $this->sovereignty->assertLocalPostingAllowed($organization, $bookedOn);

        // 2. Periode offen? (Die Periode kann sich seit dem Entwurf geändert haben.)
        $period = $this->periodOrFail($organization, $bookedOn);
        if (! $period->status->acceptsPostings()) {
            throw ValidationException::withMessages([
                'booked_on' => (string) __('accounting.ledger.error.period_closed', [
                    'period' => $period->starts_on->format(\App\Support\Formats::date()),
                ]),
            ]);
        }

        $entry->load('lines.account');
        $this->assertPostable($entry, $organization);

        return DB::transaction(function () use ($entry, $actor, $organization, $period): AccountingEntry {
            $entry->forceFill([
                'accounting_period_id' => $period->id,
                'accounting_fiscal_year_id' => $period->accounting_fiscal_year_id,
                'journal_no' => $this->nextJournalNumber($organization),
                'status' => AccountingEntryStatus::Posted,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ])->save();

            $this->events->record($organization, 'accounting.entry_posted', [
                'journal_no' => $entry->journal_no,
                'booked_on' => $entry->booked_on->toDateString(),
                'debit' => $entry->debitTotal()->getAmount(),
                'credit' => $entry->creditTotal()->getAmount(),
                'source_key' => $entry->source_key,
            ], $entry, $actor);

            // Offene Posten entstehen AUS der Festbuchung — in derselben
            // Transaktion, sonst gäbe es einen Moment mit Buchung ohne OPOS.
            $this->openItems->applyEntry($entry);

            return $entry->refresh();
        });
    }

    /**
     * Entwurf anlegen und in einem Zug festschreiben.
     *
     * @param  EntryDraftData  $data
     */
    public function postDirect(Organization $organization, array $data, User $actor): AccountingEntry {
        $entry = $this->draft($organization, $data, $actor);

        return $entry->status->isPosted() ? $entry : $this->post($entry, $actor);
    }

    /**
     * Storno als echte Gegenbuchung: Soll und Haben getauscht, Bezug in beide
     * Richtungen, Pflichtbegründung. Das Original bleibt inhaltlich stehen.
     */
    public function reverse(AccountingEntry $entry, string $reason, User $actor, ?CarbonImmutable $bookedOn = null): AccountingEntry {
        if ($entry->status !== AccountingEntryStatus::Posted) {
            throw ValidationException::withMessages([
                'status' => (string) __('accounting.ledger.error.reverse_not_posted'),
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reversal_reason' => (string) __('accounting.ledger.error.reversal_reason_required'),
            ]);
        }

        $organization = $this->organizationOf($entry);
        $bookedOn ??= $this->reversalDate($organization, $entry);
        $entry->load('lines');

        return DB::transaction(function () use ($entry, $reason, $actor, $organization, $bookedOn): AccountingEntry {
            $lines = $entry->lines->map(fn (AccountingEntryLine $line): array => [
                // Gespiegelt: aus Soll wird Haben.
                'accounting_account_id' => $line->accounting_account_id,
                'debit' => $line->credit?->getAmount() ?? '0.00',
                'credit' => $line->debit?->getAmount() ?? '0.00',
                'accounting_tax_code_id' => $line->accounting_tax_code_id,
                'tax_amount' => $line->tax_amount?->getAmount(),
                'counterparty_type' => $line->counterparty_type,
                'counterparty_id' => $line->counterparty_id,
                'project_id' => $line->project_id,
                'asset_id' => $line->asset_id,
                'cost_group' => $line->cost_group,
                'memo' => $line->memo,
            ])->all();

            $reversal = $this->draft($organization, [
                'booked_on' => $bookedOn,
                'document_on' => $entry->document_on !== null ? CarbonImmutable::parse($entry->document_on) : null,
                'memo' => (string) __('accounting.ledger.reversal_memo', ['no' => (string) $entry->journal_no]),
                'document_reference' => $entry->document_reference,
                'source_key' => $entry->source_key !== null ? 'reversal:' . $entry->id : null,
                'snapshot' => ['reverses' => $entry->journal_no, 'reason' => $reason],
                'lines' => $lines,
            ], $actor);

            $reversal->forceFill(['reverses_entry_id' => $entry->id])->save();
            $reversal = $this->post($reversal, $actor);

            // Einzige erlaubte Änderung an einer Festbuchung (Guard im Modell).
            $entry->forceFill([
                'status' => AccountingEntryStatus::Reversed,
                'reversed_by_entry_id' => $reversal->id,
                'reversal_reason' => $reason,
            ])->save();

            $this->events->record($organization, 'accounting.entry_reversed', [
                'journal_no' => $entry->journal_no,
                'reversal_journal_no' => $reversal->journal_no,
                'reason' => $reason,
            ], $entry, $actor);

            // Der Storno nimmt auch die OPOS-Wirkung zurück: erzeugte Posten
            // werden ausgebucht, geleistete Ausgleiche gegengebucht.
            $this->openItems->reverseEntry($entry, $reversal);

            return $reversal->refresh();
        });
    }

    /**
     * Eröffnungsbuchung zum Startdatum: Salden und offene Posten des
     * Altbestands. Sie ist eine gewöhnliche Buchung mit eigener Quelle —
     * damit greifen dieselben Guards wie überall sonst.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function openingBalance(Organization $organization, array $lines, User $actor): AccountingEntry {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();
        if (! $profile instanceof AccountingProfile || $profile->starts_on === null) {
            throw ValidationException::withMessages([
                'starts_on' => (string) __('accounting.ledger.preflight.starts_on_missing'),
            ]);
        }

        return $this->postDirect($organization, [
            'booked_on' => CarbonImmutable::parse($profile->starts_on),
            'memo' => (string) __('accounting.ledger.opening_memo'),
            'source_key' => 'opening_balance',
            'lines' => $lines,
        ], $actor);
    }

    /** Aktive (nicht stornierte) Buchung zu einem Idempotenzschlüssel. */
    public function activeEntryForSource(Organization $organization, ?string $sourceKey): ?AccountingEntry {
        if ($sourceKey === null || $sourceKey === '') {
            return null;
        }

        return AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->where('source_key', $sourceKey)
            ->whereIn('status', [
                AccountingEntryStatus::Draft->value,
                AccountingEntryStatus::Ready->value,
                AccountingEntryStatus::Posted->value,
            ])
            ->first();
    }

    /**
     * Aktive Buchungen zu mehreren Schlüsseln in einer Abfrage.
     *
     * Für Listen gedacht: Die Bestandsseiten fragen ihren Buchungsstand ab,
     * ohne je Zeile eine eigene Abfrage abzusetzen.
     *
     * @param  list<string>  $sourceKeys
     * @return array<string, AccountingEntry>
     */
    public function activeEntriesForSources(Organization $organization, array $sourceKeys): array {
        $keys = array_values(array_filter(array_unique($sourceKeys), static fn (string $key): bool => $key !== ''));
        if ($keys === []) {
            return [];
        }

        return AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('source_key', $keys)
            ->whereIn('status', [
                AccountingEntryStatus::Draft->value,
                AccountingEntryStatus::Ready->value,
                AccountingEntryStatus::Posted->value,
            ])
            ->get()
            ->keyBy(fn (AccountingEntry $entry): string => (string) $entry->source_key)
            ->all();
    }

    /**
     * Freier Idempotenzschlüssel. Nach einem Storno darf dieselbe Quelle neu
     * gebucht werden — dann mit Zähler-Suffix, damit die stornierte Buchung
     * ihren Schlüssel behält (sie ist unveränderlich).
     */
    private function availableSourceKey(Organization $organization, ?string $sourceKey): ?string {
        if ($sourceKey === null || $sourceKey === '') {
            return null;
        }

        $candidate = $sourceKey;
        $attempt = 1;
        while (AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->where('source_key', $candidate)
            ->exists()) {
            $attempt++;
            $candidate = $sourceKey . '#' . $attempt;
        }

        return $candidate;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function replaceLines(AccountingEntry $entry, array $lines): void {
        $no = 1;
        foreach ($lines as $line) {
            AccountingEntryLine::query()->create([
                'organization_id' => $entry->organization_id,
                'accounting_entry_id' => $entry->id,
                'line_no' => $no++,
                'accounting_account_id' => $line['accounting_account_id'],
                'debit' => $line['debit'] ?? '0.00',
                'credit' => $line['credit'] ?? '0.00',
                'currency' => $entry->currency,
                'accounting_tax_code_id' => $line['accounting_tax_code_id'] ?? null,
                'tax_amount' => $line['tax_amount'] ?? null,
                'counterparty_type' => $line['counterparty_type'] ?? null,
                'counterparty_id' => $line['counterparty_id'] ?? null,
                'project_id' => $line['project_id'] ?? null,
                'asset_id' => $line['asset_id'] ?? null,
                'cost_group' => $line['cost_group'] ?? null,
                'memo' => $line['memo'] ?? null,
            ]);
        }
    }

    private function assertMutable(AccountingEntry $entry): void {
        if (! $entry->status->isMutable()) {
            throw ValidationException::withMessages([
                'status' => (string) __('accounting.ledger.error.entry_frozen'),
            ]);
        }
    }

    /** Fachliche Prüfungen vor der Festschreibung. */
    private function assertPostable(AccountingEntry $entry, Organization $organization): void {
        if ($entry->lines->count() < 2) {
            throw ValidationException::withMessages([
                'lines' => (string) __('accounting.ledger.error.needs_two_lines'),
            ]);
        }

        $base = $this->baseCurrency($organization);
        foreach ($entry->lines as $line) {
            $account = $line->account;
            if ($account === null || (int) $account->organization_id !== (int) $organization->id) {
                throw ValidationException::withMessages([
                    'lines' => (string) __('accounting.ledger.error.unknown_account'),
                ]);
            }

            if (! $account->is_active) {
                throw ValidationException::withMessages([
                    'lines' => (string) __('accounting.ledger.error.inactive_account', ['account' => $account->displayLabel()]),
                ]);
            }

            if ($line->currency !== $base) {
                throw ValidationException::withMessages([
                    'lines' => (string) __('accounting.ledger.error.foreign_currency_line', ['currency' => $base->value]),
                ]);
            }

            // Genau eine Seite je Zeile — sonst ist die Zeile keine Buchung,
            // sondern eine Rechnung, die niemand nachvollziehen kann.
            $debit = $line->debit ?? Money::zero($base);
            $credit = $line->credit ?? Money::zero($base);
            if ($debit->isNegative() || $credit->isNegative()) {
                throw ValidationException::withMessages([
                    'lines' => (string) __('accounting.ledger.error.negative_amount'),
                ]);
            }
            if (! $debit->isZero() && ! $credit->isZero()) {
                throw ValidationException::withMessages([
                    'lines' => (string) __('accounting.ledger.error.both_sides'),
                ]);
            }
        }

        if (! $entry->isBalanced()) {
            throw ValidationException::withMessages([
                'lines' => (string) __('accounting.ledger.error.unbalanced', [
                    'debit' => $entry->debitTotal()->format(),
                    'credit' => $entry->creditTotal()->format(),
                ]),
            ]);
        }
    }

    private function periodOrFail(Organization $organization, CarbonImmutable $date): AccountingPeriod {
        $period = $this->fiscalYears->periodFor($organization, $date);
        if (! $period instanceof AccountingPeriod) {
            throw ValidationException::withMessages([
                'booked_on' => (string) __('accounting.ledger.error.no_period', [
                    'date' => $date->format(\App\Support\Formats::date()),
                ]),
            ]);
        }

        return $period;
    }

    /**
     * Nächste Journalnummer. Die Profilzeile der Organisation dient als
     * Sperranker (Muster CashBookService::lockRegister) — zwei gleichzeitige
     * Festschreibungen können so nicht dieselbe Nummer ziehen.
     */
    private function nextJournalNumber(Organization $organization): int {
        DB::table('accounting_profiles')
            ->where('organization_id', $organization->id)
            ->lockForUpdate()
            ->first();

        $max = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->max('journal_no');

        return (int) $max + 1;
    }

    /** Buchungsdatum der Gegenbuchung: Originaltag, wenn dessen Periode noch offen ist. */
    private function reversalDate(Organization $organization, AccountingEntry $entry): CarbonImmutable {
        $original = CarbonImmutable::parse($entry->booked_on);
        $period = $this->fiscalYears->periodFor($organization, $original);

        if ($period instanceof AccountingPeriod && $period->status->acceptsPostings()) {
            return $original;
        }

        return CarbonImmutable::now()->startOfDay();
    }

    /** Basiswährung der Organisation — der erste Schnitt führt genau eine. */
    public function baseCurrency(Organization $organization): CurrencyCode {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        return $profile instanceof AccountingProfile ? $profile->base_currency : CurrencyCode::Euro;
    }

    /**
     * Organisation der Buchung. Eine Buchung ohne Mandant wäre ein Datenfehler,
     * kein Sonderfall — deshalb hier hart statt still weiterlaufend.
     */
    private function organizationOf(AccountingEntry $entry): Organization {
        $organization = $entry->organization;
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => (string) __('accounting.ledger.error.entry_without_organization'),
            ]);
        }

        return $organization;
    }
}
