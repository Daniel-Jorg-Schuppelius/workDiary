<?php

declare(strict_types=1);

namespace App\Plugins\Msgraph;

use App\Models\{MsgraphContactConnection, Organization};
use App\Plugins\Msgraph\Api\MsgraphContactsClient;
use App\Plugins\Support\PluginSettingsResolver;
use App\Services\Contacts\{ExternalPhoneContact, ExternalPhoneContactSource};

/** Outlook-Kontakte des verbundenen Microsoft-365-Kontos als Telefonverzeichnis. */
final class MsgraphPhoneContactSource implements ExternalPhoneContactSource {
    public function id(): string {
        return MsgraphPlugin::ID;
    }

    public function label(): string {
        return 'Microsoft 365';
    }

    public function isAvailable(Organization $organization): bool {
        return PluginSettingsResolver::for(MsgraphPlugin::ID, $organization->id)->enabled()
            && $this->connection($organization)?->isActive() === true;
    }

    public function contacts(Organization $organization): iterable {
        $connection = $this->connection($organization);
        if (! $this->isAvailable($organization) || ! $connection instanceof MsgraphContactConnection) {
            return [];
        }

        $contacts = [];
        try {
            $remoteContacts = (new MsgraphContactsClient($connection))->contacts();
            $connection->recordConnectionSuccess();
        } catch (\Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            throw $e;
        }
        foreach ($remoteContacts as $remote) {
            $externalId = trim((string) ($remote['id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $phones = [];
            foreach (['businessPhones', 'homePhones'] as $field) {
                foreach ((array) ($remote[$field] ?? []) as $phone) {
                    if (is_string($phone) && trim($phone) !== '') {
                        $phones[] = trim($phone);
                    }
                }
            }
            $mobile = trim((string) ($remote['mobilePhone'] ?? ''));
            if ($mobile !== '') {
                $phones[] = $mobile;
            }
            if ($phones === []) {
                continue;
            }

            $name = trim((string) ($remote['displayName'] ?? ''));
            $company = trim((string) ($remote['companyName'] ?? ''));
            $contacts[] = new ExternalPhoneContact(
                providerId: $this->id(),
                providerLabel: $this->label(),
                externalId: $externalId,
                name: $name !== '' ? $name : ($company !== '' ? $company : null),
                company: $company !== '' ? $company : null,
                phones: array_values(array_unique($phones)),
            );
        }

        return $contacts;
    }

    private function connection(Organization $organization): ?MsgraphContactConnection {
        return MsgraphContactConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->first();
    }
}
