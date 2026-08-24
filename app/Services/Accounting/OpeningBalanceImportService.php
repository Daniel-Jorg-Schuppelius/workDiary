<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpeningBalanceImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\{AccountingAccount, AccountingEntry};
use App\Models\{Organization, User};
use App\Support\Toolkit\CsvFacade;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Validation\ValidationException;

/**
 * Startsalden-Übernahme (Feature 125, MVP-677).
 *
 * MVP-Standard ist **Startsaldo + offene Posten + Stichtag**, nicht der
 * Vollimport eines alten Hauptbuchs (Risiko 4 des Feature-Dokuments): Ein
 * importiertes Journal aus einem fremden System ließe sich nachträglich nicht
 * mehr gegen seine Belege prüfen.
 *
 * Jeder Import läuft zuerst als Probelauf. Erst wenn die Salden aufgehen,
 * entsteht die Eröffnungsbuchung — und die ist eine gewöhnliche Festbuchung
 * mit allen Guards.
 */
class OpeningBalanceImportService {
    /** Erwartete CSV-Spalten. */
    public const COLUMNS = ['account', 'debit', 'credit'];

    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountingEventRecorder $events,
    ) {}

    /**
     * Probelauf: prüft Konten und Ausgleich, ohne zu buchen.
     *
     * @return array{lines: list<array<string, mixed>>, debit: string, credit: string, balanced: bool, errors: list<string>}
     */
    public function dryRun(Organization $organization, string $absolutePath): array {
        $lines = [];
        $errors = [];
        // Money normalisiert die CSV-Schreibweise (Komma, Tausenderpunkt) und
        // rundet kaufmännisch — ohne Float-Zwischenschritt (Vollscan 2026-08-23, C1).
        $currency = $this->journal->baseCurrency($organization);
        $debit = Money::zero($currency);
        $credit = Money::zero($currency);

        foreach (CsvFacade::streamAssoc($absolutePath) as $lineNumber => $row) {
            $number = trim((string) ($row['account'] ?? ''));
            $rowDebit = Money::of((string) ($row['debit'] ?? '0'), $currency);
            $rowCredit = Money::of((string) ($row['credit'] ?? '0'), $currency);

            if ($number === '') {
                $errors[] = (string) __('accounting.opening.error.missing_account', ['line' => (string) $lineNumber]);

                continue;
            }

            $account = AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->where('number', $number)
                ->first();

            if (! $account instanceof AccountingAccount) {
                $errors[] = (string) __('accounting.opening.error.unknown_account', ['account' => $number, 'line' => (string) $lineNumber]);

                continue;
            }

            if ($rowDebit->isPositive() && $rowCredit->isPositive()) {
                $errors[] = (string) __('accounting.opening.error.both_sides', ['line' => (string) $lineNumber]);

                continue;
            }

            $lines[] = [
                'accounting_account_id' => $account->id,
                'account' => $account,
                'debit' => $rowDebit->getAmount(),
                'credit' => $rowCredit->getAmount(),
            ];

            $debit = $debit->plus($rowDebit);
            $credit = $credit->plus($rowCredit);
        }

        return [
            'lines' => $lines,
            'debit' => $debit->getAmount(),
            'credit' => $credit->getAmount(),
            'balanced' => $debit->equals($credit) && $debit->isPositive(),
            'errors' => $errors,
        ];
    }

    /**
     * Import ausführen — nur nach fehlerfreiem, ausgeglichenem Probelauf.
     *
     * @throws ValidationException
     */
    public function import(Organization $organization, string $absolutePath, User $actor): AccountingEntry {
        $result = $this->dryRun($organization, $absolutePath);

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['file' => $result['errors']]);
        }

        if (! $result['balanced']) {
            throw ValidationException::withMessages([
                'file' => (string) __('accounting.opening.error.unbalanced', [
                    'debit' => $result['debit'],
                    'credit' => $result['credit'],
                ]),
            ]);
        }

        $entry = $this->journal->openingBalance(
            $organization,
            array_map(static fn (array $line): array => [
                'accounting_account_id' => $line['accounting_account_id'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ], $result['lines']),
            $actor,
        );

        // Der Migrationsnachweis gehört in die Kette, nicht in ein Logfile:
        // Er erklärt später, woher die Anfangssalden stammen.
        $this->events->record($organization, 'accounting.opening_balance_imported', [
            'journal_no' => $entry->journal_no,
            'lines' => count($result['lines']),
            'debit' => $result['debit'],
            'credit' => $result['credit'],
        ], $entry, $actor);

        return $entry;
    }
}
