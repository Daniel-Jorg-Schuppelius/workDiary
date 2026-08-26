<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LedgerOpenItemsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\Accounting\AccountingOpenItem;
use App\Models\Organization;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;

/** Offene Posten aus den Festbuchungen mit Ausgleichsstand. */
class LedgerOpenItemsSection extends AbstractGdpduSection {
    public function key(): string {
        return 'ledger_open_items';
    }

    public function definition(): array {
        return [
            'file' => 'offeneposten.csv',
            'name' => 'Offene Posten',
            'description' => 'Forderungen und Verbindlichkeiten aus den Festbuchungen mit Ausgleichsstand.',
            'columns' => [
                ['name' => 'Beleg', 'type' => 'alpha'],
                ['name' => 'Richtung', 'type' => 'alpha'],
                ['name' => 'Konto', 'type' => 'alpha'],
                ['name' => 'Belegdatum', 'type' => 'date'],
                ['name' => 'Faelligkeit', 'type' => 'date'],
                ['name' => 'Waehrung', 'type' => 'alpha'],
                ['name' => 'Ursprungsbetrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Offener_Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Status', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('document_date', DateRange::days($from, $to))
            ->with('account')
            ->orderBy('id')
            ->lazy() as $item) {
            yield [
                $this->str($item->document_reference),
                $this->str($item->direction->value),
                $this->str($item->account?->number),
                $this->date($item->document_date),
                $this->date($item->due_date),
                $this->str($item->currency->value),
                $this->num((float) ($item->original_amount?->getAmount() ?? '0.00'), 2),
                $this->num((float) ($item->open_amount?->getAmount() ?? '0.00'), 2),
                $this->str($item->status->value),
            ];
        }
    }
}
