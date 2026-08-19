<?php

declare(strict_types=1);

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\Organization;
use App\Plugins\Support\PluginHttpFactory;
use App\Services\Contacts\{ExternalPhoneContact, ExternalPhoneContactSource};
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

        $baseUrl = rtrim($config['base_url'], '/');
        $api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $baseUrl);
        $api->setAuthentication(new BearerAuthentication($apiKey));

        $contacts = [];
        $page = 0;
        do {
            $response = $api->getResponse($baseUrl . '/contacts', ['page' => $page, 'size' => 100]);
            if (! $response->successful()) {
                throw new RuntimeException('Lexoffice-Kontaktabruf fehlgeschlagen (HTTP ' . $response->status() . ').');
            }

            /** @var array<string, mixed> $body */
            $body = (array) ($response->json() ?? []);
            foreach ((array) ($body['content'] ?? []) as $remote) {
                if (! is_array($remote)) {
                    continue;
                }
                $contact = $this->mapContact($remote);
                if ($contact instanceof ExternalPhoneContact) {
                    $contacts[] = $contact;
                }
            }

            $page++;
            $totalPages = min(self::PAGE_LIMIT, max(1, (int) ($body['totalPages'] ?? 1)));
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
