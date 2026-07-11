<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryProviderResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryProvider;
use App\Enums\Inventory\InventoryMode;
use App\Models\Organization;
use Closure;
use RuntimeException;

/**
 * Löst die Bestandsführerschaft und den aktiven Provider je Organisation auf
 * (Feature 048, MVP-066 / Feature 078, MVP-319) – analog
 * {@see \App\Services\Finance\BillingModeResolver}. Kaskade:
 * organizations.settings['inventory_mode'] → Fallback `local`.
 *
 * Externe Provider registrieren sich als Factory je Plugin-ID (Singleton-
 * Registry, Registrierung im Plugin-ServiceProvider-Boot — Mechanik wie
 * {@see ExternalInventoryDispatcherResolver}). Bei `read_only` wird der
 * externe Provider in den {@see ReadOnlyInventoryProvider} gehüllt, der
 * Schreib-Capabilities ausblendet.
 */
class InventoryProviderResolver {
    /** @var array<string, Closure(Organization): InventoryProvider> */
    private array $external = [];

    public function __construct(private readonly LocalInventoryProvider $local) {}

    /**
     * Registriert die Provider-Factory eines Plugins (idempotent je Plugin-ID;
     * letzte Registrierung gewinnt — relevant nur für Tests).
     *
     * @param  Closure(Organization): InventoryProvider  $factory
     */
    public function registerExternal(string $pluginId, Closure $factory): void {
        $this->external[$pluginId] = $factory;
    }

    public function hasExternal(string $pluginId): bool {
        return isset($this->external[$pluginId]);
    }

    public function modeFor(Organization $organization): InventoryMode {
        $setting = data_get($organization->settings, 'inventory_mode');
        if (is_string($setting) && $setting !== '') {
            $mode = InventoryMode::tryFrom($setting);
            if ($mode instanceof InventoryMode) {
                return $mode;
            }
        }

        return InventoryMode::Local;
    }

    public function providerFor(Organization $organization): InventoryProvider {
        return match ($this->modeFor($organization)) {
            InventoryMode::Local => $this->local,
            InventoryMode::External => $this->externalFor($organization),
            InventoryMode::ReadOnly => new ReadOnlyInventoryProvider($this->externalFor($organization)),
        };
    }

    private function externalFor(Organization $organization): InventoryProvider {
        $pluginId = (string) data_get($organization->settings, 'inventory_plugin_id', '');

        if ($pluginId === '') {
            throw new RuntimeException(
                'Externe Bestandsführung ist aktiviert, aber kein Bestands-Plugin gewählt (settings.inventory_plugin_id).'
            );
        }

        $factory = $this->external[$pluginId] ?? null;
        if ($factory === null) {
            throw new RuntimeException(
                sprintf('Für das Bestands-Plugin „%s“ ist kein Provider registriert — ist das Plugin aktiviert?', $pluginId)
            );
        }

        return $factory($organization);
    }
}
