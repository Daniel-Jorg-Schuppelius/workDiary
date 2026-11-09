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
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
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
 * Übernommen werden die Felder des Kontakts; Anschrift und Kommunikationswege
 * sind eigene Ressourcen (`GET /ContactAddress`, `GET /CommunicationWay`) und
 * gehen in diesem Schritt nicht mit.
 */
final class SevDeskContactImportService extends AccountingContactImporter {
    /** `limit` 1–1000 laut Doku. */
    private const int PAGE_SIZE = 1000;

    private const int MAX_PAGES = 20;

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
        $rows = RemotePages::offset(static function (int $page) use ($client): OffsetPage {
            $result = $client->contacts(($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
            $hasMore = $result['total'] !== null ? $page * self::PAGE_SIZE < $result['total'] : count($result['rows']) >= self::PAGE_SIZE;

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $hasMore, $result['total']);
        }, self::MAX_PAGES);

        foreach ($rows as $row) {
            yield self::contact($row, $parties);
        }
    }

    protected function linked(Customer|Supplier $party, RemoteContact $contact, ?string $number): void {
        $this->numbers->linked($party, SevDeskPlugin::ID, $contact->externalId, $number);
    }

    /**
     * @param  array<string, mixed>  $row  `Model_ContactResponse`
     * @param  array<int, class-string<Customer|Supplier>>  $parties  Kategorie-ID → Rolle
     */
    private static function contact(array $row, array $parties): RemoteContact {
        $id = StringHelper::trimToNull($row['id'] ?? null) ?? '';
        $party = $parties[(int) data_get($row, 'category.id', 0)] ?? null;
        if ($id === '' || $party === null || StringHelper::trimToNull(data_get($row, 'parent.id')) !== null) {
            return new RemoteContact($id, [], [], $row);
        }

        $organization = StringHelper::trimToNull($row['name'] ?? null);
        $firstName = StringHelper::trimToNull($row['surename'] ?? null);
        $lastName = StringHelper::trimToNull($row['familyname'] ?? null);

        return new RemoteContact($id, [$party => StringHelper::trimToNull($row['customerNumber'] ?? null)], [
            'name' => $organization ?? PersonNameHelper::join(['first_name' => $firstName, 'last_name' => $lastName]),
            'company' => $organization,
            'vat_id' => StringHelper::trimToNull($row['vatNumber'] ?? null),
            'tax_number' => StringHelper::trimToNull($row['taxNumber'] ?? null),
            // Eine Person ist ihr eigener Ansprechpartner.
            'contact_title' => $organization === null ? StringHelper::trimToNull($row['academicTitle'] ?? null) : null,
            'contact_first_name' => $organization === null ? $firstName : null,
            'contact_last_name' => $organization === null ? $lastName : null,
        ], $row);
    }
}
