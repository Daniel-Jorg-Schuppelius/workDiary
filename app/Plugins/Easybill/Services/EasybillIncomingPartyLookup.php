<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillIncomingPartyLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\{EasybillConfig, EasybillPlugin};
use App\Plugins\PluginManager;
use App\Services\Invoicing\Contracts\IncomingPartyLookup;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;
use App\Services\Invoicing\EInvoice\IncomingInvoiceParties;

/**
 * Kontaktsuche in easybill (Phase 152, MVP-1226): `GET /customers` filtert
 * E-Mail und Firmenname exakt; Lieferanten führt easybill als Kunden.
 */
final class EasybillIncomingPartyLookup implements IncomingPartyLookup {
    public function key(): string {
        return EasybillPlugin::ID;
    }

    public function label(): string {
        return app(PluginManager::class)->get(EasybillPlugin::ID)?->name() ?? 'easybill';
    }

    public function isEnabled(Organization $organization): bool {
        $config = EasybillConfig::resolve((int) $organization->id);

        return $config['enabled'] && is_string($config['api_key']) && $config['api_key'] !== '';
    }

    public function lookup(IncomingEInvoice $incoming): array {
        $client = app(EasybillClientFactory::class)->for((int) $incoming->organization_id);
        $fields = app(IncomingInvoiceParties::class)->fieldsOf($incoming);
        $rows = [];
        if ($fields['email'] !== null) {
            $rows = $client->customersByEmail($fields['email']);
        }
        if ($fields['name'] !== null) {
            $rows = [...$rows, ...$client->customersByCompanyName($fields['name'])];
        }
        $candidates = [];
        foreach ($rows as $row) {
            $candidate = is_array($row) ? $this->candidate($row) : null;
            if ($candidate !== null) {
                $candidates[$candidate->externalId] = $candidate;
            }
        }

        return array_values($candidates);
    }

    /** @param  array<string, mixed>  $row */
    private function candidate(array $row): ?IncomingPartyCandidate {
        $id = (string) ($row['id'] ?? '');
        $name = trim((string) ($row['company_name'] ?? '')) ?: trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
        if ($id === '' || $name === '') {
            return null;
        }
        $emails = (array) ($row['emails'] ?? []);

        return new IncomingPartyCandidate(
            pluginId: EasybillPlugin::ID,
            pluginLabel: $this->label(),
            externalId: $id,
            name: $name,
            number: self::text($row['number'] ?? null),
            vatId: self::text($row['vat_identifier'] ?? null),
            email: self::text($emails[0] ?? null),
            street: self::text($row['street'] ?? null),
            zip: self::text($row['zip_code'] ?? null),
            city: self::text($row['city'] ?? null),
            country: self::text($row['country'] ?? null),
        );
    }

    private static function text(mixed $value): ?string {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
