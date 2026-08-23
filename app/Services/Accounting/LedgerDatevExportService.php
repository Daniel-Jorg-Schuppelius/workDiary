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
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;

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
        $debit = '0.00';
        $credit = '0.00';

        foreach ($entries as $entry) {
            // Gegenkonto ist die jeweils andere Seite; bei mehr als zwei Zeilen
            // bleibt es leer, statt eine Zuordnung zu erfinden.
            $counter = $entry->lines->count() === 2 ? $entry->lines : null;

            foreach ($entry->lines as $index => $line) {
                $isDebit = (float) ($line->debit?->getAmount() ?? '0.00') > 0.0;
                $amount = $isDebit ? ($line->debit?->getAmount() ?? '0.00') : ($line->credit?->getAmount() ?? '0.00');

                // Assoziativ nach Spaltenkopf: CsvFacade::buildCsv ordnet die
                // Zellen über die Header-Namen zu, nicht über die Position.
                $rows[] = [
                    'Umsatz' => $amount,
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
                    ? $debit = $this->add($debit, $amount)
                    : $credit = $this->add($credit, $amount);
            }
        }

        return [
            'csv' => CsvFacade::buildCsv(self::HEADERS, $rows),
            'rows' => count($rows),
            'debit' => $debit,
            'credit' => $credit,
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

    private function add(string $a, string $b): string {
        return number_format((float) $a + (float) $b, 2, '.', '');
    }
}
