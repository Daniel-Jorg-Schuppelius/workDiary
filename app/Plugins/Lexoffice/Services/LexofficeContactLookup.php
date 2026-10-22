<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeContactLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Exceptions\ApiException;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Plugins\Lexoffice\LexofficePlugin;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\ContactsEndpoint;

/**
 * Lexoffice-Kontakt eines Kunden für einen ausgehenden Beleg: bestehender
 * Nachweis, sonst die Kontaktsuche per E-Mail — der Treffer wird als Nachweis
 * gemerkt. Stand wortgleich in Übergabeziel, Auftragsbelegen und Lieferschein
 * (Konsolidierungs-Audit 2026-10, k2-02). Die Suche läuft über den Client des
 * Aufrufers, damit sie mit dessen Beleg-POST gedrosselt bleibt.
 */
class LexofficeContactLookup {
    public function find(Customer $customer, Client $client): ?string {
        $existing = ExternalReference::query()
            ->forPlugin($customer->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($customer)
            ->first();

        if ($existing !== null) {
            return $existing->external_id;
        }

        $email = (string) $customer->email;
        if ($email === '') {
            return null;
        }

        try {
            $first = (new ContactsEndpoint($client))->search(['email' => $email, 'page' => 0, 'size' => 1])->getValues()[0] ?? null;
        } catch (ApiException) {
            return null;
        }

        $id = (string) ($first?->toArray()['id'] ?? '');
        if ($id === '') {
            return null;
        }

        ExternalReference::updateOrCreate(
            [
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                'referenceable_type' => $customer->getMorphClass(),
                'referenceable_id' => $customer->getKey(),
            ],
            [
                'organization_id' => $customer->organization_id,
                'external_id' => $id,
                'synced_at' => now(),
            ],
        );

        return $id;
    }
}
