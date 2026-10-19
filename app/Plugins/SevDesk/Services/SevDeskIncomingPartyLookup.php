<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskIncomingPartyLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Plugins\PluginManager;
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Services\Invoicing\Contracts\IncomingPartyLookup;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;
use App\Services\Invoicing\EInvoice\IncomingInvoiceParties;

/**
 * Kontaktsuche in sevDesk (Phase 152, MVP-1226): `GET /Contact?name=`; die
 * Kontaktliste trägt weder E-Mail noch Anschrift (E6).
 */
final class SevDeskIncomingPartyLookup implements IncomingPartyLookup {
    public function key(): string {
        return SevDeskPlugin::ID;
    }

    public function label(): string {
        return app(PluginManager::class)->get(SevDeskPlugin::ID)?->name() ?? 'sevDesk';
    }

    public function isEnabled(Organization $organization): bool {
        $config = SevDeskConfig::resolve((int) $organization->id);

        return $config['enabled'] && is_string($config['api_key']) && $config['api_key'] !== '';
    }

    public function lookup(IncomingEInvoice $incoming): array {
        $fields = app(IncomingInvoiceParties::class)->fieldsOf($incoming);
        if ($fields['name'] === null) {
            return [];
        }
        $candidates = [];
        foreach (app(SevDeskClientFactory::class)->for((int) $incoming->organization_id)->contactsByName($fields['name']) as $row) {
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
        $name = trim((string) ($row['name'] ?? '')) ?: trim(((string) ($row['surename'] ?? '')) . ' ' . ((string) ($row['familyname'] ?? '')));
        if ($id === '' || $name === '') {
            return null;
        }
        $number = trim((string) ($row['customerNumber'] ?? ''));
        $vat = trim((string) ($row['vatNumber'] ?? ''));

        return new IncomingPartyCandidate(
            pluginId: SevDeskPlugin::ID,
            pluginLabel: $this->label(),
            externalId: $id,
            name: $name,
            number: $number !== '' ? $number : null,
            vatId: $vat !== '' ? $vat : null,
        );
    }
}
