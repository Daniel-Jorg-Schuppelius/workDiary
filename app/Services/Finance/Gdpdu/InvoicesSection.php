<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{Invoice, Organization};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** Ausgangsrechnungen des Prüfungszeitraums (nach Rechnungsdatum). */
class InvoicesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'invoices';
    }

    public function definition(): array {
        return [
            'file' => 'rechnungen.csv',
            'name' => 'Ausgangsrechnungen',
            'description' => 'Ausgangsrechnungen und Gutschriften des Prüfungszeitraums (nach Rechnungsdatum).',
            'columns' => [
                ['name' => 'Rechnungsnummer', 'type' => 'alpha'],
                ['name' => 'Typ', 'type' => 'alpha'],
                ['name' => 'Status', 'type' => 'alpha'],
                ['name' => 'Rechnungsdatum', 'type' => 'date'],
                ['name' => 'Faelligkeit', 'type' => 'date'],
                ['name' => 'Bezahlt_am', 'type' => 'date'],
                ['name' => 'Kundennummer', 'type' => 'alpha'],
                ['name' => 'Kunde', 'type' => 'alpha'],
                ['name' => 'Waehrung', 'type' => 'alpha'],
                ['name' => 'Netto', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'USt_Satz', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'USt_Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Brutto', 'type' => 'numeric', 'accuracy' => 2],
                // Vollaudit 2026-07 (H11): Formatmetadaten der E-Rechnung
                // (066→063) — letzter Versand: Format/Kanal/Zeitpunkt/Hash.
                ['name' => 'ERechnung_Format', 'type' => 'alpha'],
                ['name' => 'Versandkanal', 'type' => 'alpha'],
                ['name' => 'Versandt_am', 'type' => 'alpha'],
                ['name' => 'Datei_SHA256', 'type' => 'alpha'],
            ],
        ];
    }

    /**
     * Gemeinsame Basisabfrage der Rechnungs-Trias: auch Nummern-/Debitorenquelle
     * für Positionen ({@see InvoiceItemsSection}) und Debitoren
     * ({@see CustomersSection}), damit die drei Bereiche zusammenpassen.
     *
     * @return Builder<Invoice>
     */
    public static function invoicesInPeriod(Organization $organization, CarbonInterface $from, CarbonInterface $to): Builder {
        return Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('issued_on', DateRange::days($from, $to))
            ->orderBy('id');
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        $invoices = self::invoicesInPeriod($organization, $from, $to)
            ->with('customer:id,number,name,company,vat_id,tax_number,address_street,address_zip,address_city,country,email')
            ->with('dispatches')
            ->lazy();

        foreach ($invoices as $inv) {
            // Vollaudit 2026-07 (H11): Formatmetadaten des letzten Versands.
            $dispatch = $inv->dispatches->sortByDesc('id')->first();
            yield [
                $this->str($inv->number),
                $this->str($inv->type),
                $this->str($inv->status),
                $this->date($inv->issued_on),
                $this->date($inv->due_on),
                $this->date($inv->paid_on),
                $this->str($inv->customer->number),
                $this->str($inv->customer->name),
                $this->str($inv->currency->value),
                $this->num($inv->subtotal?->toFloat(), 2),
                $this->num($inv->tax_rate !== null ? (float) $inv->tax_rate->getNumericValue() : null, 2),
                $this->num($inv->tax_amount?->toFloat(), 2),
                $this->num($inv->total?->toFloat(), 2),
                $this->str($dispatch?->format),
                $this->str($dispatch?->channel),
                $this->dateTime($dispatch?->created_at),
                $this->str($dispatch?->sha256),
            ];
        }
    }
}
