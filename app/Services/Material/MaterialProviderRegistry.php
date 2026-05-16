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

use App\Models\Material;
use App\Services\Material\Provider\LexofficeMaterialProvider;
use App\Services\Material\Provider\LocalMaterialProvider;
use Illuminate\Support\Collection;

class MaterialProviderRegistry {
    /** @var array<string, MaterialProviderInterface> */
    protected array $providers = [];

    public function __construct() {
        $this->register(new LocalMaterialProvider);

        $key = (string) config('timesheet.providers.lexoffice.api_key', '');
        if ($key !== '') {
            $this->register(new LexofficeMaterialProvider($key));
        }
    }

    public function register(MaterialProviderInterface $provider): void {
        $this->providers[$provider->name()] = $provider;
    }

    public function get(string $name): ?MaterialProviderInterface {
        return $this->providers[$name] ?? null;
    }

    /** @return array<int, string> */
    public function names(): array {
        return array_keys($this->providers);
    }

    /**
     * Aggregierte Suche über alle aktiven Provider (lokaler Cache zuerst).
     *
     * @return Collection<int, Material>
     */
    public function searchAll(string $query, int $limit = 20): Collection {
        $results = collect();
        foreach ($this->providers as $provider) {
            foreach ($provider->search($query, $limit) as $material) {
                $key = $material->external_provider . ':' . ($material->external_id ?? $material->id);
                if (! $results->has($key)) {
                    $results->put($key, $material);
                }
            }
        }

        return $results->values();
    }
}
