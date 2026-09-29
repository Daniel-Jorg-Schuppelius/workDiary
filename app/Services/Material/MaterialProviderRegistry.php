<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialProviderRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Material;

use App\Models\Material\Material;
use App\Services\Material\Provider\LocalMaterialProvider;
use Closure;
use Illuminate\Support\Collection;

class MaterialProviderRegistry {
    /** @var array<string, Closure(): ?MaterialProviderInterface> */
    protected array $factories = [];

    public function __construct() {
        $this->register('local', static fn (): MaterialProviderInterface => new LocalMaterialProvider);
    }

    /**
     * Plugins tragen ihre Quelle beim Booten ein (MVP-1033). Die Fabrik läuft
     * bei jedem Zugriff im aktuellen Organisationskontext und liefert null,
     * solange die Quelle dort nicht eingerichtet ist.
     *
     * @param  Closure(): ?MaterialProviderInterface  $factory
     */
    public function register(string $name, Closure $factory): void {
        $this->factories[$name] = $factory;
    }

    public function get(string $name): ?MaterialProviderInterface {
        return isset($this->factories[$name]) ? ($this->factories[$name])() : null;
    }

    /** @return array<int, string> eingerichtete Quellen */
    public function names(): array {
        return array_keys($this->providers());
    }

    /**
     * Aggregierte Suche über alle aktiven Provider (lokaler Cache zuerst).
     *
     * @return Collection<int, Material>
     */
    public function searchAll(string $query, int $limit = 20): Collection {
        $results = collect();
        foreach ($this->providers() as $provider) {
            foreach ($provider->search($query, $limit) as $material) {
                $key = $material->external_provider . ':' . ($material->external_id ?? $material->id);
                if (! $results->has($key)) {
                    $results->put($key, $material);
                }
            }
        }

        return $results->values();
    }

    /** @return array<string, MaterialProviderInterface> */
    private function providers(): array {
        $providers = [];
        foreach ($this->factories as $name => $factory) {
            $provider = $factory();
            if ($provider !== null) {
                $providers[$name] = $provider;
            }
        }

        return $providers;
    }
}
