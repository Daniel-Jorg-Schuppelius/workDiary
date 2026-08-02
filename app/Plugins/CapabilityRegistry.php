<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapabilityRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Plugins\Contracts\{PluginCapability, PluginCapabilityContract};
use InvalidArgumentException;

/**
 * Registry aller bekannten Plugin-Fähigkeiten (Review 2026-08, W5e).
 *
 * Die Kern-Fähigkeiten (Enum {@see PluginCapability}) sind immer enthalten;
 * externe Plugins registrieren zusätzliche Fähigkeiten in ihrem
 * ServiceProvider: `app(CapabilityRegistry::class)->register(new MyCapability)`.
 * Singleton via {@see \App\Providers\PluginServiceProvider}.
 */
final class CapabilityRegistry {
    /** @var array<string, PluginCapabilityContract> identifier → Capability */
    private array $registered = [];

    public function register(PluginCapabilityContract $capability): void {
        $identifier = $capability->identifier();
        if ($this->find($identifier) !== null) {
            throw new InvalidArgumentException(sprintf(
                'Capability "%s" ist bereits registriert.',
                $identifier,
            ));
        }
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException(sprintf(
                'Capability-Identifier "%s" ist ungültig (erwartet ^[a-z][a-z0-9_]*$).',
                $identifier,
            ));
        }
        $this->registered[$identifier] = $capability;
    }

    /** @return list<PluginCapabilityContract> Kern-Enum + registrierte Erweiterungen. */
    public function all(): array {
        return [...PluginCapability::cases(), ...array_values($this->registered)];
    }

    public function find(string $identifier): ?PluginCapabilityContract {
        foreach (PluginCapability::cases() as $case) {
            if ($case->identifier() === $identifier) {
                return $case;
            }
        }

        return $this->registered[$identifier] ?? null;
    }
}
