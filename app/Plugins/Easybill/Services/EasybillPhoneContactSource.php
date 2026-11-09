<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillPhoneContactSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Models\Platform\Organization;
use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\{EasybillConfig, EasybillPlugin};
use App\Services\Contacts\{ExternalPhoneContact, ExternalPhoneContactSource};
use CommonToolkit\Helper\Data\PersonNameHelper;

/**
 * Lesende easybill-Kundenliste für den Rufnummernabgleich (FRITZ!Box, CTI;
 * MVP-1500): `phone_1`, `phone_2` und `mobile` des Kunden (Swagger-Fixture
 * `Customer`). Verknüpft ist ein Treffer über die Kontaktreferenz der
 * Übertragung (`contact`).
 */
final class EasybillPhoneContactSource implements ExternalPhoneContactSource {
    private const int PAGE_SIZE = 1000;

    private const int MAX_PAGES = 50;

    public function id(): string {
        return EasybillPlugin::ID;
    }

    public function label(): string {
        return 'easybill';
    }

    public function isAvailable(Organization $organization): bool {
        return EasybillConfig::resolve($organization->id)['enabled'] && app(EasybillClientFactory::class)->isConfigured($organization->id);
    }

    public function contacts(Organization $organization): iterable {
        if (! $this->isAvailable($organization)) {
            return [];
        }

        $client = app(EasybillClientFactory::class)->for($organization->id);
        $contacts = [];
        $page = 1;
        do {
            $found = $client->customers($page, self::PAGE_SIZE);
            foreach ($found['rows'] as $remote) {
                $contact = $this->mapContact($remote);
                if ($contact instanceof ExternalPhoneContact) {
                    $contacts[] = $contact;
                }
            }
            $page++;
        } while ($page <= min(self::MAX_PAGES, $found['pages']));

        return $contacts;
    }

    /** @param  array<string, mixed>  $remote */
    private function mapContact(array $remote): ?ExternalPhoneContact {
        $externalId = trim((string) ($remote['id'] ?? ''));
        $phones = [];
        foreach (['phone_1', 'phone_2', 'mobile'] as $field) {
            $phone = trim((string) ($remote[$field] ?? ''));
            if ($phone !== '') {
                $phones[] = $phone;
            }
        }
        if ($externalId === '' || $phones === []) {
            return null;
        }

        $company = trim((string) ($remote['company_name'] ?? ''));
        $person = PersonNameHelper::join(['first_name' => $remote['first_name'] ?? null, 'last_name' => $remote['last_name'] ?? null]);

        return new ExternalPhoneContact(
            providerId: $this->id(),
            providerLabel: $this->label(),
            externalId: $externalId,
            name: $person !== '' ? $person : ($company !== '' ? $company : null),
            company: $company !== '' ? $company : null,
            phones: array_values(array_unique($phones)),
        );
    }
}
