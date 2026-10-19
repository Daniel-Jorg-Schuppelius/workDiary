<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxIncomingPartyLookup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Enums\Billing\DocumentDirection;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\PluginManager;
use App\Services\Invoicing\Contracts\IncomingPartyLookup;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;
use App\Services\Invoicing\EInvoice\IncomingInvoiceParties;
use Orgamax\API\Endpoints\{CustomersEndpoint, SuppliersEndpoint};

/**
 * Kontaktsuche in orgaMAX (Phase 152, MVP-1226): Eingangsbelege suchen bei
 * den Lieferanten (Name, Nummer, E-Mail — keine USt-IdNr. in der API),
 * Ausgangsbelege bei den Kunden.
 */
final class OrgaMaxIncomingPartyLookup implements IncomingPartyLookup {
    public function key(): string {
        return OrgaMaxPlugin::ID;
    }

    public function label(): string {
        return app(PluginManager::class)->get(OrgaMaxPlugin::ID)?->name() ?? 'orgaMAX';
    }

    public function isEnabled(Organization $organization): bool {
        $connection = OrgaMaxConnection::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->first();

        return $connection instanceof OrgaMaxConnection && $connection->isActive();
    }

    public function lookup(IncomingEInvoice $incoming): array {
        $connection = OrgaMaxConnection::query()->withoutGlobalScopes()->where('organization_id', $incoming->organization_id)->first();
        $fields = app(IncomingInvoiceParties::class)->fieldsOf($incoming);
        if (! $connection instanceof OrgaMaxConnection || $fields['name'] === null) {
            return [];
        }
        $client = app(OrgaMaxClientFactory::class)->for($connection);
        $query = ['search' => $fields['name'], 'limit' => 25];
        $candidates = [];
        if ($incoming->direction === DocumentDirection::Outgoing) {
            foreach ((new CustomersEndpoint($client))->search($query)?->getValues() ?? [] as $row) {
                $id = (string) $row->getId();
                $name = trim((string) ($row->getCompanyName() ?: $row->getName()));
                if ($id === '' || $name === '') {
                    continue;
                }
                $candidates[$id] = new IncomingPartyCandidate(OrgaMaxPlugin::ID, $this->label(), $id, $name, self::text($row->getNumber()), self::text($row->getVatNumber()), self::text($row->getEmail()));
            }
        } else {
            foreach ((new SuppliersEndpoint($client))->search($query)?->getValues() ?? [] as $row) {
                $id = (string) $row->getId();
                $name = trim((string) $row->getName());
                if ($id === '' || $name === '') {
                    continue;
                }
                $candidates[$id] = new IncomingPartyCandidate(OrgaMaxPlugin::ID, $this->label(), $id, $name, self::text($row->getNumber()), null, self::text($row->getEmail()));
            }
        }

        return array_values($candidates);
    }

    private static function text(mixed $value): ?string {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
