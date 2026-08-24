<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomersSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{Customer, Organization};
use Carbon\CarbonInterface;

/**
 * Debitorenstammdaten — genau die im Zeitraum per Rechnung berührten Kunden
 * (Datenminimierung), Quelle: {@see InvoicesSection::invoicesInPeriod()}.
 */
class CustomersSection extends AbstractGdpduSection {
    public function key(): string {
        return 'customers';
    }

    public function definition(): array {
        return [
            'file' => 'debitoren.csv',
            'name' => 'Debitoren',
            'description' => 'Debitorenstammdaten der im Zeitraum berührten Kunden.',
            'columns' => [
                ['name' => 'Kundennummer', 'type' => 'alpha'],
                ['name' => 'Name', 'type' => 'alpha'],
                ['name' => 'Firma', 'type' => 'alpha'],
                ['name' => 'USt_IdNr', 'type' => 'alpha'],
                ['name' => 'Steuernummer', 'type' => 'alpha'],
                ['name' => 'Strasse', 'type' => 'alpha'],
                ['name' => 'PLZ', 'type' => 'alpha'],
                ['name' => 'Ort', 'type' => 'alpha'],
                ['name' => 'Land', 'type' => 'alpha'],
                ['name' => 'E-Mail', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $customerIds = InvoicesSection::invoicesInPeriod($organization, $from, $to)
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->all();

        $rows = [];
        Customer::query()
            ->whereIn('id', $customerIds)
            ->orderBy('number')
            ->get()
            ->each(function (Customer $c) use (&$rows): void {
                $rows[] = [
                    $this->str($c->number),
                    $this->str($c->name),
                    $this->str($c->company),
                    $this->str($c->vat_id),
                    $this->str($c->tax_number),
                    $this->str($c->address_street),
                    $this->str($c->address_zip),
                    $this->str($c->address_city),
                    $this->str($c->country),
                    $this->str($c->email),
                ];
            });

        return $rows;
    }
}
