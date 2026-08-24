<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LedgerEntriesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\Accounting\AccountingEntry;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/** Festgeschriebene Buchungen des Prüfungszeitraums (nach Buchungsdatum). */
class LedgerEntriesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'ledger_entries';
    }

    public function definition(): array {
        return [
            'file' => 'journal.csv',
            'name' => 'Buchungsjournal',
            'description' => 'Festgeschriebene Buchungen des Prüfungszeitraums (nach Buchungsdatum).',
            'columns' => [
                ['name' => 'Journalnummer', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Buchungsdatum', 'type' => 'date'],
                ['name' => 'Belegdatum', 'type' => 'date'],
                ['name' => 'Status', 'type' => 'alpha'],
                ['name' => 'Buchungstext', 'type' => 'alpha'],
                ['name' => 'Beleg', 'type' => 'alpha'],
                ['name' => 'Waehrung', 'type' => 'alpha'],
                ['name' => 'Soll_Summe', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Haben_Summe', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Quelle', 'type' => 'alpha'],
                ['name' => 'Regelversion', 'type' => 'alpha'],
                ['name' => 'Storniert_durch', 'type' => 'alpha'],
                ['name' => 'Festgeschrieben_am', 'type' => 'alpha'],
            ],
        ];
    }

    /**
     * Festgeschriebene Buchungen des Zeitraums — gemeinsame Quelle von
     * Journal- und Zeilen-Export ({@see LedgerEntryLinesSection}), damit
     * beide zwangsläufig zusammenpassen.
     *
     * @return Collection<int, AccountingEntry>
     */
    public static function postedEntries(Organization $organization, CarbonInterface $from, CarbonInterface $to): Collection {
        return AccountingEntry::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['posted', 'reversed'])
            ->whereDate('booked_on', '>=', $from->toDateString())
            ->whereDate('booked_on', '<=', $to->toDateString())
            ->with(['lines.account', 'lines.taxCode', 'reversedBy'])
            ->orderBy('journal_no')
            ->get();
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        return array_values(self::postedEntries($organization, $from, $to)
            ->map(fn ($entry): array => [
                $this->num($entry->journal_no, 0),
                $this->date($entry->booked_on),
                $this->date($entry->document_on),
                $this->str($entry->status->value),
                $this->str($entry->memo),
                $this->str($entry->document_reference),
                $this->str($entry->currency->value),
                $this->num((float) $entry->debitTotal()->getAmount(), 2),
                $this->num((float) $entry->creditTotal()->getAmount(), 2),
                $this->str($entry->source_key),
                $this->str($entry->rule_version),
                $this->str($entry->reversed_by_entry_id === null ? '' : (string) $entry->reversedBy?->journal_no),
                $this->dateTime($entry->posted_at),
            ])
            ->values()
            ->all());
    }
}
