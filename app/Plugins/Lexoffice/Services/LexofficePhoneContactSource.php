<?php

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Exceptions\ApiException;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Services\Contacts\{ExternalPhoneContact, ExternalPhoneContactSource};
use Lexoffice\API\Endpoints\ContactsEndpoint;
use RuntimeException;

/** Lesende Lexoffice-Kontaktquelle für den provider-neutralen Rufnummernabgleich. */
final class LexofficePhoneContactSource implements ExternalPhoneContactSource {
    private const PAGE_LIMIT = 100;

    public function id(): string {
        return LexofficePlugin::ID;
    }

    public function label(): string {
        return 'Lexoffice';
    }

    public function isAvailable(Organization $organization): bool {
        $config = LexofficeConfig::resolve($organization->id);

        return $config['enabled'] && trim((string) $config['api_key']) !== '';
    }

    public function contacts(Organization $organization): iterable {
        $config = LexofficeConfig::resolve($organization->id);
        $apiKey = trim((string) $config['api_key']);
        if (! $config['enabled'] || $apiKey === '') {
            return [];
        }

        $endpoint = new ContactsEndpoint(app(LexofficeClientFactory::class)->sdk($apiKey, $config['base_url'], $config['request_interval']));

        $contacts = [];
        $page = 0;
        do {
            try {
                $found = $endpoint->search(['page' => $page, 'size' => 100]);
            } catch (ApiException $e) {
                throw new RuntimeException('Lexoffice-Kontaktabruf fehlgeschlagen (HTTP ' . $e->getCode() . ').', 0, $e);
            }

            foreach ($found->getValues() as $remote) {
                $contact = $this->mapContact($remote->toArray());
                if ($contact instanceof ExternalPhoneContact) {
                    $contacts[] = $contact;
                }
            }

            $page++;
            $totalPages = min(self::PAGE_LIMIT, max(1, $found->getTotalPages()));
        } while ($page < $totalPages);

        return $contacts;
    }

    /** @param array<string, mixed> $remote */
    private function mapContact(array $remote): ?ExternalPhoneContact {
        $externalId = trim((string) ($remote['id'] ?? ''));
        if ($externalId === '') {
            return null;
        }

        $company = trim((string) data_get($remote, 'company.name', ''));
        $person = trim((string) data_get($remote, 'person.firstName', '') . ' ' . (string) data_get($remote, 'person.lastName', ''));
        $phones = [];
        foreach (['business', 'mobile', 'private'] as $kind) {
            foreach ((array) data_get($remote, 'phoneNumbers.' . $kind, []) as $phone) {
                if (is_string($phone) && trim($phone) !== '') {
                    $phones[] = trim($phone);
                }
            }
        }
        foreach ((array) data_get($remote, 'company.contactPersons', []) as $contactPerson) {
            if (is_array($contactPerson) && is_string($contactPerson['phoneNumber'] ?? null) && trim($contactPerson['phoneNumber']) !== '') {
                $phones[] = trim($contactPerson['phoneNumber']);
            }
        }

        if ($phones === []) {
            return null;
        }

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
