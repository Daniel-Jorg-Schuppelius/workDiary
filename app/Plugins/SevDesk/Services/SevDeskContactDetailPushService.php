<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskContactDetailPushService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\{Customer, ExternalReference};
use App\Plugins\SevDesk\Api\SevDeskClient;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\Accounting\ContactPushService;

/**
 * Adressen und Kommunikationswege des sevDesk-Kontakt-Push (Feature 122,
 * MVP-731 — Vollscan G18).
 *
 * sevDesk führt beides **nicht** als Felder des Kontakts, sondern als eigene
 * Objekte mit eigenem Endpunkt (`/ContactAddress`, `/CommunicationWay`).
 * Deshalb konnte MVP-611 den Kontakt anlegen, aber weder Anschrift noch
 * E-Mail/Telefon mitschicken — genau die Lücke schließt dieser Service.
 *
 * **Idempotenz über external_id:** Jedes angelegte Objekt bekommt eine
 * {@see ExternalReference} (`contact_address`, `communication_way_email`,
 * `communication_way_phone`). Beim nächsten Lauf wird aktualisiert (PUT),
 * nicht neu angelegt — sonst sammelt der Kontakt bei jedem Push eine weitere
 * Adresse an. Gelöscht wird im Fremdsystem nie.
 *
 * **Eigene Stammdaten** bleiben draußen: Die E-Mail der eigenen Organisation
 * geht nie an einen Fremdkontakt ({@see ContactPushService::withoutOwnIdentity()}) —
 * derselbe Fehler, der im Lexoffice-Betrieb Fehl-Matches erzeugt hat.
 *
 * **Katalog-IDs** (Adresskategorie, Land, CommunicationWayKey) sind
 * Standardwerte aus `config/plugins.sevdesk.*` und im Pilot gegen den
 * Account zu verifizieren — sie unterscheiden sich je Mandant.
 */
class SevDeskContactDetailPushService {
    public const TYPE_ADDRESS = 'contact_address';

    public const TYPE_EMAIL = 'communication_way_email';

    public const TYPE_PHONE = 'communication_way_phone';

    public function __construct(private readonly ContactPushService $contacts) {}

    /**
     * Anschrift + Kommunikationswege eines bereits gepushten Kontakts
     * nachziehen.
     *
     * @return array{address: bool, email: bool, phone: bool}
     */
    public function push(SevDeskClient $client, Customer $customer, string $contactId): array {
        $pushed = ['address' => false, 'email' => false, 'phone' => false];
        if (trim($contactId) === '') {
            return $pushed;
        }

        $contact = ['id' => $contactId, 'objectName' => 'Contact'];

        $pushed['address'] = $this->pushAddress($client, $customer, $contact);

        $fields = $this->contacts->withoutOwnIdentity([
            'email' => (string) ($customer->email ?? ''),
        ]);
        $pushed['email'] = $this->pushCommunicationWay(
            $client,
            $customer,
            $contact,
            self::TYPE_EMAIL,
            'EMAIL',
            trim((string) ($fields['email'] ?? '')),
        );
        $pushed['phone'] = $this->pushCommunicationWay(
            $client,
            $customer,
            $contact,
            self::TYPE_PHONE,
            'PHONE',
            trim((string) ($customer->phone ?? '')),
        );

        return $pushed;
    }

    /** @param array{id: string, objectName: string} $contact */
    private function pushAddress(SevDeskClient $client, Customer $customer, array $contact): bool {
        $street = trim((string) ($customer->address_street ?? ''));
        $zip = trim((string) ($customer->address_zip ?? ''));
        $city = trim((string) ($customer->address_city ?? ''));
        if ($street === '' && $zip === '' && $city === '') {
            // Keine Anschrift ist kein Fehler — eine leere Adresse anzulegen
            // wäre einer.
            return false;
        }

        $payload = array_filter([
            'contact' => $contact,
            'street' => $street !== '' ? $street : null,
            'zip' => $zip !== '' ? $zip : null,
            'city' => $city !== '' ? $city : null,
            'country' => ['id' => (int) config('plugins.sevdesk.address_country_id', 1), 'objectName' => 'StaticCountry'],
            'category' => ['id' => (int) config('plugins.sevdesk.address_category_id', 47), 'objectName' => 'Category'],
        ], static fn ($value): bool => $value !== null);

        $existing = $this->referenceId($customer, self::TYPE_ADDRESS);
        $result = $existing !== null
            ? $client->updateContactAddress($existing, $payload)
            : $client->createContactAddress($payload);

        return $this->remember($customer, self::TYPE_ADDRESS, (string) ($result['id'] ?? $existing ?? ''));
    }

    /** @param array{id: string, objectName: string} $contact */
    private function pushCommunicationWay(
        SevDeskClient $client,
        Customer $customer,
        array $contact,
        string $externalType,
        string $type,
        string $value,
    ): bool {
        if ($value === '') {
            return false;
        }

        $payload = [
            'contact' => $contact,
            'type' => $type,
            'value' => $value,
            'key' => ['id' => (int) config('plugins.sevdesk.communication_way_key_id', 1), 'objectName' => 'CommunicationWayKey'],
            'main' => true,
        ];

        $existing = $this->referenceId($customer, $externalType);
        $result = $existing !== null
            ? $client->updateCommunicationWay($existing, $payload)
            : $client->createCommunicationWay($payload);

        return $this->remember($customer, $externalType, (string) ($result['id'] ?? $existing ?? ''));
    }

    private function referenceId(Customer $customer, string $externalType): ?string {
        $reference = ExternalReference::query()
            ->forPlugin($customer->organization_id, SevDeskPlugin::ID, $externalType)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first();

        $id = $reference instanceof ExternalReference ? trim((string) $reference->external_id) : '';

        return $id !== '' ? $id : null;
    }

    private function remember(Customer $customer, string $externalType, string $externalId): bool {
        if (trim($externalId) === '') {
            return false;
        }

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => SevDeskPlugin::ID,
                'external_type' => $externalType,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $customer->organization_id,
                'external_id' => $externalId,
                'synced_at' => now(),
            ],
        );

        return true;
    }
}
