<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillContactImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Stammdaten\ContactSalutation;
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\{EasybillConfig, EasybillPlugin};
use App\Plugins\Support\ContactImport\{AccountingContactImporter, RemoteContact};
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Integration\IntegrationResolver;
use App\Services\Stammdaten\ContactNumberAdopter;
use App\Services\Stammdaten\Contracts\ContactPushTarget;
use CommonToolkit\Helper\Data\{PersonNameHelper, StringHelper};

/**
 * Kontakt-Rückweg aus easybill (MVP-1502) über `GET /customers` (Swagger-
 * Fixture `Customer`). Die Rolle ergibt sich aus den Nummern: `number` ist die
 * Kundennummer, `supplier_number` die Lieferantennummer; ein Kontakt kann
 * beides sein. Archivierte Kontakte bleiben draußen. `GET /customers` kennt
 * keinen Änderungsfilter, gelesen wird deshalb die ganze Liste.
 */
final class EasybillContactImportService extends AccountingContactImporter {
    /** `limit` bis 1000 laut Swagger. */
    private const int PAGE_SIZE = 1000;

    private const int MAX_PAGES = 50;

    public function __construct(
        IntegrationResolver $resolver,
        ContactPushTarget $contacts,
        private readonly EasybillClientFactory $clients,
        private readonly ContactNumberAdopter $numbers,
    ) {
        parent::__construct($resolver, $contacts);
    }

    public function pluginId(): string {
        return EasybillPlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        return EasybillConfig::resolve($organizationId)['enabled'] && $this->clients->isConfigured($organizationId);
    }

    protected function remoteContacts(int $organizationId): iterable {
        $client = $this->clients->for($organizationId);
        $rows = RemotePages::offset(static function (int $page) use ($client): OffsetPage {
            $result = $client->customers($page, self::PAGE_SIZE);

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $page < $result['pages']);
        }, self::MAX_PAGES);

        foreach ($rows as $row) {
            yield self::contact($row);
        }
    }

    protected function linked(Customer|Supplier $party, RemoteContact $contact, ?string $number): void {
        $this->numbers->linked($party, EasybillPlugin::ID, $contact->externalId, $number);
    }

    /** @param  array<string, mixed>  $row  `Customer` der Swagger-Fixture */
    private static function contact(array $row): RemoteContact {
        $id = StringHelper::trimToNull($row['id'] ?? null) ?? '';
        $numbers = array_filter([
            Customer::class => StringHelper::trimToNull($row['number'] ?? null),
            Supplier::class => StringHelper::trimToNull($row['supplier_number'] ?? null),
        ], static fn (?string $number): bool => $number !== null);
        if ($id === '' || ($row['archived'] ?? false) === true) {
            $numbers = [];
        }

        $company = StringHelper::trimToNull($row['company_name'] ?? null);
        $firstName = StringHelper::trimToNull($row['first_name'] ?? null);
        $lastName = StringHelper::trimToNull($row['last_name'] ?? null);
        $emails = is_array($row['emails'] ?? null) ? array_values(array_filter($row['emails'], 'is_string')) : [];

        return new RemoteContact($id, $numbers, [
            'name' => $company ?? PersonNameHelper::join(['first_name' => $firstName, 'last_name' => $lastName]),
            'company' => $company,
            'vat_id' => StringHelper::trimToNull($row['vat_identifier'] ?? null),
            'tax_number' => StringHelper::trimToNull($row['tax_number'] ?? null),
            'email' => StringHelper::trimToNull($emails[0] ?? null),
            'phone' => StringHelper::trimToNull($row['phone_1'] ?? null),
            'mobile' => StringHelper::trimToNull($row['mobile'] ?? null),
            'address_street' => StringHelper::trimToNull($row['street'] ?? null),
            'address_zip' => StringHelper::trimToNull($row['zip_code'] ?? null),
            'address_city' => StringHelper::trimToNull($row['city'] ?? null),
            // Anrede laut Swagger: 1 Herr, 2 Frau; die übrigen Werte sind keine Personenanrede.
            'contact_salutation' => match ((int) ($row['salutation'] ?? 0)) {
                1 => ContactSalutation::Mr->value,
                2 => ContactSalutation::Ms->value,
                default => null,
            },
            'contact_title' => StringHelper::trimToNull($row['title'] ?? null),
            'contact_first_name' => $firstName,
            'contact_last_name' => $lastName,
        ], $row);
    }
}
