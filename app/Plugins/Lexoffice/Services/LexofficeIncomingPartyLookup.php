<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeIncomingPartyLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Services\Invoicing\Contracts\IncomingPartyLookup;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;
use App\Services\Invoicing\EInvoice\IncomingInvoiceParties;
use Illuminate\Support\Facades\Cache;

/** Kontaktsuche in Lexware Office für den Zuordnen-Dialog (Phase 152, MVP-1226): E-Mail und Name. */
final class LexofficeIncomingPartyLookup implements IncomingPartyLookup {
    public function key(): string {
        return LexofficePlugin::ID;
    }

    public function label(): string {
        return (string) __('lexoffice::incoming.label');
    }

    public function isEnabled(Organization $organization): bool {
        $config = LexofficeConfig::resolve((int) $organization->id);

        return $config['enabled'] && is_string($config['api_key']) && $config['api_key'] !== '';
    }

    public function lookup(IncomingEInvoice $incoming): array {
        $organizationId = (int) $incoming->organization_id;
        $config = LexofficeConfig::resolve($organizationId);
        $service = new LexofficeService(
            apiKey: $config['api_key'],
            mapper: new LexofficeMapper,
            defaults: $config['defaults'],
            baseUrl: $config['base_url'],
            requestInterval: $config['request_interval'],
        );
        $fields = app(IncomingInvoiceParties::class)->fieldsOf($incoming);
        // Unter der API-Sperre der Organisation wie Sync-Läufe (Befund 2026-10-17); wartet der Dialog zu lang, gibt es keine Treffer statt eines Limits.
        $rows = Cache::lock(LexofficeConfig::apiLockKey($organizationId), 60)
            ->block(LexofficeConfig::API_LOCK_WAIT_SHORT, static fn (): array => $service->findContacts($fields['email'], $fields['name']));

        return array_values(array_filter(array_map($this->candidate(...), $rows)));
    }

    /** @param  array<string, mixed>  $contact */
    private function candidate(array $contact): ?IncomingPartyCandidate {
        $id = (string) ($contact['id'] ?? '');
        $company = trim((string) data_get($contact, 'company.name', ''));
        $person = trim(((string) data_get($contact, 'person.firstName', '')) . ' ' . ((string) data_get($contact, 'person.lastName', '')));
        $name = $company !== '' ? $company : $person;
        if ($id === '' || $name === '') {
            return null;
        }
        $number = (string) (data_get($contact, 'roles.vendor.number') ?? data_get($contact, 'roles.customer.number') ?? '');
        $mails = (array) data_get($contact, 'emailAddresses.business', []);
        $email = (string) ($mails[0] ?? data_get($contact, 'emailAddresses.private.0', ''));
        $billing = (array) data_get($contact, 'addresses.billing.0', []);
        $vat = trim((string) data_get($contact, 'company.vatRegistrationId', ''));

        return new IncomingPartyCandidate(
            pluginId: LexofficePlugin::ID,
            pluginLabel: $this->label(),
            externalId: $id,
            name: $name,
            number: $number !== '' ? $number : null,
            vatId: $vat !== '' ? $vat : null,
            email: $email !== '' ? $email : null,
            street: self::text($billing['street'] ?? null),
            zip: self::text($billing['zip'] ?? null),
            city: self::text($billing['city'] ?? null),
            country: self::text($billing['countryCode'] ?? null),
        );
    }

    private static function text(mixed $value): ?string {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
