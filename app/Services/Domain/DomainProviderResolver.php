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
use App\Plugins\Contracts\Domain\DomainProviderAdapter;
use App\Plugins\Contracts\DomainRegistrar;
use App\Plugins\DomainReselling\DomainResellingPlugin;
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
        $plugin = $this->plugins->find(DomainResellingPlugin::ID);
        if (! $plugin instanceof DomainRegistrar) {
            throw new RuntimeException('Kein DomainRegistrar-Plugin verfügbar.');
        }

        return $plugin->domainAdapter($connection);
    }
}
