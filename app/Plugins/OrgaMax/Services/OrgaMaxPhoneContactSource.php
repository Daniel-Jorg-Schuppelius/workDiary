<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxPhoneContactSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Models\Platform\Organization;
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Contacts\{ExternalPhoneContact, ExternalPhoneContactSource};
use CommonToolkit\Helper\Data\PersonNameHelper;
use Orgamax\API\Endpoints\CustomersEndpoint;
use Orgamax\Entities\Customers\Customer;

/**
 * Lesende orgaMAX-Kundenliste für den Rufnummernabgleich (FRITZ!Box, CTI;
 * MVP-1500): `phone1`, `phone2` und `mobile` des Kunden und seiner
 * Ansprechpartner (OpenAPI im orgaMAX-SDK), alle Seiten über `searchAll()`.
 * Verknüpft ist ein Treffer über die Kundenreferenz des Stammdatenimports
 * (`customer`) bzw. des Push (`contact`).
 */
final class OrgaMaxPhoneContactSource implements ExternalPhoneContactSource {
    private const int MAX_PAGES = 40;

    public function id(): string {
        return OrgaMaxPlugin::ID;
    }

    public function label(): string {
        return 'orgaMAX';
    }

    public function isAvailable(Organization $organization): bool {
        return $this->connection($organization)?->isActive() === true;
    }

    public function contacts(Organization $organization): iterable {
        $connection = $this->connection($organization);
        if (! $connection instanceof OrgaMaxConnection || ! $connection->isActive()) {
            return [];
        }

        $contacts = [];
        $customers = (new CustomersEndpoint(app(OrgaMaxClientFactory::class)->for($connection)))
            ->searchAll([], CustomersEndpoint::MAX_PAGE_SIZE, [], self::MAX_PAGES);
        foreach ($customers as $customer) {
            if ($customer instanceof Customer) {
                $contact = $this->mapContact($customer);
                if ($contact instanceof ExternalPhoneContact) {
                    $contacts[] = $contact;
                }
            }
        }

        return $contacts;
    }

    private function mapContact(Customer $customer): ?ExternalPhoneContact {
        $externalId = trim((string) $customer->getId());
        $phones = [$customer->getPhone1(), $customer->getPhone2(), $customer->getMobile()];
        foreach ($customer->getContactPersons() ?? [] as $person) {
            array_push($phones, $person->getPhone1(), $person->getPhone2(), $person->getMobile());
        }
        $phones = array_values(array_unique(array_filter(array_map(static fn (?string $phone): string => trim((string) $phone), $phones), static fn (string $phone): bool => $phone !== '')));
        if ($externalId === '' || $phones === []) {
            return null;
        }

        $company = trim((string) $customer->getCompanyName());
        $person = PersonNameHelper::join(['first_name' => $customer->getFirstName(), 'last_name' => $customer->getLastName()]);
        $name = trim((string) $customer->getName());

        return new ExternalPhoneContact(
            providerId: $this->id(),
            providerLabel: $this->label(),
            externalId: $externalId,
            name: $company !== '' ? $company : ($name !== '' ? $name : ($person !== '' ? $person : null)),
            company: $company !== '' ? $company : null,
            phones: $phones,
        );
    }

    private function connection(Organization $organization): ?OrgaMaxConnection {
        return OrgaMaxConnection::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->first();
    }
}
