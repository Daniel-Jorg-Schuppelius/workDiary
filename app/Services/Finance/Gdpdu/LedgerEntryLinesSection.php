<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LedgerEntryLinesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\Organization;
use Carbon\CarbonInterface;

/**
 * Zeilen der festgeschriebenen Buchungen — gleiche Quelle wie das Journal
 * ({@see LedgerEntriesSection::postedEntries()}), damit beide zusammenpassen.
 */
class LedgerEntryLinesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'ledger_entry_lines';
    }

    public function definition(): array {
        return [
            'file' => 'journalzeilen.csv',
            'name' => 'Buchungszeilen',
            'description' => 'Zeilen der festgeschriebenen Buchungen mit Konto, Soll/Haben und Steuerkennzeichen.',
            'columns' => [
                ['name' => 'Journalnummer', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Position', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Konto', 'type' => 'alpha'],
                ['name' => 'Soll', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Haben', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Steuerkennzeichen', 'type' => 'alpha'],
                ['name' => 'Steuerbetrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Buchungstext', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (LedgerEntriesSection::postedEntries($organization, $from, $to) as $entry) {
            foreach ($entry->lines as $line) {
                yield [
                    $this->num($entry->journal_no, 0),
                    $this->num($line->line_no, 0),
                    $this->str($line->account?->number),
                    $this->num((float) ($line->debit?->getAmount() ?? '0.00'), 2),
                    $this->num((float) ($line->credit?->getAmount() ?? '0.00'), 2),
                    $this->str($line->taxCode?->code),
                    $this->num((float) ($line->tax_amount?->getAmount() ?? '0.00'), 2),
                    $this->str($line->memo),
                ];
            }
        }
    }
}
