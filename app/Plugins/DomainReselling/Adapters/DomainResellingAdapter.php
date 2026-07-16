<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellingAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\DomainReselling\Adapters;

use App\Enums\Domain\DomainCapabilityArea;
use App\Models\Domain\DomainProviderConnection;
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Plugins\DomainReselling\Api\DomainResellingClient;
use App\Plugins\Support\Domain\{DomainCapabilityBlockedException, DomainCapabilityMatrix, DomainResponse};

/**
 * Providerneutraler Adapter über den {@see DomainResellingClient} (Feature 083).
 * Fügt Capability-Gating (nicht belegte Bereiche → Blocked-State) und die
 * Read/Write-Unterscheidung (Retry-Verhalten) hinzu.
 */
class DomainResellingAdapter implements DomainProviderAdapter {
    private DomainCapabilityMatrix $matrix;

    public function __construct(
        private readonly DomainResellingClient $client,
        DomainProviderConnection $connection,
    ) {
        $this->matrix = DomainCapabilityMatrix::fromStored($connection->capabilities);
    }

    public function execute(string $command, array $params = [], ?DomainCapabilityArea $area = null): DomainResponse {
        $area ??= self::areaFor($command);
        if ($area !== null && ! $this->matrix->allows($area)) {
            throw new DomainCapabilityBlockedException($area);
        }

        return $this->client->call($command, $params, self::isMutating($command));
    }

    public function capabilities(): DomainCapabilityMatrix {
        return $this->matrix;
    }

    /** Ordnet einen Provider-Befehl seinem Fähigkeitsbereich zu. */
    private static function areaFor(string $command): ?DomainCapabilityArea {
        $c = strtolower($command);

        return match (true) {
            str_contains($c, 'authentication'), str_contains($c, 'authorization') => DomainCapabilityArea::Authentication,
            str_contains($c, 'user') => DomainCapabilityArea::Subuser,
            str_contains($c, 'contact') => DomainCapabilityArea::Contacts,
            str_contains($c, 'nameserver') => DomainCapabilityArea::Nameservers,
            str_contains($c, 'zone'), str_contains($c, 'dns') => DomainCapabilityArea::Dns,
            str_contains($c, 'event') => DomainCapabilityArea::Events,
            str_contains($c, 'transfer') => DomainCapabilityArea::Transfer,
            str_contains($c, 'renew') => DomainCapabilityArea::Renewal,
            str_contains($c, 'accounting') => DomainCapabilityArea::Accounting,
            str_contains($c, 'invoice') => DomainCapabilityArea::Invoices,
            str_contains($c, 'domain') => DomainCapabilityArea::Domains,
            default => null,
        };
    }

    /** Schreibende Befehle (kein Transport-Retry ohne Idempotenz). */
    private static function isMutating(string $command): bool {
        foreach (['add', 'modify', 'delete', 'create', 'update', 'set', 'trade', 'push', 'renew', 'transfer', 'assign', 'clone'] as $verb) {
            if (str_starts_with(strtolower($command), $verb)) {
                return true;
            }
        }

        return false;
    }
}
