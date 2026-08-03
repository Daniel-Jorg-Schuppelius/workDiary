<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{Customer, Supplier, TimeEntry};
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Translates workDiary domain models into Lexoffice JSON payloads.
 *
 * The payload structures follow the public Lexoffice REST API:
 *   https://developers.lexoffice.io/docs/
 *
 * They are intentionally produced as plain associative arrays so they can be
 * fed into Lexoffice entity classes via fromJson(json_encode($payload)) which
 * is the conventional construction pattern in the Lexoffice SDK.
 */
class LexofficeMapper {
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function customerToContactPayload(Customer $customer, array $defaults = []): array {
        return $this->contactPayload($customer, 'customer', $defaults);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function supplierToContactPayload(Supplier $supplier, array $defaults = []): array {
        return $this->contactPayload($supplier, 'vendor', $defaults);
    }

    /**
     * Baut den gemeinsamen Lexoffice-Contact-Payload für Kunden (role=customer)
     * und Lieferanten (role=vendor).
     *
     * @param  'customer'|'vendor'  $role
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function contactPayload(Customer|Supplier $contact, string $role, array $defaults = []): array {
        $isCompany = (bool) ($contact->company || $contact->vat_id);
        $country = $contact->country ?: ($defaults['country'] ?? 'DE');

        $billingAddress = [];
        $hasStructured = $contact->address_street || $contact->address_zip || $contact->address_city;
        if ($hasStructured) {
            $billingAddress = array_filter([
                'street' => $contact->address_street,
                'zip' => $contact->address_zip,
                'city' => $contact->address_city,
                'countryCode' => $country,
            ]);
        } elseif ($contact->address) {
            $billingAddress = [
                'street' => $contact->address,
                'countryCode' => $country,
            ];
        }

        $payload = [
            'version' => 0,
            'roles' => [
                $role => (object) [],
            ],
            'note' => $contact->comment ?: null,
        ];

        if ($isCompany) {
            $contactPersons = $this->buildContactPersons($contact);
            $payload['company'] = array_filter([
                'name' => $contact->company ?: $contact->name,
                'taxNumber' => $contact->tax_number ?: null,
                'vatRegistrationId' => $contact->vat_id ?: null,
                'contactPersons' => $contactPersons !== [] ? $contactPersons : null,
            ]);
        } else {
            $parts = preg_split('/\s+/', trim((string) $contact->name), 2) ?: [];
            $payload['person'] = array_filter([
                'firstName' => $parts[0] ?? null,
                'lastName' => $parts[1] ?? ($parts[0] ?? $contact->name),
            ]);
        }

        $addresses = $this->buildAddresses($contact, $billingAddress, $country);
        if ($addresses !== []) {
            $payload['addresses'] = $addresses;
        }

        if ($contact->email || $contact->phone || $contact->mobile || $contact->fax) {
            $payload['emailAddresses'] = $contact->email
                ? ['business' => [$contact->email]]
                : null;
            $payload['phoneNumbers'] = array_filter([
                'business' => $contact->phone ? [$contact->phone] : null,
                'mobile' => $contact->mobile ? [$contact->mobile] : null,
                'fax' => $contact->fax ? [$contact->fax] : null,
            ]);
        }

        return array_filter($payload, static fn($v) => $v !== null && $v !== []);
    }

    /**
     * Baut die addresses-Struktur (billing + optionale shipping-Adressen aus
     * der contact_addresses-Relation).
     *
     * @param  array<string, mixed>  $billingAddress
     * @return array<string, mixed>
     */
    private function buildAddresses(Customer|Supplier $contact, array $billingAddress, string $defaultCountry): array {
        $addresses = [];
        if ($billingAddress !== []) {
            $addresses['billing'] = [$billingAddress];
        }

        $shipping = [];
        foreach ($contact->addresses()->where('kind', \App\Models\ContactAddress::KIND_SHIPPING)->get() as $addr) {
            $entry = array_filter([
                'supplement' => $addr->supplement,
                'street' => $addr->street,
                'zip' => $addr->zip,
                'city' => $addr->city,
                'countryCode' => $addr->country_code ?: $defaultCountry,
            ]);
            if ($entry !== []) {
                $shipping[] = $entry;
            }
        }
        if ($shipping !== []) {
            $addresses['shipping'] = $shipping;
        }

        return $addresses;
    }

    /**
     * Baut die contactPersons-Liste aus den strukturierten Kontaktpersonen
     * des Kunden, fällt auf das Legacy-Einzelfeld contact_name zurück.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildContactPersons(Customer|Supplier $customer): array {
        $persons = $customer->contact_persons ?? [];
        $list = [];

        foreach ($persons as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            if ($name === '' && empty($p['email']) && empty($p['phone'])) {
                continue;
            }
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $list[] = array_filter([
                'salutation' => null,
                'firstName' => isset($parts[1]) ? $parts[0] : null,
                'lastName' => $parts[1] ?? ($parts[0] ?? $name),
                'emailAddress' => $p['email'] ?? null,
                'phoneNumber' => $p['phone'] ?? null,
                'primary' => (bool) ($p['primary'] ?? false),
            ], static fn($v) => $v !== null && $v !== '');
        }

        if ($list === [] && $customer->contact_name) {
            $list[] = array_filter([
                'lastName' => $customer->contact_name,
                'emailAddress' => $customer->email,
                'phoneNumber' => $customer->phone ?: $customer->mobile,
                'primary' => true,
            ]);
        }

        return $list;
    }

    /**
     * Build a Lexoffice voucher payload (type=salesinvoice) summarising all
     * billable time entries of the given customer in [$from, $to], grouped
     * by project. Each project becomes one voucher item.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function timeEntriesToVoucherPayload(
        Customer $customer,
        Collection $entries,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $defaults = [],
    ): array {
        $taxType = $defaults['default_tax_type'] ?? 'net';
        $vatRate = (float) ($defaults['default_vat_rate'] ?? 19.0);
        $currency = $customer->currency->value;

        // Taktung & Zusammenfassung anwenden: liefert Blöcke je (Projekt, kind).
        if ($entries instanceof \Illuminate\Database\Eloquent\Collection) {
            $entries->loadMissing(['project.parent', 'project.customer', 'project.foreignCustomer']);
        }
        $blocks = app(\App\Services\Invoicing\BillableTimeAggregator::class)->aggregate($entries);

        $items = $blocks
            ->groupBy(fn(\App\Services\Invoicing\BillingBlock $b) => ($b->project->id ?? 0) . '|' . ($b->kind->value ?? ''))
            ->map(function (Collection $group) use ($vatRate, $from, $to) {
                /** @var \App\Services\Invoicing\BillingBlock $first */
                $first = $group->first();
                $project = $first->project;
                $kind = $first->kind;
                $projectName = $project !== null ? $project->name : (string) __('Leistung');
                $hours = round((float) $group->sum(fn(\App\Services\Invoicing\BillingBlock $b) => $b->billedHours()), 2);
                $revenue = round((float) $group->sum(fn(\App\Services\Invoicing\BillingBlock $b) => $b->revenue), 2);
                // Stundensatz aus der gearbeiteten Zeit (ohne Lücken), damit die
                // Taktung über die aufgerundeten $hours den Betrag erhöht.
                $workedHours = (float) $group->sum(fn(\App\Services\Invoicing\BillingBlock $b) => $b->workedMinutes) / 60.0;
                $unitPrice = $workedHours > 0 ? round($revenue / $workedHours, 2) : 0.0;

                // Standardleistung (MVP-486): Projekt-Regel → Org-Einstellung.
                $service = app(\App\Services\Invoicing\ServiceDefaultResolver::class)
                    ->resolve($project?->organization, $project, $kind?->value);

                $type = $service?->itemType ?: 'service';
                $unitName = $service?->unitName ?: 'Stunde';
                $taxRate = $service !== null && $service->vatRate !== null ? $service->vatRate : $vatRate;
                // Ein an der Projektregel gepflegter Preis ist eine bewusste
                // Festlegung und gewinnt; sonst zählt der aus den Zeiten
                // errechnete Satz, und der Listenpreis der Leistung füllt nur
                // die Lücke (sonst 0,00 €).
                $servicePrice = $service !== null ? $service->netPrice : null;
                $netAmount = $service?->priceIsExplicit === true && ($servicePrice ?? 0.0) > 0.0
                    ? (float) $servicePrice
                    : ($unitPrice > 0.0 ? $unitPrice : ($servicePrice ?? 0.0));

                $kindSuffix = $kind !== null ? ' [' . $kind->value . ']' : '';
                // Endkunde (Fremdkunde) mit in die Buchungszeile übernehmen.
                $endkunde = $project?->foreignCustomer;
                $prefix = $endkunde !== null
                    ? (string) __('Endkunde :name', ['name' => trim((string) ($endkunde->company ?: $endkunde->name))]) . ' · '
                    : '';
                $name = sprintf('%s%s%s (%s – %s)', $prefix, $projectName, $kindSuffix, $from->format('d.m.Y'), $to->format('d.m.Y'));

                $item = [
                    'type' => $type,
                    'name' => $name,
                    'quantity' => $hours,
                    'unitName' => $unitName,
                    'unitPrice' => [
                        'currency' => 'EUR',
                        'netAmount' => $netAmount,
                        'taxRatePercentage' => $taxRate,
                    ],
                ];

                if ($service?->articleId !== null) {
                    $item['id'] = $service->articleId;
                }
                if (filled($service?->standardText)) {
                    $item['description'] = (string) $service->standardText;
                }

                return $item;
            })->values()->all();

        return [
            'voucherType' => 'salesinvoice',
            'voucherNumber' => null,
            'voucherDate' => $to->format('Y-m-d') . 'T00:00:00.000+01:00',
            'totalGrossAmount' => null,
            'totalTaxAmount' => null,
            'taxType' => $taxType,
            'useCollectiveContact' => false,
            'contactId' => $defaults['external_contact_id'] ?? null,
            'remark' => sprintf('Zeiterfassung %s – %s', $from->format('d.m.Y'), $to->format('d.m.Y')),
            'voucherItems' => $items,
            'currency' => $currency,
        ];
    }
}
