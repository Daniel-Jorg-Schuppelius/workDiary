<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Models\Domain\DomainProviderConnection;
use App\Plugins\Contracts\Domain\{DomainProviderAdapter, DomainRegistrarSettings};
use App\Plugins\Contracts\{DomainRegistrar, Plugin};
use App\Plugins\PluginManager;
use RuntimeException;

/**
 * Löst den providerneutralen {@see DomainProviderAdapter} einer Verbindung
 * über die Plugin-Registry auf (Feature 083). Analog zu
 * {@see \App\Services\CloudIntake\CloudIntakeRunner::resolveAdapter()}: die
 * Services bleiben providerneutral, die konkrete Fähigkeit kommt aus dem
 * {@see DomainRegistrar}-Plugin.
 */
class DomainProviderResolver {
    public function __construct(private readonly PluginManager $plugins) {}

    public function for(DomainProviderConnection $connection): DomainProviderAdapter {
        return $this->registrar()->domainAdapter($connection);
    }

    public function settings(int $organizationId): DomainRegistrarSettings {
        return $this->registrar()->domainSettings($organizationId);
    }

    /** Plugin-ID des Registrars — Kennung der Domain-Zuordnungen in `external_references`. */
    public function pluginId(): string {
        return $this->registrar()->id();
    }

    /** Registrar über den Vertrag, unabhängig von der Aktivierung (MVP-1043). */
    private function registrar(): DomainRegistrar&Plugin {
        $plugin = $this->plugins->all()->first(static fn (Plugin $plugin): bool => $plugin instanceof DomainRegistrar);
        if (! $plugin instanceof DomainRegistrar) {
            throw new RuntimeException('Kein DomainRegistrar-Plugin verfügbar.');
        }

        return $plugin;
    }
}
