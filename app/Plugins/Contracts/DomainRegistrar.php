<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRegistrar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\Domain\DomainProviderConnection;
use App\Plugins\Contracts\Domain\DomainProviderAdapter;

/**
 * Contract der Fähigkeit {@see PluginCapability::DomainRegistrar} (Feature 083):
 * ein Plugin, das Domains bei einem Registrar-/Reseller-Provider projizieren
 * und kontrolliert verwalten kann. Die App-Services („Domain"-Modul) lösen den
 * providerneutralen {@see DomainProviderAdapter} über dieses Interface auf —
 * analog zu {@see DocumentIntakeSource} beim Cloud-Dokumenteingang.
 */
interface DomainRegistrar {
    /** Provider-Adapter für die gegebene, org-gebundene Verbindung. */
    public function domainAdapter(DomainProviderConnection $connection): DomainProviderAdapter;
}
