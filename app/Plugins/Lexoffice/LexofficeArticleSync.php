<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticleSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{LexofficeArticle, Organization};
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Synchronisiert Lexoffice-Artikel (Services/Produkte) in die lokale Tabelle
 * `lexoffice_articles`. Verwendet den HTTP-Client direkt, da das verwendete
 * SDK keinen Articles-Endpunkt anbietet.
 *
 * Quelle: https://developers.lexoffice.io/docs/#articles-endpoint
 */
class LexofficeArticleSync {
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    /**
     * @return array{created: int, updated: int, archived: int}
     */
    public function sync(Organization $organization): array {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $seen = [];
        $created = 0;
        $updated = 0;
        $page = 0;
        $pageSize = 100;

        do {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->get($this->baseUrl . '/articles', [
                    'page' => $page,
                    'size' => $pageSize,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Lexoffice articles request failed: ' . $response->status() . ' ' . $response->body());
            }

            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];
            $items = $body['content'] ?? [];

            foreach ($items as $item) {
                if (! isset($item['id'])) {
                    continue;
                }
                $external = (string) $item['id'];
                $seen[] = $external;

                $price = $item['price'] ?? [];

                $attrs = [
                    'name' => (string) ($item['title'] ?? $item['name'] ?? ''),
                    'article_number' => isset($item['articleNumber']) ? (string) $item['articleNumber'] : null,
                    'description' => isset($item['description']) ? (string) $item['description'] : null,
                    'type' => (string) ($item['type'] ?? 'service'),
                    'unit_name' => isset($item['unitName']) ? (string) $item['unitName'] : null,
                    'net_unit_price' => isset($price['netPrice']) ? (string) $price['netPrice'] : null,
                    'currency' => (string) ($price['currency'] ?? 'EUR'),
                    'vat_rate' => isset($price['taxRate']) ? (string) $price['taxRate'] : null,
                    'synced_at' => now(),
                    'archived_at' => null,
                ];

                $existing = LexofficeArticle::query()
                    ->where('organization_id', $organization->id)
                    ->where('external_id', $external)
                    ->first();

                if ($existing === null) {
                    LexofficeArticle::create($attrs + [
                        'organization_id' => $organization->id,
                        'external_id' => $external,
                    ]);
                    $created++;
                } else {
                    $existing->fill($attrs)->save();
                    $updated++;
                }
            }

            $totalPages = (int) ($body['totalPages'] ?? 1);
            $page++;
        } while ($page < $totalPages);

        // Verschwundene Artikel als archiviert markieren.
        $archived = LexofficeArticle::query()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->when($seen, fn($q) => $q->whereNotIn('external_id', $seen))
            ->update(['archived_at' => now()]);

        return [
            'created' => $created,
            'updated' => $updated,
            'archived' => (int) $archived,
        ];
    }
}
