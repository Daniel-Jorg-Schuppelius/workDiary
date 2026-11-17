<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Invoicing\SalesTaxCase;
use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Models\Time\TimeEntry;
use App\Services\Billing\DocumentTotalsCalculator;
use App\Services\Invoicing\Dto\{OutgoingTaxCase, TargetTopUpLine};
use App\Services\Stammdaten\ContactPersons;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{DateHelper, PersonNameHelper};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;

/**
 * Translates workDiary domain models into Lexoffice JSON payloads.
 *
 * The payload structures follow the public Lexoffice REST API:
 *   https://developers.lexware.io/docs/
 *
 * They are intentionally produced as plain associative arrays so they can be
 * fed into Lexoffice entity classes via fromJson(json_encode($payload)) which
 * is the conventional construction pattern in the Lexoffice SDK.
 */
class LexofficeMapper {
    /** Belegtext des Zeitbelegs mit dem Abrechnungszeitraum; nur er nennt den Beginn ({@see timeVoucherPeriod()}). */
    private const string TIME_VOUCHER_REMARK = 'Zeiterfassung %s – %s: %s';

    private const string TIME_VOUCHER_PERIOD_PATTERN = '/^Zeiterfassung (\d{2}\.\d{2}\.\d{4}) – (\d{2}\.\d{2}\.\d{4})/u';

    /**
     * Abrechnungszeitraum eines gespeicherten Zeitbelegs aus seinem Belegtext
     * (Nutzlast der Fremdreferenz); null, wenn der Text ihn nicht trägt.
     *
     * @param  array<string, mixed>  $payload
     * @return array{from: CarbonImmutable, to: CarbonImmutable}|null
     */
    public static function timeVoucherPeriod(array $payload): ?array {
        if (preg_match(self::TIME_VOUCHER_PERIOD_PATTERN, (string) ($payload['remark'] ?? ''), $match) !== 1) {
            return null;
        }
        $from = DateHelper::normalizeToIso($match[1]);
        $to = DateHelper::normalizeToIso($match[2]);

        return $from !== null && $to !== null ? ['from' => CarbonImmutable::parse($from)->startOfDay(), 'to' => CarbonImmutable::parse($to)->endOfDay()] : null;
    }

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
                'name' => $contact->displayLabel(),
                'taxNumber' => $contact->tax_number ?: null,
                'vatRegistrationId' => $contact->vat_id ?: null,
                'contactPersons' => $contactPersons !== [] ? $contactPersons : null,
            ]);
        } else {
            $parts = PersonNameHelper::split((string) $contact->name);
            $payload['person'] = array_filter([
                'salutation' => self::salutation($parts['salutation'], $parts['title']),
                'firstName' => PersonNameHelper::join(['first_name' => $parts['first_name'], 'middle_names' => $parts['middle_names']]) ?: null,
                'lastName' => $parts['last_name'] ?? $contact->name,
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
        foreach ($contact->addresses()->where('kind', \App\Models\Contacts\ContactAddress::KIND_SHIPPING)->get() as $addr) {
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
     * Baut die contactPersons-Liste aus den Ansprechpartnern des Kunden mit
     * Anrede, Vornamen und Nachname (MVP-1138); ohne Liste geht der
     * Ansprechpartner des Formulars als primärer Kontakt hinüber.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildContactPersons(Customer|Supplier $customer): array {
        $entries = ContactPersons::normalizeAll($customer->contact_persons ?? []);
        if ($entries === []) {
            $primary = ContactPersons::primaryOf($customer);
            $entries = $primary !== null ? [$primary] : [];
        }

        $list = [];
        foreach ($entries as $entry) {
            $lastName = (string) ($entry['last_name'] ?? '');
            if ($lastName === '') {
                continue; // Lexoffice verlangt einen Nachnamen
            }
            $list[] = array_filter([
                'salutation' => self::salutation($entry['salutation'], $entry['title']),
                'firstName' => PersonNameHelper::join(['first_name' => $entry['first_name'], 'middle_names' => $entry['middle_names']]) ?: null,
                'lastName' => $lastName,
                'emailAddress' => $entry['email'],
                'phoneNumber' => $entry['phone'],
                'primary' => (bool) $entry['primary'],
            ], static fn($v) => $v !== null && $v !== '');
        }

        return $list;
    }

    /**
     * Ansprechpartner einer Lexoffice-Firma als Einträge für contact_persons:
     * Anrede samt Titel steht in Lexoffice in einem Feld („Herr Dr.“), die
     * weiteren Vornamen im Vornamen (MVP-1138); Einträge ohne Namen entfallen.
     *
     * @param  array<string, mixed>  $remote
     * @return list<array<string, mixed>>
     */
    public static function contactPersonsFromRemote(array $remote): array {
        $list = [];
        foreach ((array) data_get($remote, 'company.contactPersons', []) as $p) {
            if (! is_array($p) || trim(((string) ($p['firstName'] ?? '')) . ((string) ($p['lastName'] ?? ''))) === '') {
                continue;
            }
            $entry = ContactPersons::fromExternal(
                is_string($p['salutation'] ?? null) ? $p['salutation'] : null,
                is_string($p['firstName'] ?? null) ? $p['firstName'] : null,
                is_string($p['lastName'] ?? null) ? $p['lastName'] : null,
                email: is_string($p['emailAddress'] ?? null) ? $p['emailAddress'] : null,
                phone: is_string($p['phoneNumber'] ?? null) ? $p['phoneNumber'] : null,
                primary: (bool) ($p['primary'] ?? false),
            );
            if ($entry !== null) {
                $list[] = $entry;
            }
        }

        return $list;
    }

    /**
     * Die Person eines Lexoffice-Privatkontakts (`person`) als Hauptansprechpartner.
     *
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>|null
     */
    public static function personFromRemote(array $remote): ?array {
        $person = $remote['person'] ?? null;
        if (! is_array($person) || trim(((string) ($person['firstName'] ?? '')) . ((string) ($person['lastName'] ?? ''))) === '') {
            return null;
        }

        return ContactPersons::fromExternal(
            is_string($person['salutation'] ?? null) ? $person['salutation'] : null,
            is_string($person['firstName'] ?? null) ? $person['firstName'] : null,
            is_string($person['lastName'] ?? null) ? $person['lastName'] : null,
            primary: true,
        );
    }

    /**
     * Anrede für Lexoffice (Freitext, höchstens 25 Zeichen): das deutsche
     * Anredewort, ein Titel dahinter („Herr Dr.“).
     */
    private static function salutation(mixed $salutation, mixed $title): ?string {
        $word = match ($salutation) {
            ContactSalutation::Mr->value => 'Herr',
            ContactSalutation::Ms->value => 'Frau',
            default => '',
        };
        $text = trim($word . ' ' . (string) $title);

        return $text !== '' ? mb_substr($text, 0, 25) : null;
    }

    /**
     * Beleg (type=salesinvoice) über die abrechenbaren Zeiten des Kunden im
     * Zeitraum, nach der Lexware-API-Referenz „Vouchers Endpoint“ (MVP-1723):
     * Positionen je Steuersatz mit `amount` (netto, `taxType` net),
     * `taxAmount`, `taxRatePercent` und `categoryId` des Steuerfalls; Kopf mit
     * Belegnummer, Datum, Leistungsdatum und Summen. Je Projekt, Art und
     * Pauschal-Auftrag gilt der Betrag wie in der Rechnung (Taktung, Satz,
     * Pauschale je Auftrag, MVP-1678); Positionen mit Artikel und Einheit
     * kennt ein Buchhaltungsbeleg nicht. Die Auffüllung auf das Projekt-Soll
     * ({@see \App\Services\Invoicing\TargetTopUps::lines()}, MVP-1733) geht
     * mit Menge und Preis wie in Rechnung und Übergabe in den Betrag ihres
     * Steuersatzes ein.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @param  array<string, mixed>  $defaults
     * @param  iterable<TargetTopUpLine>  $topUps
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\UnmappableTaxCaseException Steuerfall ohne Buchungskategorie
     */
    public function timeEntriesToVoucherPayload(
        Customer $customer,
        Collection $entries,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $defaults = [],
        OutgoingTaxCase $taxCase = new OutgoingTaxCase(SalesTaxCase::Standard),
        iterable $topUps = [],
    ): array {
        $categoryId = LexofficeTaxConditions::voucherCategory($taxCase);
        $vatRate = (float) ($defaults['default_vat_rate'] ?? 19.0);
        // Lexware-Belege führen Euro; eine Fremdwährung sperrt der Aufrufer vorher.
        $currency = CurrencyCode::Euro;

        // Taktung & Zusammenfassung anwenden: liefert Blöcke je (Projekt, kind).
        if ($entries instanceof \Illuminate\Database\Eloquent\Collection) {
            $entries->loadMissing(['project.parent', 'project.customer', 'project.foreignCustomer']);
        }
        $blocks = app(\App\Services\Invoicing\BillableTimeAggregator::class)->aggregate($entries, $customer->organization);

        /** @var array<string, Money> $netByRate */
        $netByRate = [];
        $projects = [];
        $groups = $blocks
            // Pauschalen bleiben je Auftrag eine eigene Position.
            ->groupBy(fn(\App\Services\Invoicing\BillingBlock $b) => ($b->project->id ?? 0) . '|' . ($b->kind->value ?? '') . ($b->isFlatRate() ? '|flat|' . $b->primaryEntryId : ''));
        foreach ($groups as $group) {
            /** @var \App\Services\Invoicing\BillingBlock $first */
            $first = $group->first();
            $project = $first->project;
            $hours = round((float) $group->sum(fn(\App\Services\Invoicing\BillingBlock $b) => $b->quantity()), 2);
            $revenue = round((float) $group->sum(fn(\App\Services\Invoicing\BillingBlock $b) => $b->revenue), 2);
            // Stundensatz aus der gearbeiteten Zeit (ohne Lücken), damit die
            // Taktung über die aufgerundeten $hours den Betrag erhöht.
            $workedHours = (float) $group->sum(fn(\App\Services\Invoicing\BillingBlock $b) => $b->workedMinutes) / 60.0;
            $unitPrice = $workedHours > 0 ? round($revenue / $workedHours, 2) : 0.0;

            // Standardleistung (MVP-486): Projekt-Regel → Org-Einstellung.
            $service = app(\App\Services\Invoicing\ServiceDefaultResolver::class)
                ->resolve($customer->organization, $project, $first->kind?->value);
            // Ein an der Projektregel gepflegter Preis ist eine bewusste
            // Festlegung und gewinnt; sonst zählt der aus den Zeiten
            // errechnete Satz, und der Listenpreis der Leistung füllt nur
            // die Lücke (sonst 0,00 €). Eine Pauschale kostet den Preis ihrer Leistung.
            $servicePrice = $service !== null ? $service->netPrice : null;
            $netAmount = match (true) {
                $first->isFlatRate() => app(\App\Services\Invoicing\BlockPriceResolver::class)->resolve($first, null, $customer, $first->flatService)->rate,
                $service?->priceIsExplicit === true && ($servicePrice ?? 0.0) > 0.0 => (float) $servicePrice,
                default => $unitPrice > 0.0 ? $unitPrice : ($servicePrice ?? 0.0),
            };
            // Ohne Steuer (Drittland, § 13b, § 19 …) trägt die Position 0 %.
            $rate = $taxCase->zeroRated() ? 0.0 : ($service !== null && $service->vatRate !== null ? $service->vatRate : $vatRate);
            $key = DocumentTotalsCalculator::rateKey(DocumentTotalsCalculator::percent($rate));
            $net = DocumentTotalsCalculator::lineNet($hours, $netAmount, currency: $currency)->withScale(2);
            $netByRate[$key] = isset($netByRate[$key]) ? $netByRate[$key]->plus($net) : $net;
            if ($project !== null) {
                $projects[$project->id] = $project->name;
            }
        }
        foreach ($topUps as $topUp) {
            $rate = $taxCase->zeroRated() ? 0.0 : ($topUp->service !== null && $topUp->service->vatRate !== null ? $topUp->service->vatRate : $vatRate);
            $key = DocumentTotalsCalculator::rateKey(DocumentTotalsCalculator::percent($rate));
            $net = DocumentTotalsCalculator::lineNet($topUp->hours, $topUp->price->rate, currency: $currency)->withScale(2);
            $netByRate[$key] = isset($netByRate[$key]) ? $netByRate[$key]->plus($net) : $net;
            $projects[$topUp->shortfall->project_id] = $topUp->shortfall->project->name;
        }
        ksort($netByRate);

        $items = [];
        $tax = Money::zero($currency);
        $gross = Money::zero($currency);
        foreach ($netByRate as $key => $net) {
            // Steuer je Satz gerundet wie der Belegrechner ({@see DocumentTotalsCalculator::totals()}).
            $itemTax = $net->percentage((float) $key);
            $items[] = [
                'amount' => $net->toFloat(),
                'taxAmount' => $itemTax->toFloat(),
                'taxRatePercent' => (float) $key,
                'categoryId' => $categoryId,
            ];
            $tax = $tax->plus($itemTax);
            $gross = $gross->plus($net)->plus($itemTax);
        }
        $date = $to->format('Y-m-d') . 'T00:00:00.000+01:00';

        return [
            // POST /vouchers erwartet `type`; `voucherType` heißt das Feld nur in der
            // voucherlist. Die SDK-Entität verwarf den falschen Schlüssel still (MVP-802).
            'type' => 'salesinvoice',
            'voucherNumber' => (string) ($defaults['voucher_number'] ?? ''),
            'voucherDate' => $date,
            // Leistungszeitraum: die Referenz verlangt sein Ende.
            'shippingDate' => $date,
            'totalGrossAmount' => $gross->toFloat(),
            'totalTaxAmount' => $tax->toFloat(),
            'taxType' => 'net',
            'useCollectiveContact' => false,
            'contactId' => $defaults['external_contact_id'] ?? null,
            'remark' => mb_substr(trim(sprintf(self::TIME_VOUCHER_REMARK, $from->format('d.m.Y'), $to->format('d.m.Y'), implode(', ', $projects)), ' :'), 0, 255),
            'voucherItems' => $items,
        ];
    }

    /**
     * Auslage → Einkaufsbeleg-Payload (Feature 106): derselbe Endpunkt wie die
     * Faktura-Übergabe, nur `type: purchaseinvoice`.
     *
     * Der Händler ist in aller Regel KEIN Lexoffice-Kontakt — deshalb
     * `useCollectiveContact` (Sammelkontakt) und der Händlername im Belegtext,
     * statt für jede Tankstelle einen Kontakt anzulegen.
     *
     * @return array<string, mixed>
     */
    public function expenseToVoucherPayload(\App\Models\Travel\Expense $expense, string $categoryId): array {
        $gross = $expense->amount_gross?->toFloat() ?? 0.0;
        $tax = $expense->tax_amount?->toFloat() ?? 0.0;
        $taxRate = $expense->tax_rate?->getValue()->toFloat() ?? 0.0;

        $payload = [
            'type' => 'purchaseinvoice',
            'voucherNumber' => null,
            'voucherDate' => $expense->date->format('Y-m-d') . 'T00:00:00.000+01:00',
            'totalGrossAmount' => round($gross, 2),
            'totalTaxAmount' => round($tax, 2),
            'taxType' => 'gross',
            'useCollectiveContact' => true,
            'remark' => trim(($expense->vendor !== null ? $expense->vendor . ' - ' : '') . $expense->description),
            'voucherItems' => [[
                'amount' => round($gross, 2),
                'taxAmount' => round($tax, 2),
                'taxRatePercent' => $taxRate,
                'categoryId' => $categoryId,
            ]],
        ];

        return $payload;
    }

    /**
     * Einkaufsgutschrift als Gegenbeleg (MVP-802): gleiche Beträge wie der
     * ursprüngliche Beleg, Datum der Korrektur, Verweis im Vermerk.
     *
     * @return array<string, mixed>
     */
    public function expenseToCounterVoucherPayload(\App\Models\Travel\Expense $expense, string $categoryId, string $originalReference, string $reason): array {
        $payload = $this->expenseToVoucherPayload($expense, $categoryId);
        $payload['type'] = 'purchasecreditnote';
        $payload['voucherDate'] = now()->format('Y-m-d') . 'T00:00:00.000+01:00';
        $payload['remark'] = mb_substr(trim(sprintf('Gegenbeleg zu %s: %s', $originalReference, $reason)), 0, 255);

        return $payload;
    }
}
