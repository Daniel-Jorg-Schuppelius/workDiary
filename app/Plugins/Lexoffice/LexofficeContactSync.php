<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeContactSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{Customer, ExternalReference, Organization, PendingExternalConflict};
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pull-Sync für Lexoffice-Kontakte. Verbindet existierende workDiary-Kunden
 * mit ihren Lexoffice-Pendants über mehrere Match-Strategien und entscheidet
 * anhand der konfigurierten {@see LexofficeMatchPolicy}, wie mit
 * Daten-Konflikten umzugehen ist.
 *
 * Verwendet bewusst HTTP direkt statt SDK, weil wir mit dem Roh-JSON arbeiten
 * (Konflikt-Vergleich Feld für Feld) — die SDK-Klassen liefern hier keinen
 * Mehrwert, ihre fromJson()-Logik ist eher hinderlich.
 */
class LexofficeContactSync {
    /**
     * @return array{matched: int, linked: int, created: int, conflicts: int, updated: int}
     */
    public function sync(
        Organization $organization,
        LexofficeMatchPolicy $policy,
        ?string $apiKey,
        string $baseUrl = 'https://api.lexoffice.io/v1',
        bool $createMissingLocal = false,
    ): array {
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $matched = 0;
        $linked = 0;
        $created = 0;
        $conflicts = 0;
        $updated = 0;

        $page = 0;
        $pageSize = 100;

        do {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->get($baseUrl . '/contacts', [
                    'page' => $page,
                    'size' => $pageSize,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Lexoffice contacts request failed: ' . $response->status() . ' ' . $response->body());
            }

            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];
            $items = (array) ($body['content'] ?? []);

            foreach ($items as $remote) {
                if (! is_array($remote) || empty($remote['id'])) {
                    continue;
                }
                $externalId = (string) $remote['id'];

                $existingRef = ExternalReference::query()
                    ->where('organization_id', $organization->id)
                    ->where('plugin_id', LexofficePlugin::ID)
                    ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
                    ->where('external_id', $externalId)
                    ->first();

                if ($existingRef instanceof ExternalReference) {
                    /** @var Customer|null $customer */
                    $customer = Customer::query()->find($existingRef->referenceable_id);
                    if ($customer === null) {
                        continue;
                    }
                    $matched++;
                    if ($this->applyRemote($customer, $remote, $policy, $externalId)) {
                        $updated++;
                    } elseif ($policy === LexofficeMatchPolicy::ManualReview && $this->hasConflict($customer, $remote)) {
                        $this->recordConflict($customer, $remote, $externalId, $organization);
                        $conflicts++;
                    }

                    continue;
                }

                $match = $this->findLocalMatch($organization, $remote);
                if ($match instanceof Customer) {
                    ExternalReference::create([
                        'organization_id' => $organization->id,
                        'plugin_id' => LexofficePlugin::ID,
                        'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                        'referenceable_type' => $match->getMorphClass(),
                        'referenceable_id' => $match->getKey(),
                        'external_id' => $externalId,
                        'payload' => $remote,
                        'synced_at' => now(),
                    ]);
                    $linked++;
                    if ($this->applyRemote($match, $remote, $policy, $externalId)) {
                        $updated++;
                    } elseif ($policy === LexofficeMatchPolicy::ManualReview && $this->hasConflict($match, $remote)) {
                        $this->recordConflict($match, $remote, $externalId, $organization);
                        $conflicts++;
                    }

                    continue;
                }

                if ($createMissingLocal) {
                    $new = $this->createFromRemote($organization, $remote);
                    if ($new instanceof Customer) {
                        ExternalReference::create([
                            'organization_id' => $organization->id,
                            'plugin_id' => LexofficePlugin::ID,
                            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                            'referenceable_type' => $new->getMorphClass(),
                            'referenceable_id' => $new->getKey(),
                            'external_id' => $externalId,
                            'payload' => $remote,
                            'synced_at' => now(),
                        ]);
                        $created++;
                    }
                }
            }

            $totalPages = (int) ($body['totalPages'] ?? 1);
            $page++;
        } while ($page < $totalPages);

        return [
            'matched' => $matched,
            'linked' => $linked,
            'created' => $created,
            'conflicts' => $conflicts,
            'updated' => $updated,
        ];
    }

    /**
     * Versucht über vat_id → email → company+postcode → name einen lokalen Kunden zu finden.
     *
     * @param  array<string, mixed>  $remote
     */
    private function findLocalMatch(Organization $organization, array $remote): ?Customer {
        $vatId = $this->extractVatId($remote);
        $email = $this->extractEmail($remote);
        $company = (string) data_get($remote, 'company.name', '');
        $personName = trim(((string) data_get($remote, 'person.firstName', '')) . ' ' . ((string) data_get($remote, 'person.lastName', '')));
        $zip = (string) data_get($remote, 'addresses.billing.0.zip', '');

        $query = Customer::query()->where('organization_id', $organization->id);

        if ($vatId !== '') {
            $byVat = (clone $query)->where('vat_id', $vatId)->first();
            if ($byVat instanceof Customer) {
                return $byVat;
            }
        }

        if ($email !== '') {
            $byEmail = (clone $query)->where('email', $email)->first();
            if ($byEmail instanceof Customer) {
                return $byEmail;
            }
        }

        if ($company !== '' && $zip !== '') {
            $byCompany = (clone $query)
                ->where('company', $company)
                ->where('address_zip', $zip)
                ->first();
            if ($byCompany instanceof Customer) {
                return $byCompany;
            }
        }

        if ($company !== '') {
            $byCompany = (clone $query)->where('company', $company)->first();
            if ($byCompany instanceof Customer) {
                return $byCompany;
            }
        }

        if ($personName !== '') {
            $byName = (clone $query)->where('name', $personName)->first();
            if ($byName instanceof Customer) {
                return $byName;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function createFromRemote(Organization $organization, array $remote): ?Customer {
        $isCompany = ! empty(data_get($remote, 'company.name'));
        $name = $isCompany
            ? (string) data_get($remote, 'company.name')
            : trim(((string) data_get($remote, 'person.firstName', '')) . ' ' . ((string) data_get($remote, 'person.lastName', '')));

        if ($name === '') {
            return null;
        }

        return Customer::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'company' => $isCompany ? $name : null,
            'vat_id' => $this->extractVatId($remote) ?: null,
            'email' => $this->extractEmail($remote) ?: null,
            'phone' => (string) data_get($remote, 'phoneNumbers.business.0', '') ?: null,
            'address_street' => (string) data_get($remote, 'addresses.billing.0.street', '') ?: null,
            'address_zip' => (string) data_get($remote, 'addresses.billing.0.zip', '') ?: null,
            'address_city' => (string) data_get($remote, 'addresses.billing.0.city', '') ?: null,
            'country' => (string) data_get($remote, 'addresses.billing.0.countryCode', 'DE'),
            'currency' => 'EUR',
            'billable' => true,
        ]);
    }

    /**
     * Wendet Remote-Felder gemäß Policy auf den lokalen Kunden an.
     *
     * @param  array<string, mixed>  $remote
     */
    private function applyRemote(Customer $customer, array $remote, LexofficeMatchPolicy $policy, string $externalId): bool {
        if ($policy === LexofficeMatchPolicy::LocalWins || $policy === LexofficeMatchPolicy::ManualReview) {
            // Nur Snapshot in ExternalReference aktualisieren, keine Customer-Felder anfassen.
            ExternalReference::query()
                ->where('organization_id', $customer->organization_id)
                ->where('plugin_id', LexofficePlugin::ID)
                ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
                ->where('external_id', $externalId)
                ->update([
                    'payload' => $remote,
                    'synced_at' => now(),
                ]);

            return false;
        }

        $changes = $this->buildChangesFromRemote($remote);
        if ($changes === []) {
            return false;
        }

        $customer->fill($changes)->save();

        ExternalReference::query()
            ->where('organization_id', $customer->organization_id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('external_id', $externalId)
            ->update([
                'payload' => $remote,
                'synced_at' => now(),
            ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function hasConflict(Customer $customer, array $remote): bool {
        return $this->diffFields($customer, $remote) !== [];
    }

    /**
     * Liste der Felder, in denen sich lokaler Kunde und Remote-Snapshot unterscheiden.
     *
     * @param  array<string, mixed>  $remote
     * @return array<int, string>
     */
    private function diffFields(Customer $customer, array $remote): array {
        $remoteVals = $this->buildChangesFromRemote($remote);
        $diff = [];
        foreach ($remoteVals as $field => $value) {
            $local = (string) ($customer->{$field} ?? '');
            if ($local !== (string) $value) {
                $diff[] = $field;
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function recordConflict(Customer $customer, array $remote, string $externalId, Organization $organization): void {
        $diff = $this->diffFields($customer, $remote);
        if ($diff === []) {
            return;
        }

        PendingExternalConflict::query()->updateOrCreate(
            [
                'plugin_id' => LexofficePlugin::ID,
                'conflict_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
                'external_id' => $externalId,
                'status' => PendingExternalConflict::STATUS_OPEN,
            ],
            [
                'organization_id' => $organization->id,
                'local_snapshot' => $customer->only(array_keys($this->buildChangesFromRemote($remote))),
                'remote_snapshot' => $remote,
                'diff_fields' => $diff,
            ],
        );
    }

    /**
     * Übersetzt das Remote-JSON in das workDiary-Customer-Schema.
     *
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    private function buildChangesFromRemote(array $remote): array {
        $isCompany = ! empty(data_get($remote, 'company.name'));
        $name = $isCompany
            ? (string) data_get($remote, 'company.name')
            : trim(((string) data_get($remote, 'person.firstName', '')) . ' ' . ((string) data_get($remote, 'person.lastName', '')));

        $out = array_filter([
            'name' => $name !== '' ? $name : null,
            'company' => $isCompany ? (string) data_get($remote, 'company.name') : null,
            'vat_id' => $this->extractVatId($remote) ?: null,
            'email' => $this->extractEmail($remote) ?: null,
            'phone' => (string) data_get($remote, 'phoneNumbers.business.0', '') ?: null,
            'address_street' => (string) data_get($remote, 'addresses.billing.0.street', '') ?: null,
            'address_zip' => (string) data_get($remote, 'addresses.billing.0.zip', '') ?: null,
            'address_city' => (string) data_get($remote, 'addresses.billing.0.city', '') ?: null,
            'country' => (string) data_get($remote, 'addresses.billing.0.countryCode', '') ?: null,
        ], static fn($v) => $v !== null);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function extractVatId(array $remote): string {
        $vat = (string) data_get($remote, 'company.vatRegistrationId', '');
        if ($vat !== '') {
            return $vat;
        }

        return (string) data_get($remote, 'company.taxNumber', '');
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function extractEmail(array $remote): string {
        $mails = (array) data_get($remote, 'emailAddresses.business', []);

        return (string) ($mails[0] ?? data_get($remote, 'emailAddresses.private.0', ''));
    }
}
