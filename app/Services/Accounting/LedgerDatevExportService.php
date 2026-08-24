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
use App\Models\Accounting\{AccountingEntry, AccountingEntryLine};
use App\Models\Organization;
use App\Services\Finance\Datev\{DatevBookingAdapter, DatevBookingConfig};
use App\Services\Finance\FinancialFormatsSupport;
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

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
 */
class LedgerDatevExportService {
    /** Spaltenköpfe der Übergabedatei. */
    public const HEADERS = [
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
            'csv' => CsvFacade::buildCsv(self::HEADERS, $rows),
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
     * @return array{content: string, filename: string, rows: int, debit: string, credit: string}
     */
    public function buildExtf(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        FinancialFormatsSupport::ensureAvailable();

        $entries = AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value])
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->with(['lines.account', 'lines.taxCode'])
            ->orderBy('journal_no')
            ->get();

        $rows = [];
        $currency = $entries->first()->currency ?? CurrencyCode::Euro;
        $debit = Money::zero($currency);
        $credit = Money::zero($currency);

        foreach ($entries as $entry) {
            $counter = $entry->lines->count() === 2 ? $entry->lines : null;
            foreach ($entry->lines as $index => $line) {
                $isDebit = $line->debit?->isPositive() ?? false;
                $amount = ($isDebit ? $line->debit : $line->credit) ?? Money::zero($line->currency);
                $rows[] = [
                    'amount' => $amount->getAmount(),
                    'soll_haben' => $isDebit ? 'S' : 'H',
                    'account' => $this->accountNumber($line),
                    'contra_account' => $this->counterAccount($counter, $index),
                    'tax_key' => $line->taxCode instanceof \App\Models\Accounting\AccountingTaxCode ? (string) $line->taxCode->code : null,
                    'date' => new \DateTimeImmutable($entry->booked_on->toDateString()),
                    'document_ref' => (string) ($entry->document_reference ?? ($entry->journal_no !== null ? 'J' . $entry->journal_no : '')),
                    'text' => (string) $entry->memo,
                    // Festbuchungen sind per Definition festgeschrieben; Stornos
                    // stehen als eigene Buchung im Journal, keine Generalumkehr.
                ];

                $isDebit
                    ? $debit = $debit->plus($amount)
                    : $credit = $credit->plus($amount);
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
