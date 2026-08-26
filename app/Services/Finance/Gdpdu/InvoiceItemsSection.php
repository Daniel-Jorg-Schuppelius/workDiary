<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceItemsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{InvoiceItem, Organization};
use Carbon\CarbonInterface;

/** Positionen der Ausgangsrechnungen des Prüfungszeitraums. */
class InvoiceItemsSection extends AbstractGdpduSection {
    public function key(): string {
        return 'invoice_items';
    }

    public function definition(): array {
        return [
            'file' => 'rechnungspositionen.csv',
            'name' => 'Rechnungspositionen',
            'description' => 'Positionen der Ausgangsrechnungen des Prüfungszeitraums.',
            'columns' => [
                ['name' => 'Rechnungsnummer', 'type' => 'alpha'],
                ['name' => 'Position', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Leistungsdatum', 'type' => 'date'],
                ['name' => 'Beschreibung', 'type' => 'alpha'],
                ['name' => 'Menge', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Einheit', 'type' => 'alpha'],
                ['name' => 'Einzelpreis', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Betrag', 'type' => 'numeric', 'accuracy' => 2],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        $invoices = InvoicesSection::invoicesInPeriod($organization, $from, $to)->get(['id', 'number']);
        $numberById = $invoices->pluck('number', 'id');

        foreach (InvoiceItem::query()
            ->whereIn('invoice_id', $invoices->modelKeys())
            ->orderBy('invoice_id')->orderBy('position')->orderBy('id')
            ->lazy() as $item) {
            yield [
                $this->str($numberById[$item->invoice_id] ?? null),
                $this->num($item->position, 0),
                $this->date($item->service_date),
                $this->str($item->description),
                $this->num($item->quantity, 2),
                $this->str($item->unit),
                $this->num($item->unit_price?->toFloat(), 2),
                $this->num($item->amount?->toFloat(), 2),
            ];
        }
    }
}
