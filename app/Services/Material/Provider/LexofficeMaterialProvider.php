<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMaterialProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Material\Provider;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\Material;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Material\MaterialProviderInterface;
use Illuminate\Support\Collection;

/**
 * Lexoffice-Artikel als Material-Provider.
 *
 * Doku: https://developers.lexoffice.io/docs/#articles-endpoint-articles
 */
class LexofficeMaterialProvider implements MaterialProviderInterface {
    private ?PluginApiClient $api = null;

    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    public function name(): string {
        return 'lexoffice';
    }

    /**
     * @return Collection<int, Material>
     */
    public function search(string $query, int $limit = 20): Collection {
        if ($this->apiKey === '') {
            return collect();
        }

        $response = $this->api()->getResponse($this->baseUrl . '/articles', [
            'query' => $query,
            'size' => $limit,
        ]);

        if (! $response->ok()) {
            return collect();
        }

        $items = (array) ($response->json('content') ?? []);
        $models = collect();

        foreach ($items as $item) {
            $models->push($this->upsert($item));
        }

        return $models;
    }

    public function sync(): int {
        if ($this->apiKey === '') {
            return 0;
        }

        $count = 0;
        $page = 0;

        do {
            $response = $this->api()->getResponse($this->baseUrl . '/articles', ['page' => $page, 'size' => 100]);
            if (! $response->ok()) {
                break;
            }
            $items = (array) ($response->json('content') ?? []);
            foreach ($items as $item) {
                $this->upsert($item);
                $count++;
            }
            $page++;
            $isLast = (bool) ($response->json('last') ?? true);
        } while (! $isLast);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function upsert(array $item): Material {
        $price = data_get($item, 'price.netPrice');
        $tax = data_get($item, 'price.taxRate');

        return Material::query()->updateOrCreate(
            [
                'external_provider' => $this->name(),
                'external_id' => (string) ($item['id'] ?? ''),
            ],
            [
                'sku' => (string) ($item['articleNumber'] ?? null) ?: null,
                'name' => (string) ($item['title'] ?? 'Artikel'),
                'unit' => (string) ($item['unitName'] ?? 'Stk.'),
                'default_unit_price' => $price !== null ? (string) $price : null,
                'tax_rate' => $tax !== null ? (string) $tax : null,
                'is_active' => true,
            ],
        );
    }

    protected function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('lexoffice', $this->baseUrl);
            $this->api->setAuthentication(new BearerAuthentication($this->apiKey));
        }

        return $this->api;
    }
}
