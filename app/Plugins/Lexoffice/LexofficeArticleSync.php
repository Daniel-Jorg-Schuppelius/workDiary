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

use App\Models\{LexofficeArticle, Organization, PendingExternalConflict};
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Synchronisiert Lexoffice-Artikel (Services/Produkte) in die lokale Tabelle
 * `lexoffice_articles` und unterstützt bidirektionalen Sync:
 *  - sync(): Pull mit optionaler {@see LexofficeMatchPolicy} für Konflikte
 *  - push(): einzelnen lokalen Artikel (POST/PUT) zu Lexoffice senden
 *  - pushAllDirty(): alle als is_dirty markierten Artikel pushen
 *
 * Verwendet den HTTP-Client direkt, da das verwendete SDK keinen
 * Articles-Endpunkt anbietet.
 *
 * Quelle: https://developers.lexoffice.io/docs/#articles-endpoint
 */
class LexofficeArticleSync {
    private LexofficeMatchPolicy $policy = LexofficeMatchPolicy::LexofficeWins;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {
    }

    public function withPolicy(LexofficeMatchPolicy $policy): self {
        $clone = clone $this;
        $clone->policy = $policy;

        return $clone;
    }

    /**
     * @return array{created: int, updated: int, archived: int, conflicts: int}
     */
    public function sync(Organization $organization): array {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $seen = [];
        $created = 0;
        $updated = 0;
        $conflicts = 0;
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
            $items = (array) ($body['content'] ?? []);

            foreach ($items as $item) {
                if (! isset($item['id'])) {
                    continue;
                }
                $external = (string) $item['id'];
                $seen[] = $external;

                $attrs = $this->itemToAttrs($item);

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

                    continue;
                }

                if ($existing->is_dirty && $this->policy === LexofficeMatchPolicy::ManualReview) {
                    $diff = $this->diffArticle($existing, $attrs);
                    if ($diff !== []) {
                        $this->recordArticleConflict($existing, $attrs, $external, $organization, $diff);
                        $conflicts++;
                    }

                    continue;
                }

                if ($existing->is_dirty && $this->policy === LexofficeMatchPolicy::LocalWins) {
                    // Lokale Änderungen warten auf Push: nur Versions-Snapshot aktualisieren.
                    $existing->forceFill([
                        'external_version' => $attrs['external_version'],
                        'synced_at' => now(),
                    ])->save();

                    continue;
                }

                $existing->fill($attrs)->save();
                $updated++;
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
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Sendet einen lokalen Artikel zu Lexoffice. Wenn external_id leer ist
     * wird POST verwendet (Neuanlage), sonst PUT (Update). Lokale `is_dirty`
     * wird zurückgesetzt und external_version wird aus der Antwort übernommen.
     */
    public function push(LexofficeArticle $article): void {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $payload = $this->articleToPayload($article);

        if ($article->external_id === '') {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post($this->baseUrl . '/articles', $payload);
        } else {
            // Beim PUT muss die aktuelle Version mitgesendet werden (optimistic locking).
            $version = $article->external_version ?? $this->fetchRemoteVersion($article->external_id);
            $payload['version'] = $version;
            $payload['id'] = $article->external_id;
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->put($this->baseUrl . '/articles/' . $article->external_id, $payload);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Lexoffice articles push failed: ' . $response->status() . ' ' . $response->body());
        }

        $body = (array) ($response->json() ?? []);
        $article->forceFill([
            'external_id' => (string) ($body['id'] ?? $article->external_id),
            'external_version' => isset($body['version']) ? (int) $body['version'] : ($article->external_version + 1),
            'is_dirty' => false,
            'last_pushed_at' => now(),
            'synced_at' => now(),
        ])->save();
    }

    /**
     * @return array{pushed: int, failed: int}
     */
    public function pushAllDirty(Organization $organization): array {
        $pushed = 0;
        $failed = 0;

        LexofficeArticle::query()
            ->where('organization_id', $organization->id)
            ->where('is_dirty', true)
            ->whereNull('archived_at')
            ->chunk(100, function ($articles) use (&$pushed, &$failed): void {
                foreach ($articles as $article) {
                    try {
                        $this->push($article);
                        $pushed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        return ['pushed' => $pushed, 'failed' => $failed];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function itemToAttrs(array $item): array {
        $price = (array) ($item['price'] ?? []);

        return [
            'external_version' => isset($item['version']) ? (int) $item['version'] : null,
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
    }

    /**
     * @return array<string, mixed>
     */
    private function articleToPayload(LexofficeArticle $article): array {
        return array_filter([
            'title' => $article->name,
            'description' => $article->description,
            'type' => $article->type ?: 'service',
            'articleNumber' => $article->article_number,
            'unitName' => $article->unit_name,
            'price' => array_filter([
                'netPrice' => $article->net_unit_price !== null ? (float) $article->net_unit_price : null,
                'currency' => $article->currency ?: 'EUR',
                'taxRate' => $article->vat_rate !== null ? (float) $article->vat_rate : null,
                'leadingPrice' => 'NET',
            ], static fn($v) => $v !== null),
        ], static fn($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array<int, string>
     */
    private function diffArticle(LexofficeArticle $local, array $remote): array {
        $fields = ['name', 'article_number', 'description', 'type', 'unit_name', 'net_unit_price', 'currency', 'vat_rate'];
        $diff = [];
        foreach ($fields as $f) {
            $a = (string) ($local->{$f} ?? '');
            $b = (string) ($remote[$f] ?? '');
            if ($a !== $b) {
                $diff[] = $f;
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  array<int, string>  $diff
     */
    private function recordArticleConflict(LexofficeArticle $local, array $remote, string $external, Organization $organization, array $diff): void {
        PendingExternalConflict::query()->updateOrCreate(
            [
                'plugin_id' => LexofficePlugin::ID,
                'conflict_type' => 'article',
                'referenceable_type' => $local->getMorphClass(),
                'referenceable_id' => $local->getKey(),
                'external_id' => $external,
                'status' => PendingExternalConflict::STATUS_OPEN,
            ],
            [
                'organization_id' => $organization->id,
                'local_snapshot' => $local->only(['name', 'article_number', 'description', 'type', 'unit_name', 'net_unit_price', 'currency', 'vat_rate']),
                'remote_snapshot' => $remote,
                'diff_fields' => $diff,
            ],
        );
    }

    private function fetchRemoteVersion(string $externalId): int {
        $response = Http::withToken((string) $this->apiKey)
            ->acceptJson()
            ->get($this->baseUrl . '/articles/' . $externalId);

        if (! $response->successful()) {
            return 0;
        }

        return (int) ($response->json('version') ?? 0);
    }
}
