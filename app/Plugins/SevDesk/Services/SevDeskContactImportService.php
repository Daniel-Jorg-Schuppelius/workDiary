<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskContactImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Plugins\SevDesk\Api\{SevDeskClient, SevDeskClientFactory};
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Plugins\Support\ContactImport\{AccountingContactImporter, RemoteContact};
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Integration\IntegrationResolver;
use App\Services\Stammdaten\ContactNumberAdopter;
use App\Services\Stammdaten\Contracts\ContactPushTarget;
use CommonToolkit\Helper\Data\{PersonNameHelper, StringHelper};

/**
 * Kontakt-Rückweg aus sevDesk (MVP-1502) über `GET /Contact` (`depth=1`).
 * Die Rolle steht in der Kontaktkategorie (Doku „Contact Categories“:
 * Customer 3, Supplier 2); Partner und Interessenten bleiben draußen, ebenso
 * Personen einer Organisation (`parent`), die deren Ansprechpartner sind.
 *
 * Anschrift und Kommunikationswege sind eigene Ressourcen (MVP-1529). Statt
 * zwei Abrufen je Kontakt liest der Lauf beide Listen einmal ganz
 * (`GET /ContactAddress`, `GET /CommunicationWay`, je 1000 je Seite) und nur,
 * wenn ein Kontakt eine Rolle trägt. Primär ist:
 * - bei der Anschrift die Adresse der Kategorie, die der Kontakt-Push schreibt
 *   (`plugins.sevdesk.address_category_id`), sonst die einzige Adresse —
 *   `Model_ContactAddressResponse` hat kein Hauptkennzeichen;
 * - je Kommunikationsweg der mit `main` = 1, sonst der einzige seiner Art.
 *   PHONE mit dem Schlüssel Fax (3) ist Fax, mit Mobil (4) Mobilnummer
 *   (Doku „CommunicationWay“, Schlüssel 1–8).
 * Mehrdeutiges bleibt leer und damit lokal unverändert. Das Land fehlt: die
 * Antwort nennt nur die ID von `StaticCountry`, dessen Felder die Doku nicht
 * beschreibt.
 */
final class SevDeskContactImportService extends AccountingContactImporter {
    /** `limit` 1–1000 laut Doku. */
    private const int PAGE_SIZE = 1000;

    private const int MAX_PAGES = 20;

    /** Mehrere Adressen und Wege je Kontakt: die Listen sind länger als die Kontaktliste. */
    private const int MAX_DETAIL_PAGES = 100;

    private const string KEY_FAX = '3';

    private const string KEY_MOBILE = '4';

    public function __construct(
        IntegrationResolver $resolver,
        ContactPushTarget $contacts,
        private readonly SevDeskClientFactory $clients,
        private readonly ContactNumberAdopter $numbers,
    ) {
        parent::__construct($resolver, $contacts);
    }

    public function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        return SevDeskConfig::resolve($organizationId)['enabled'] && $this->clients->isConfigured($organizationId);
    }

    protected function remoteContacts(int $organizationId): iterable {
        $config = SevDeskConfig::resolve($organizationId);
        $parties = [$config['contact_category_id'] => Customer::class, $config['supplier_category_id'] => Supplier::class];
        $client = $this->clients->for($organizationId);
        $details = null;

        foreach (self::pages(static fn (int $offset): array => $client->contacts($offset, self::PAGE_SIZE), self::MAX_PAGES) as $row) {
            $id = StringHelper::trimToNull($row['id'] ?? null) ?? '';
            $party = self::party($row, $parties);
            if ($id === '' || $party === null) {
                yield new RemoteContact($id, [], [], $row);

                continue;
            }
            $details ??= self::details($client);

            yield self::contact($id, $row, $party, $details['addresses'][$id] ?? [], $details['ways'][$id] ?? []);
        }
    }

    protected function linked(Customer|Supplier $party, RemoteContact $contact, ?string $number): void {
        $this->numbers->linked($party, SevDeskPlugin::ID, $contact->externalId, $number);
    }

    /**
     * @param  callable(int): array{rows: list<array<string, mixed>>, total: ?int}  $fetch  Offset → Seite
     * @return iterable<array<string, mixed>>
     */
    private static function pages(callable $fetch, int $maxPages): iterable {
        return RemotePages::offset(static function (int $page) use ($fetch): OffsetPage {
            $result = $fetch(($page - 1) * self::PAGE_SIZE);
            $hasMore = $result['total'] !== null ? $page * self::PAGE_SIZE < $result['total'] : count($result['rows']) >= self::PAGE_SIZE;

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $hasMore, $result['total']);
        }, $maxPages);
    }

    /**
     * Adressen und Kommunikationswege aller Kontakte, schmal nach Kontakt-ID.
     *
     * @return array{addresses: array<string, list<array{id: int, category: ?string, street: ?string, zip: ?string, city: ?string}>>, ways: array<string, list<array{id: int, type: string, key: ?string, main: bool, value: string}>>}
     */
    private static function details(SevDeskClient $client): array {
        $addresses = [];
        foreach (self::pages(static fn (int $offset): array => $client->contactAddresses($offset, self::PAGE_SIZE), self::MAX_DETAIL_PAGES) as $row) {
            $contactId = StringHelper::trimToNull(data_get($row, 'contact.id'));
            if ($contactId !== null) {
                $addresses[$contactId][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'category' => StringHelper::trimToNull(data_get($row, 'category.id')),
                    'street' => StringHelper::trimToNull($row['street'] ?? null),
                    'zip' => StringHelper::trimToNull($row['zip'] ?? null),
                    'city' => StringHelper::trimToNull($row['city'] ?? null),
                ];
            }
        }

        $ways = [];
        foreach (self::pages(static fn (int $offset): array => $client->communicationWays($offset, self::PAGE_SIZE), self::MAX_DETAIL_PAGES) as $row) {
            $contactId = StringHelper::trimToNull(data_get($row, 'contact.id'));
            $value = StringHelper::trimToNull($row['value'] ?? null);
            if ($contactId !== null && $value !== null) {
                $ways[$contactId][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'type' => strtoupper((string) StringHelper::trimToNull($row['type'] ?? null)),
                    'key' => StringHelper::trimToNull(data_get($row, 'key.id')),
                    'main' => in_array($row['main'] ?? null, ['1', 1, true, 'true'], true),
                    'value' => $value,
                ];
            }
        }

        return ['addresses' => $addresses, 'ways' => $ways];
    }

    /**
     * @param  array<string, mixed>  $row  `Model_ContactResponse`
     * @param  array<int, class-string<Customer|Supplier>>  $parties  Kategorie-ID → Rolle
     * @return class-string<Customer|Supplier>|null
     */
    private static function party(array $row, array $parties): ?string {
        if (StringHelper::trimToNull(data_get($row, 'parent.id')) !== null) {
            return null;
        }

        return $parties[(int) data_get($row, 'category.id', 0)] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row  `Model_ContactResponse`
     * @param  class-string<Customer|Supplier>  $party
     * @param  list<array{id: int, category: ?string, street: ?string, zip: ?string, city: ?string}>  $addresses
     * @param  list<array{id: int, type: string, key: ?string, main: bool, value: string}>  $ways
     */
    private static function contact(string $id, array $row, string $party, array $addresses, array $ways): RemoteContact {
        $organization = StringHelper::trimToNull($row['name'] ?? null);
        $firstName = StringHelper::trimToNull($row['surename'] ?? null);
        $lastName = StringHelper::trimToNull($row['familyname'] ?? null);
        $address = self::primaryAddress($addresses);

        return new RemoteContact($id, [$party => StringHelper::trimToNull($row['customerNumber'] ?? null)], [
            'name' => $organization ?? PersonNameHelper::join(['first_name' => $firstName, 'last_name' => $lastName]),
            'company' => $organization,
            'vat_id' => StringHelper::trimToNull($row['vatNumber'] ?? null),
            'tax_number' => StringHelper::trimToNull($row['taxNumber'] ?? null),
            // Eine Person ist ihr eigener Ansprechpartner.
            'contact_title' => $organization === null ? StringHelper::trimToNull($row['academicTitle'] ?? null) : null,
            'contact_first_name' => $organization === null ? $firstName : null,
            'contact_last_name' => $organization === null ? $lastName : null,
            'email' => self::primaryWay($ways, static fn (array $way): bool => $way['type'] === 'EMAIL'),
            'phone' => self::primaryWay($ways, static fn (array $way): bool => $way['type'] === 'PHONE' && ! in_array($way['key'], [self::KEY_FAX, self::KEY_MOBILE], true)),
            'mobile' => self::primaryWay($ways, static fn (array $way): bool => $way['type'] === 'MOBILE' || ($way['type'] === 'PHONE' && $way['key'] === self::KEY_MOBILE)),
            'fax' => self::primaryWay($ways, static fn (array $way): bool => $way['type'] === 'PHONE' && $way['key'] === self::KEY_FAX),
            'homepage' => self::primaryWay($ways, static fn (array $way): bool => $way['type'] === 'WEB'),
            'address_street' => $address['street'] ?? null,
            'address_zip' => $address['zip'] ?? null,
            'address_city' => $address['city'] ?? null,
        ], $row);
    }

    /**
     * @param  list<array{id: int, category: ?string, street: ?string, zip: ?string, city: ?string}>  $addresses
     * @return array{id: int, category: ?string, street: ?string, zip: ?string, city: ?string}|null
     */
    private static function primaryAddress(array $addresses): ?array {
        $category = (string) config('plugins.sevdesk.address_category_id', 47);
        $preferred = array_values(array_filter($addresses, static fn (array $address): bool => $address['category'] === $category));
        usort($preferred, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $preferred[0] ?? (count($addresses) === 1 ? $addresses[0] : null);
    }

    /**
     * @param  list<array{id: int, type: string, key: ?string, main: bool, value: string}>  $ways
     * @param  callable(array{id: int, type: string, key: ?string, main: bool, value: string}): bool  $kind
     */
    private static function primaryWay(array $ways, callable $kind): ?string {
        $candidates = array_values(array_filter($ways, $kind));
        $main = array_values(array_filter($candidates, static fn (array $way): bool => $way['main']));
        usort($main, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return ($main[0] ?? (count($candidates) === 1 ? $candidates[0] : null))['value'] ?? null;
    }
}
