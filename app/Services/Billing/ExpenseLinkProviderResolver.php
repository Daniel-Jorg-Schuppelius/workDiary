<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseLinkProviderResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Plugins\PluginManager;
use App\Services\Billing\Contracts\ExpenseLinkProvider;
use Closure;

/**
 * Welcher {@see ExpenseLinkProvider} trägt die Auslagen-Belege dieser
 * Organisation (Vollscan 2026-08-23, B9)?
 *
 * Gleiche Registry-Mechanik wie {@see \App\Services\Inventory\InventoryProviderResolver}
 * und die {@see Feed\DocumentFeedSourceRegistry}: das Plugin registriert seine
 * Factory beim Booten, der Kern kennt keine Plugin-Klasse. Die Auswahl folgt
 * dem aktivierten Buchhaltungs-Plugin der Organisation — {@see PluginManager::enabled()}
 * ist bereits org-gescopt (PluginOrgContext). Ist keines aktiv, kommt der
 * {@see NullExpenseLinkProvider} zurück; nie null, damit kein Aufrufer prüfen muss.
 */
class ExpenseLinkProviderResolver {
    /** @var array<string, Closure(): ExpenseLinkProvider> */
    private array $factories = [];

    /**
     * Registriert die Provider-Factory eines Plugins (idempotent je Plugin-ID).
     *
     * @param  Closure(): ExpenseLinkProvider  $factory
     */
    public function register(string $pluginId, Closure $factory): void {
        $this->factories[$pluginId] = $factory;
    }

    public function has(string $pluginId): bool {
        return isset($this->factories[$pluginId]);
    }

    /**
     * Provider der aktuellen Organisation (nie null).
     *
     * Der {@see PluginManager} wird bewusst erst hier aufgelöst: sein Aufbau
     * instanziiert ALLE Plugins — beim Booten des Lexoffice-Providers (der
     * diese Registry befüllt) wäre das eine Rekursionsfalle.
     */
    public function current(): ExpenseLinkProvider {
        $enabled = app(PluginManager::class)->enabled();

        foreach ($this->factories as $pluginId => $factory) {
            if ($enabled->contains(fn ($plugin): bool => $plugin->id() === $pluginId)) {
                return $factory();
            }
        }

        return app(NullExpenseLinkProvider::class);
    }
}
