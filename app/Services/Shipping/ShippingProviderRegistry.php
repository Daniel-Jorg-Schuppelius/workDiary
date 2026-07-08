<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShippingProviderRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Shipping;

use App\Plugins\Contracts\ShippingProvider;

/**
 * Laufzeit-Registry der Versand-Provider (Feature 059, MVP-128). Jedes
 * Carrier-Plugin registriert seinen {@see ShippingProvider} im boot() gegen den
 * Carrier-Schlüssel (z. B. `dhl`); der {@see ShipmentService} löst darüber den
 * passenden Adapter zur {@see \App\Models\CarrierConnection} auf. Als Singleton
 * gebunden, damit die Registrierungen prozessweit sichtbar sind.
 */
class ShippingProviderRegistry {
    /** @var array<string, ShippingProvider> */
    private array $providers = [];

    public function register(ShippingProvider $provider): void {
        $this->providers[strtolower($provider->carrier())] = $provider;
    }

    public function for(string $carrier): ?ShippingProvider {
        return $this->providers[strtolower($carrier)] ?? null;
    }

    /** @return list<string> */
    public function carriers(): array {
        return array_keys($this->providers);
    }
}
