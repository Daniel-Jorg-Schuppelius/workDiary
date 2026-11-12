<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManualEntryLines.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\TaxCodeDirection;
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEntryLine, AccountingTaxCode};
use App\Models\Customer\Customer;
use App\Models\Finance\CostCenter;
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Support\{MorphMap, Sqid};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Validation\ValidationException;

/**
 * Zeilen einer Buchung von Hand (Handbuchung, Entwurf bearbeiten,
 * Buchungsvorlage — MVP-1556/1558).
 *
 * Übersetzt Formularzeilen in die Zeilenform des {@see JournalService} und
 * zurück. Eine Zeile mit Steuerkennzeichen trägt den **Nettobetrag**; die
 * Steuerzeile entsteht automatisch dahinter auf dem Steuerkonto des
 * Kennzeichens. Kennzeichen und Steuerbetrag stehen an der Nettozeile — so
 * liest sie die Kennziffern-Aufteilung
 * ({@see Filing\VatFieldBreakdownService}). Ein Kennzeichen der Steuerschuld
 * des Empfängers (MVP-1621) erzeugt zwei Steuerzeilen: Vorsteuer auf der Seite
 * der Zeile, Umsatzsteuer auf der Gegenseite.
 */
class ManualEntryLines {
    /** Präfixe der Gegenpartei-Auswahl (`customer:<sqid>`). */
    private const array PARTIES = ['customer' => Customer::class, 'supplier' => Supplier::class];

    /** Zeilen der Maske; leere werden ignoriert. */
    private const int MIN_ROWS = 6;

    /**
     * Auswahllisten der Zeilenmaske (`finance.accounting._entry_rows`).
     *
     * @return array<string, mixed>
     */
    public function formOptions(Organization $organization, CarbonImmutable $on): array {
        return [
            'accounts' => AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->active()
                ->orderBy('number')
                ->get(),
            'taxCodes' => AccountingTaxCode::query()
                ->where('organization_id', $organization->id)
                ->validOn($on)
                ->orderBy('code')
                ->get(),
            'costCenters' => CostCenter::query()->where('organization_id', $organization->id)->where('active', true)->orderBy('code')->get(),
            'customers' => Customer::query()->where('organization_id', $organization->id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'number']),
            'suppliers' => Supplier::query()->where('organization_id', $organization->id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'number']),
        ];
    }

    /** @param array<int, mixed> $rows */
    public function rowCount(array $rows): int {
        return max(self::MIN_ROWS, count($rows) + 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  Formularzeilen: account (Kontonummer), debit, credit, tax_code, cost_center, memo, line
     * @param  array<int, array<string, mixed>>  $existing  bisherige Zeilen (Zeilenform), deren übrige Bezüge erhalten bleiben
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function build(Organization $organization, CarbonImmutable $bookedOn, array $rows, ?string $counterparty, array $existing = []): array {
        $currency = app(JournalService::class)->baseCurrency($organization);
        [$partyType, $partyId] = $this->resolveCounterparty($organization, $counterparty);
        $lines = [];

        foreach ($rows as $index => $row) {
            $accountKey = trim((string) ($row['account'] ?? ''));
            $debit = $this->amount($row['debit'] ?? null);
            $credit = $this->amount($row['credit'] ?? null);
            if ($accountKey === '' && $debit === null && $credit === null) {
                continue;
            }

            // Das Formular nennt die Kontonummer (Eingabe mit Vorschlagsliste).
            $account = $accountKey === '' ? null : AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->where('number', $accountKey)
                ->first();
            if (! $account instanceof AccountingAccount) {
                throw $this->rowError($index, 'accounting.ledger.error.unknown_account');
            }
            if (($debit === null) === ($credit === null)) {
                throw $this->rowError($index, 'accounting.entry_form.error.one_side');
            }

            $taxCode = $this->taxCode($organization, $bookedOn, (string) ($row['tax_code'] ?? ''), $index);
            $onDebit = $debit !== null;
            $net = Money::of((string) ($debit ?? $credit), $currency);

            $costCenterKey = trim((string) ($row['cost_center'] ?? ''));
            $costCenterId = $costCenterKey === '' ? null : Sqid::decodeOrNumeric(CostCenter::class, $costCenterKey);

            // Bezüge, die das Formular nicht zeigt (Projekt, Anlage, Kostengruppe), bleiben an der Zeile.
            $kept = $existing[(int) ($row['line'] ?? -1)] ?? [];
            $line = [
                'accounting_account_id' => $account->id,
                'debit' => $onDebit ? $net->getAmount() : '0.00',
                'credit' => $onDebit ? '0.00' : $net->getAmount(),
                'accounting_tax_code_id' => $taxCode?->id,
                'tax_amount' => null,
                'counterparty_type' => $kept['counterparty_type'] ?? null,
                'counterparty_id' => $kept['counterparty_id'] ?? null,
                'project_id' => $kept['project_id'] ?? null,
                'asset_id' => $kept['asset_id'] ?? null,
                'cost_center_id' => $costCenterId,
                'cost_group' => $kept['cost_group'] ?? null,
                'memo' => ($memo = trim((string) ($row['memo'] ?? ''))) !== '' ? $memo : null,
            ];

            // Die Gegenpartei gehört an den offenen Posten und an die Steuerzeile (Zusammenfassende Meldung).
            if ($partyType !== null && ($account->is_open_item || $taxCode instanceof AccountingTaxCode)) {
                $line['counterparty_type'] = $partyType;
                $line['counterparty_id'] = $partyId;
            } elseif ($counterparty !== null && $partyType === null && ($account->is_open_item || $taxCode instanceof AccountingTaxCode)) {
                $line['counterparty_type'] = null;
                $line['counterparty_id'] = null;
            }

            if (! $taxCode instanceof AccountingTaxCode) {
                $lines[] = $line;

                continue;
            }

            // Steht die Zeile selbst auf dem Steuerkonto (Vorschlag aus der Inbox), ist sie die Steuer.
            $isTaxLine = $taxCode->isTaxAccount((int) $account->id);
            $tax = $isTaxLine ? $net : $net->percentage((string) $taxCode->rate);
            // Unveränderte Zeile eines Inbox-Entwurfs: Die Steuer des Belegs gilt, nicht die nachgerechnete.
            if (! $isTaxLine && ($kept['tax_amount'] ?? null) !== null
                && (int) ($kept['accounting_tax_code_id'] ?? 0) === (int) $taxCode->id
                && Money::of((string) ($kept[$onDebit ? 'debit' : 'credit'] ?? '0'), $currency)->equals($net)) {
                $tax = Money::of((string) $kept['tax_amount'], $currency)->abs();
            }
            // Vorzeichen nach Wirkung: Ausgangssteuer im Haben, Vorsteuer und Eigenberechnung im Soll erhöhen die Kennziffer.
            $increases = $taxCode->direction->basedOnDebit() ? $onDebit : ! $onDebit;
            if ($taxCode->direction === TaxCodeDirection::None) {
                $increases = true;
            }
            $line['tax_amount'] = $tax->isZero() ? null : ($increases ? $tax->getAmount() : NumberHelper::negatePrecise($tax->getAmount()));
            $lines[] = $line;

            if ($isTaxLine || $tax->isZero()) {
                continue;
            }
            if ($taxCode->tax_account_id === null || ($taxCode->isSelfAssessed() && $taxCode->input_tax_account_id === null)) {
                throw $this->rowError($index, 'accounting.entry_form.error.tax_account_missing', ['code' => $taxCode->code]);
            }

            $taxLine = static fn (int $accountId, bool $debit): array => [
                'accounting_account_id' => $accountId,
                'debit' => $debit ? $tax->getAmount() : '0.00',
                'credit' => $debit ? '0.00' : $tax->getAmount(),
                'cost_center_id' => null,
                'memo' => $taxCode->code,
            ];
            if ($taxCode->isSelfAssessed()) {
                $lines[] = $taxLine((int) $taxCode->input_tax_account_id, $onDebit);
                $lines[] = $taxLine((int) $taxCode->tax_account_id, ! $onDebit);

                continue;
            }
            $lines[] = $taxLine((int) $taxCode->tax_account_id, $onDebit);
        }

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => (string) __('accounting.ledger.error.needs_two_lines'),
            ]);
        }

        return $lines;
    }

    /**
     * Formularzeilen zu gespeicherten Zeilen. Die automatisch erzeugte
     * Steuerzeile erscheint nicht: Sie entsteht beim Speichern neu.
     *
     * @param  array<int, array<string, mixed>>  $lines  Zeilenform des JournalService
     * @return list<array{line: int, account_id: int, debit: string, credit: string, tax_code_id: ?int, cost_center_id: ?int, memo: string}>
     */
    public function rows(array $lines): array {
        $lines = array_values($lines);
        $codes = AccountingTaxCode::query()
            ->whereIn('id', array_filter(array_column($lines, 'accounting_tax_code_id')))
            ->get()
            ->keyBy('id');
        $rows = [];
        $skip = [];

        foreach ($lines as $index => $line) {
            if (in_array($index, $skip, true)) {
                continue;
            }

            $code = $codes[(int) ($line['accounting_tax_code_id'] ?? 0)] ?? null;
            if ($code instanceof AccountingTaxCode) {
                $skip = [...$skip, ...$this->generatedTaxLines($lines, $index, $code)];
            }

            $rows[] = [
                'line' => $index,
                'account_id' => (int) $line['accounting_account_id'],
                'debit' => $this->shown($line['debit'] ?? null),
                'credit' => $this->shown($line['credit'] ?? null),
                'tax_code_id' => $code?->id,
                'cost_center_id' => isset($line['cost_center_id']) ? (int) $line['cost_center_id'] : null,
                'memo' => (string) ($line['memo'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Zeilen einer Buchung in der Zeilenform des JournalService.
     *
     * @return list<array<string, mixed>>
     */
    public function lineData(AccountingEntry $entry): array {
        return array_values($entry->lines->map(fn (AccountingEntryLine $line): array => [
            'accounting_account_id' => (int) $line->accounting_account_id,
            'debit' => $line->debit?->getAmount() ?? '0.00',
            'credit' => $line->credit?->getAmount() ?? '0.00',
            'accounting_tax_code_id' => $line->accounting_tax_code_id,
            'tax_amount' => $line->tax_amount?->getAmount(),
            'counterparty_type' => $line->counterparty_type,
            'counterparty_id' => $line->counterparty_id,
            'project_id' => $line->project_id,
            'asset_id' => $line->asset_id,
            'cost_center_id' => $line->cost_center_id,
            'cost_group' => $line->cost_group,
            'memo' => $line->memo,
        ])->all());
    }

    /**
     * Gegenpartei der Zeilen als Auswahlwert (`customer:<sqid>`), sonst null.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function counterpartyValue(array $lines): ?string {
        foreach ($lines as $line) {
            $class = MorphMap::classFor((string) ($line['counterparty_type'] ?? ''));
            $prefix = array_search($class, self::PARTIES, true);
            if (is_string($prefix) && $class !== null && ($line['counterparty_id'] ?? null) !== null) {
                return $prefix . ':' . Sqid::encode($class, (int) $line['counterparty_id']);
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?int} Morph-Alias und ID
     *
     * @throws ValidationException
     */
    private function resolveCounterparty(Organization $organization, ?string $value): array {
        $value = trim((string) $value);
        if ($value === '') {
            return [null, null];
        }

        [$prefix, $key] = array_pad(explode(':', $value, 2), 2, '');
        $class = self::PARTIES[$prefix] ?? null;
        $id = $class === null ? null : Sqid::decode($class, $key);
        if ($class === null || $id === null || $class::query()->where('organization_id', $organization->id)->whereKey($id)->doesntExist()) {
            throw ValidationException::withMessages([
                'counterparty' => (string) __('accounting.entry_form.error.unknown_counterparty'),
            ]);
        }

        return [MorphMap::alias($class), $id];
    }

    /** @throws ValidationException */
    private function taxCode(Organization $organization, CarbonImmutable $bookedOn, string $key, int $index): ?AccountingTaxCode {
        if (trim($key) === '') {
            return null;
        }

        $code = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->whereKey(Sqid::decodeOrNumeric(AccountingTaxCode::class, trim($key)))
            ->first();
        if (! $code instanceof AccountingTaxCode) {
            throw $this->rowError($index, 'accounting.entry_form.error.unknown_tax_code');
        }

        // Eine Folgefassung desselben Kennzeichens gilt ab ihrem Stichtag — gebucht wird mit der Fassung des Buchungstags.
        $valid = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->where('code', $code->code)
            ->validOn($bookedOn)
            ->orderByDesc('valid_from')
            ->first();
        if (! $valid instanceof AccountingTaxCode) {
            throw $this->rowError($index, 'accounting.entry_form.error.tax_code_not_valid', [
                'code' => $code->code,
                'date' => $bookedOn->format(\App\Support\Formats::date()),
            ]);
        }

        return $valid;
    }

    /**
     * Indizes der beim Speichern erzeugten Steuerzeilen hinter einer Nettozeile:
     * eine auf dem Steuerkonto, bei Eigenberechnung Vorsteuer und Umsatzsteuer.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<int>
     */
    private function generatedTaxLines(array $lines, int $index, AccountingTaxCode $code): array {
        $base = $lines[$index];
        if ($code->tax_account_id === null || $code->isTaxAccount((int) $base['accounting_account_id']) || ($base['tax_amount'] ?? null) === null) {
            return [];
        }

        $tax = NumberHelper::absPrecise(Decimal::of((string) $base['tax_amount'], 2)->getValue());
        $side = Decimal::of((string) ($base['debit'] ?? '0'), 2)->isZero() ? 'credit' : 'debit';
        $other = $side === 'debit' ? 'credit' : 'debit';
        $expected = $code->isSelfAssessed()
            ? [[(int) $code->input_tax_account_id, $side], [(int) $code->tax_account_id, $other]]
            : [[(int) $code->tax_account_id, $side]];

        $found = [];
        foreach ($expected as $offset => [$accountId, $amountSide]) {
            $next = $lines[$index + 1 + $offset] ?? null;
            if (! is_array($next)
                || (int) $next['accounting_account_id'] !== $accountId
                || ($next['accounting_tax_code_id'] ?? null) !== null
                || NumberHelper::comparePrecise(Decimal::of((string) ($next[$amountSide] ?? '0'), 2)->getValue(), $tax, 2) !== 0) {
                return [];
            }
            $found[] = $index + 1 + $offset;
        }

        return $found;
    }

    private function amount(mixed $raw): ?string {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $value = Decimal::of($raw, 2);

        return $value->isZero() ? null : $value->getValue();
    }

    private function shown(mixed $raw): string {
        $value = Decimal::of((string) ($raw ?? '0'), 2);

        return $value->isZero() ? '' : $value->getValue();
    }

    /** @param array<string, string> $replace */
    private function rowError(int $index, string $key, array $replace = []): ValidationException {
        return ValidationException::withMessages([
            'rows.' . $index . '.account' => (string) __('accounting.entry_form.error.row', ['row' => $index + 1]) . ' ' . (string) __($key, $replace),
        ]);
    }
}
