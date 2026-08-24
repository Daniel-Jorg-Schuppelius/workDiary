<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashEntriesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{CashEntry, Organization};
use Carbon\CarbonInterface;

/**
 * Kassenbuch-Einträge (MVP-414) des Prüfungszeitraums nach Buchungsdatum —
 * inkl. Hash (Kettenglied, prüfbar via audit:verify) und Storno-Verweis.
 */
class CashEntriesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'cash_entries';
    }

    public function definition(): array {
        return [
            'file' => 'kassenbuch.csv',
            'name' => 'Kassenbuch',
            'description' => 'Kassenbuch-Einträge des Prüfungszeitraums (MVP-414): append-only mit Hash-Kette, Korrekturen ausschließlich als Storno-Gegenbuchungen, Tagesabschlüsse mit Kassensturz.',
            'columns' => [
                ['name' => 'Kasse', 'type' => 'alpha'],
                ['name' => 'Belegnummer', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Datum', 'type' => 'date'],
                ['name' => 'Richtung', 'type' => 'alpha'],
                ['name' => 'Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'USt_Satz', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Zweck', 'type' => 'alpha'],
                ['name' => 'Gegenpartei', 'type' => 'alpha'],
                ['name' => 'Rechnungsnummer', 'type' => 'alpha'],
                ['name' => 'Storno_zu', 'type' => 'alpha'],
                ['name' => 'Erfasst_am', 'type' => 'alpha'],
                ['name' => 'Hash', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        CashEntry::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereBetween('booked_on', [$from->toDateString(), $to->toDateString()])
            ->with(['register:id,name', 'invoice:id,number', 'reversalOf:id,seq_no'])
            ->orderBy('cash_register_id')->orderBy('seq_no')
            ->get()
            ->each(function (CashEntry $entry) use (&$rows): void {
                $rows[] = [
                    $this->str($entry->register?->name),
                    $this->num($entry->seq_no, 0),
                    $this->date($entry->booked_on),
                    $entry->direction === CashEntry::DIRECTION_IN ? 'Einnahme' : 'Ausgabe',
                    $this->num($entry->amount?->toFloat(), 2),
                    $this->num($entry->tax_rate !== null ? (float) $entry->tax_rate->getNumericValue() : null, 2),
                    $this->str($entry->purpose),
                    $this->str($entry->counterparty),
                    $this->str($entry->invoice?->number),
                    $this->str($entry->reversalOf?->seq_no),
                    $this->dateTime($entry->created_at),
                    $this->str($entry->hash),
                ];
            });

        return $rows;
    }
}
