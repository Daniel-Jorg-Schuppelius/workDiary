<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LedgerDatevExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\AccountingEntryStatus;
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEntryLine, AccountingOpenItem, AccountingTaxCode};
use App\Models\Platform\Organization;
use App\Services\Billing\FinancialFormatsSupport;
use App\Services\Finance\Datev\{DatevBookingAdapter, DatevBookingConfig, DatevBookingFieldResolver};
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Decimal, Money};

/**
 * DATEV-Übergabe aus den lokalen Festbuchungen (Feature 125, MVP-677).
 *
 * Bewusst **aus dem Journal**, nicht erneut aus den Belegen: Eine zweite
 * Ableitung könnte abweichen, und dann gäbe es zwei Wahrheiten über denselben
 * Zeitraum. Der bestehende {@see \App\Services\Finance\DatevBookingService}
 * bleibt für Organisationen ohne lokales Hauptbuch unverändert zuständig.
 *
 * Der Export ist reproduzierbar: gleiche Buchungen, gleicher Zeitraum,
 * gleiche Datei.
 *
 * Der EXTF-Stapel bildet je Buchung Buchungssätze gegen eine Ankerzeile
 * (Personen- oder Geldkonto), damit DATEV dieselben Beträge und Steuern
 * rechnet wie das Journal (MVP-1622): Eine Nettozeile mit Kennzeichen geht
 * brutto mit dem BU-Schlüssel des Kennzeichens hinaus, ihre Steuerzeilen
 * entfallen; auf einem Automatikkonto (Vorgabe-Kennzeichen am Konto) rechnet
 * DATEV selbst, dort steht kein Schlüssel. Passt die Automatik nicht, hebt
 * BU-Schlüssel 40 sie auf und die Steuer bleibt eine eigene Zeile.
 */
class LedgerDatevExportService {
    /** Berichtigungsschlüssel 4 „Aufhebung der Automatik“ ohne Steuerschlüssel. */
    public const string SUSPEND_AUTOMATIC = '40';

    /** Spaltenköpfe der Übergabedatei. */
    public const array HEADERS = [
        'Umsatz', 'Soll_Haben', 'Konto', 'Gegenkonto', 'Belegdatum',
        'Belegfeld', 'Buchungstext', 'Journalnummer', 'Steuerkennzeichen',
    ];

    /**
     * Buchungszeilen des Zeitraums als CSV.
     *
     * @return array{csv: string, rows: int, debit: string, credit: string}
     */
    public function build(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $entries = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value])
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->with(['lines.account', 'lines.taxCode'])
            ->orderBy('journal_no')
            ->get();

        $rows = [];
        // Kontrollsummen exakt über Money — die Zeilenbeträge sind es bereits
        // (MoneyCast), eine Float-Summe darüber wäre ein Rückschritt (C1).
        $currency = $entries->first()->currency ?? CurrencyCode::Euro;
        $debit = Money::zero($currency);
        $credit = Money::zero($currency);

        foreach ($entries as $entry) {
            // Gegenkonto ist die jeweils andere Seite; bei mehr als zwei Zeilen
            // bleibt es leer, statt eine Zuordnung zu erfinden.
            $counter = $entry->lines->count() === 2 ? $entry->lines : null;

            foreach ($entry->lines as $index => $line) {
                $isDebit = $line->debit?->isPositive() ?? false;
                $amount = ($isDebit ? $line->debit : $line->credit) ?? Money::zero($line->currency);

                // Assoziativ nach Spaltenkopf: CsvFacade::buildCsv ordnet die
                // Zellen über die Header-Namen zu, nicht über die Position.
                $rows[] = [
                    'Umsatz' => $amount->getAmount(),
                    'Soll_Haben' => $isDebit ? 'S' : 'H',
                    'Konto' => $this->accountNumber($line),
                    'Gegenkonto' => $this->counterAccount($counter, $index),
                    'Belegdatum' => $entry->booked_on->format('d.m.Y'),
                    'Belegfeld' => (string) ($entry->document_reference ?? ''),
                    'Buchungstext' => (string) $entry->memo,
                    'Journalnummer' => (string) ($entry->journal_no ?? ''),
                    'Steuerkennzeichen' => $line->taxCode instanceof \App\Models\Accounting\AccountingTaxCode ? (string) $line->taxCode->code : '',
                ];

                $isDebit
                    ? $debit = $debit->plus($amount)
                    : $credit = $credit->plus($amount);
            }
        }

        return [
            // DATEV-ASCII ist ein Maschinenformat: die Datei wird eingelesen,
            // nicht in Excel geöffnet, und der Nachweis hängt an den Bytes.
            // Deshalb ohne Formel-Guard (csvformula-1).
            'csv' => CsvFacade::buildCsv(self::HEADERS, $rows, guardFormulas: false),
            'rows' => count($rows),
            'debit' => $debit->getAmount(),
            'credit' => $credit->getAmount(),
        ];
    }

    /**
     * EXTF-V700-Buchungsstapel aus denselben Journalzeilen (Vollscan
     * 2026-08-23, C2): die 9-Spalten-CSV aus {@see build()} ist von DATEV
     * nicht importierbar — sie bleibt als Zweitformat für Installationen ohne
     * das financial-formats-Paket. Voraussetzung hier:
     * {@see FinancialFormatsSupport::isAvailable()}.
     *
     * Kanzlei-Felder (Feature 135): Fälligkeit aus dem offenen Posten der
     * Buchung (Ledger-Wahrheit), sonst aus dem Quellbeleg; KOST1 und Skonto
     * über {@see DatevBookingFieldResolver} aus dem Quellbeleg.
     *
     * @return array{content: string, filename: string, rows: int, debit: string, credit: string}
     */
    public function buildExtf(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        FinancialFormatsSupport::ensureAvailable();

        $entries = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value])
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->with(['lines.account.defaultTaxCode', 'lines.taxCode', 'lines.costCenter', 'source'])
            ->orderBy('journal_no')
            ->get();

        $dueDates = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->whereIn('accounting_entry_id', $entries->pluck('id')->all())
            ->whereNotNull('due_date')
            ->orderBy('id')
            ->get(['accounting_entry_id', 'due_date'])
            ->keyBy('accounting_entry_id');
        $fields = new DatevBookingFieldResolver((int) $organization->id);

        $rows = [];
        $currency = $entries->first()->currency ?? CurrencyCode::Euro;
        $debit = Money::zero($currency);
        $credit = Money::zero($currency);

        foreach ($entries as $entry) {
            $source = $entry->source;
            $extra = $fields->forSource($source instanceof \Illuminate\Database\Eloquent\Model ? $source : null);
            $openItem = $dueDates->get((int) $entry->id);
            $dueOn = $openItem instanceof AccountingOpenItem && $openItem->due_date !== null
                ? new \DateTimeImmutable($openItem->due_date->toDateString())
                : $extra['due_on'];

            foreach ($entry->lines as $line) {
                $isDebit = $line->debit?->isPositive() ?? false;
                $amount = ($isDebit ? $line->debit : $line->credit) ?? Money::zero($line->currency);
                $isDebit
                    ? $debit = $debit->plus($amount)
                    : $credit = $credit->plus($amount);
            }

            foreach ($this->batchRows($entry) as $batchRow) {
                $rows[] = [
                    'amount' => $batchRow['amount'],
                    'soll_haben' => $batchRow['side'],
                    'account' => $batchRow['account'],
                    'contra_account' => $batchRow['contra'],
                    'tax_key' => $batchRow['tax_key'],
                    'date' => new \DateTimeImmutable($entry->booked_on->toDateString()),
                    'document_ref' => (string) ($entry->document_reference ?? ($entry->journal_no !== null ? 'J' . $entry->journal_no : '')),
                    'text' => (string) $entry->memo,
                    // Festbuchungen sind per Definition festgeschrieben; Stornos
                    // stehen als eigene Buchung im Journal, keine Generalumkehr.
                    // Ledger-Wahrheit zuerst: die Zeile trägt ihre Kostenstelle
                    // (Feature 142), erst ohne sie greift die Beleg-Regel.
                    'cost_center1' => $batchRow['cost_center'] ?? $extra['cost_center1'],
                    'due_on' => $dueOn,
                    'discount_amount' => $extra['discount_amount'],
                    'discount_type' => $extra['discount_type'],
                ];
            }
        }

        $config = DatevBookingConfig::forOrganization($organization);
        if ($config->advisorNumber < 1000 || $config->clientNumber < 1) {
            // Klare Ansage statt Toolkit-Pattern-Exception: ohne Berater-/
            // Mandantennummer ist kein importierbarer Stapel möglich.
            throw new \RuntimeException((string) __('Für die DATEV-Übergabe fehlen Beraternummer/Mandantennummer — in den DATEV-Einstellungen der Organisation pflegen.'));
        }
        $content = app(DatevBookingAdapter::class)->generateBookings(
            new \DateTimeImmutable($from->toDateString()),
            new \DateTimeImmutable($to->toDateString()),
            true,
            sprintf('WorkDiary Journal %s', $from->format('Y-m')),
            $config,
            $rows,
        );

        return [
            'content' => $content,
            'filename' => sprintf('EXTF_Buchungsstapel_%s_%s.csv', $from->format('Ymd'), $to->format('Ymd')),
            'rows' => count($rows),
            'debit' => $debit->getAmount(),
            'credit' => $credit->getAmount(),
        ];
    }

    /**
     * Buchungssätze einer Buchung für den EXTF-Stapel.
     *
     * @return list<array{amount: numeric-string, side: string, account: string, contra: string, tax_key: ?string, cost_center: ?string}>
     */
    public function batchRows(AccountingEntry $entry): array {
        $lines = array_values($entry->lines->all());
        $debitSide = [];
        $remaining = [];
        $keys = [];
        $handled = [];
        foreach ($lines as $i => $line) {
            $debitSide[$i] = $line->debit?->isPositive() ?? false;
            $remaining[$i] = (($debitSide[$i] ? $line->debit : $line->credit) ?? Money::zero($line->currency))->abs();
            $keys[$i] = null;
        }

        foreach ($lines as $i => $line) {
            $code = $line->taxCode;
            if (! $code instanceof AccountingTaxCode || $code->isTaxAccount((int) $line->accounting_account_id)) {
                continue;
            }
            $handled[$i] = true;
            $automatic = $line->account?->defaultTaxCode;
            $tax = ($line->tax_amount ?? Money::zero($line->currency))->abs();

            if ($tax->isZero()) {
                // Umsatz ohne Steuer: die Kennziffer über den Schlüssel, auf dem Automatikkonto über das Konto.
                $keys[$i] = $automatic instanceof AccountingTaxCode
                    ? ($this->isTaxed($automatic) ? self::SUSPEND_AUTOMATIC : null)
                    : $this->key($code);

                continue;
            }
            $sameAutomatic = $automatic instanceof AccountingTaxCode && $automatic->code === $code->code;
            if (($automatic instanceof AccountingTaxCode && ! $sameAutomatic) || (! $sameAutomatic && $this->key($code) === null)) {
                $keys[$i] = $automatic instanceof AccountingTaxCode ? self::SUSPEND_AUTOMATIC : null;

                continue;
            }

            $targets = $code->isSelfAssessed()
                ? [[(int) $code->input_tax_account_id, $debitSide[$i]], [(int) $code->tax_account_id, ! $debitSide[$i]]]
                : [[(int) $code->tax_account_id, $debitSide[$i]]];
            $taxLines = $this->taxLines($lines, $debitSide, $remaining, $i, $targets, $tax);
            if ($taxLines === null) {
                $keys[$i] = $sameAutomatic ? self::SUSPEND_AUTOMATIC : null;

                continue;
            }
            foreach ($taxLines as $j) {
                $remaining[$j] = $remaining[$j]->minus($tax);
            }
            // DATEV rechnet die Steuer aus dem Bruttobetrag heraus; bei Eigenberechnung bleibt er netto.
            if (! $code->isSelfAssessed()) {
                $remaining[$i] = $remaining[$i]->plus($tax);
            }
            $keys[$i] = $sameAutomatic ? null : $this->key($code);
        }

        // Ohne Kennzeichen auf einem Automatikkonto mit Steuer: DATEV darf nichts herausrechnen.
        foreach ($lines as $i => $line) {
            $automatic = $line->account?->defaultTaxCode;
            if (! isset($handled[$i]) && $automatic instanceof AccountingTaxCode && $this->isTaxed($automatic)) {
                $keys[$i] = self::SUSPEND_AUTOMATIC;
            }
        }

        $live = array_keys(array_filter($remaining, static fn (Money $amount): bool => ! $amount->isZero()));
        if (count($live) < 2) {
            return [];
        }
        $anchor = $this->anchor($lines, $live, $debitSide, $keys, $remaining);

        $rows = [];
        foreach ($live as $i) {
            if ($i === $anchor) {
                continue;
            }
            $rows[] = [
                'amount' => $remaining[$i]->getAmount(),
                'side' => $debitSide[$i] ? 'S' : 'H',
                'account' => $this->accountNumber($lines[$i]),
                'contra' => $this->accountNumber($lines[$anchor]),
                'tax_key' => $keys[$i],
                'cost_center' => $lines[$i]->costCenter?->code,
            ];
        }

        return $rows;
    }

    /**
     * Steuerzeilen eines Kennzeichens auf der erwarteten Seite, die den Betrag
     * noch tragen; null, wenn eine fehlt — dann bleiben sie eigene Sätze.
     *
     * @param  list<AccountingEntryLine>  $lines
     * @param  array<int, bool>  $debitSide
     * @param  array<int, Money>  $remaining
     * @param  list<array{0: int, 1: bool}>  $targets  Konto und Soll-Seite
     * @return list<int>|null
     */
    private function taxLines(array $lines, array $debitSide, array $remaining, int $net, array $targets, Money $tax): ?array {
        $found = [];
        foreach ($targets as [$accountId, $onDebit]) {
            $match = null;
            foreach ($lines as $j => $line) {
                if ($j !== $net && ! in_array($j, $found, true)
                    && (int) $line->accounting_account_id === $accountId
                    && $debitSide[$j] === $onDebit
                    && ! $remaining[$j]->lessThan($tax)) {
                    $match = $j;

                    break;
                }
            }
            if ($match === null) {
                return null;
            }
            $found[] = $match;
        }

        return $found;
    }

    /**
     * Gegenkonto aller Sätze einer Buchung: eine Zeile ohne eigenen Schlüssel,
     * bevorzugt die einzige ihrer Seite, dann ein Personen- oder Geldkonto,
     * dann der größte Betrag.
     *
     * @param  list<AccountingEntryLine>  $lines
     * @param  list<int>  $live
     * @param  array<int, bool>  $debitSide
     * @param  array<int, ?string>  $keys
     * @param  array<int, Money>  $remaining
     */
    private function anchor(array $lines, array $live, array $debitSide, array $keys, array $remaining): int {
        $debits = count(array_filter($live, static fn (int $i): bool => $debitSide[$i]));
        $score = function (int $i) use ($lines, $live, $debitSide, $keys, $debits): int {
            $account = $lines[$i]->account;
            $alone = ($debitSide[$i] ? $debits : count($live) - $debits) === 1;

            return ($keys[$i] === null ? 4 : 0)
                + ($alone ? 2 : 0)
                + ($account instanceof AccountingAccount && ($account->is_open_item || $account->is_bank || $account->is_cash || $account->is_clearing) ? 1 : 0);
        };
        $best = $live[0];
        foreach ($live as $i) {
            if ($score($i) > $score($best) || ($score($i) === $score($best) && $remaining[$i]->greaterThan($remaining[$best]))) {
                $best = $i;
            }
        }

        return $best;
    }

    private function isTaxed(AccountingTaxCode $code): bool {
        return ! Decimal::of((string) $code->rate, 2)->isZero();
    }

    private function key(AccountingTaxCode $code): ?string {
        $key = trim((string) $code->datev_tax_key);

        return $key === '' ? null : $key;
    }

    /** DATEV-Konto, falls gepflegt — sonst die eigene Kontonummer. */
    private function accountNumber(AccountingEntryLine $line): string {
        $account = $line->account;
        if (! $account instanceof \App\Models\Accounting\AccountingAccount) {
            return '';
        }

        return (string) ($account->datev_account !== null && $account->datev_account !== ''
            ? $account->datev_account
            : $account->number);
    }

    /**
     * Gegenkonto nur bei genau zwei Zeilen — bei mehr bleibt es leer, statt
     * eine Zuordnung zu erfinden.
     *
     * @param  \Illuminate\Support\Collection<int, AccountingEntryLine>|null  $counter
     */
    private function counterAccount(?\Illuminate\Support\Collection $counter, int $index): string {
        if ($counter === null) {
            return '';
        }

        $other = $counter[$index === 0 ? 1 : 0] ?? null;

        return $other instanceof AccountingEntryLine ? $this->accountNumber($other) : '';
    }
}
