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

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\{ArticleVariant, ExternalArticleMapping, IntegrationInboxItem, LexofficeArticle, Organization, PendingExternalConflict};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Inventory\VariantMatcher;
use RuntimeException;

/**
 * Synchronisiert Lexoffice-Artikel (Services/Produkte) in die lokale Tabelle
 * `lexoffice_articles` und unterstützt bidirektionalen Sync:
 *  - sync(): Pull mit optionaler {@see LexofficeMatchPolicy} für Konflikte
 *  - push(): einzelnen lokalen Artikel (POST/PUT) zu Lexoffice senden
 *  - pushAllDirty(): alle als is_dirty markierten Artikel pushen
 *
 * Zusätzlich verknüpft sync() jeden Lexoffice-Artikel über den
 * {@see VariantMatcher} (SKU/Artikelnummer primär, eindeutige GTIN als
 * systemübergreifende Brücke — Feature 048/078) mit dem lokalen
 * Artikelstamm ({@see ExternalArticleMapping}, plugin_id `lexoffice`).
 * Mehrdeutige GTIN-Treffer landen in der Integrations-Inbox; Artikel ohne
 * lokalen Stammsatz bleiben bewusst reine Projektion (z. B.
 * Dienstleistungen) — anders als bei JTL blockiert hier nichts.
 *
 * Verwendet den HTTP-Client direkt, da das verwendete SDK keinen
 * Articles-Endpunkt anbietet.
 *
 * Quelle: https://developers.lexoffice.io/docs/#articles-endpoint
 */
class LexofficeArticleSync {
    private LexofficeMatchPolicy $policy = LexofficeMatchPolicy::LexofficeWins;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client('lexoffice', $this->baseUrl);
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiKey));
        }

        return $this->api;
    }

    public function withPolicy(LexofficeMatchPolicy $policy): self {
        $clone = clone $this;
        $clone->policy = $policy;

        return $clone;
    }

    /**
     * @return array{created: int, updated: int, archived: int, conflicts: int, linked: int, ambiguous: int}
     */
    public function sync(Organization $organization): array {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $seen = [];
        $created = 0;
        $updated = 0;
        $conflicts = 0;
        $linked = 0;
        $ambiguous = 0;
        $page = 0;
        $pageSize = 100;

        do {
            $response = $this->api()
                ->getResponse($this->baseUrl . '/articles', [
                    'page' => $page,
                    'size' => $pageSize,
                ]);

            if (! $response->successful()) {
                throw LexofficeApiException::fromResponse($response, __('Artikel'), __('Artikel abrufen'));
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

                // Stammdaten-Brücke (GTIN/SKU) — unabhängig von der
                // Inhalts-Konfliktpolitik der Projektion.
                $outcome = $this->linkToLocalVariant($organization, $external, $attrs);
                if ($outcome === 'linked') {
                    $linked++;
                } elseif ($outcome === 'ambiguous') {
                    $ambiguous++;
                }

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
            'linked' => $linked,
            'ambiguous' => $ambiguous,
        ];
    }

    /**
     * Verknüpft einen Lexoffice-Artikel über SKU/GTIN mit dem lokalen
     * Artikelstamm. Kein Treffer ⇒ bewusst kein Inbox-Fall (reine
     * Projektion bleibt zulässig); mehrdeutige GTIN ⇒ Inbox.
     *
     * @param  array<string, mixed>  $attrs
     * @return 'linked'|'ambiguous'|null
     */
    private function linkToLocalVariant(Organization $organization, string $external, array $attrs): ?string {
        $sku = trim((string) ($attrs['article_number'] ?? ''));
        $gtin = trim((string) ($attrs['gtin'] ?? ''));
        if ($sku === '' && $gtin === '') {
            return null;
        }

        $match = app(VariantMatcher::class)->match((int) $organization->id, $sku, $gtin);

        if ($match['ambiguous']) {
            IntegrationInboxItem::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'dedupe_key' => LexofficePlugin::ID . ':article:' . $external,
                ],
                [
                    'plugin_id' => LexofficePlugin::ID,
                    'source' => LexofficePlugin::ID,
                    'target_type' => 'article_variant',
                    'external_type' => 'article',
                    'external_id' => $external,
                    'case_type' => IntegrationInboxItem::CASE_AMBIGUOUS,
                    'status' => IntegrationInboxItem::STATUS_OPEN,
                    'display_title' => trim((string) ($attrs['name'] ?? '')) !== '' ? (string) $attrs['name'] : $external,
                    'display_subtitle' => trim('SKU ' . ($sku !== '' ? $sku : '-') . ' · GTIN ' . ($gtin !== '' ? $gtin : '-')),
                    'remote_snapshot' => [
                        'id' => $external,
                        'articleNumber' => $sku,
                        'gtin' => $gtin,
                        'name' => (string) ($attrs['name'] ?? ''),
                    ],
                    'occurred_at' => now(),
                ],
            );

            return 'ambiguous';
        }

        $variant = $match['variant'];
        if (! $variant instanceof ArticleVariant) {
            return null;
        }

        ExternalArticleMapping::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => LexofficePlugin::ID,
                'external_id' => $external,
            ],
            [
                'article_id' => $variant->article_id,
                'article_variant_id' => $variant->id,
                'external_parent_id' => null,
                'external_number' => $sku !== '' ? mb_substr($sku, 0, 64) : null,
                'sync_status' => 'linked',
                'last_synced_at' => now(),
            ],
        );

        // Offene Inbox-Fälle zu diesem Artikel sind damit erledigt.
        IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('dedupe_key', LexofficePlugin::ID . ':article:' . $external)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->update(['status' => IntegrationInboxItem::STATUS_RESOLVED_LINKED, 'resolved_at' => now()]);

        return 'linked';
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
            $response = $this->api()->postJson($this->baseUrl . '/articles', $payload);
        } else {
            // Beim PUT muss die aktuelle Version mitgesendet werden (optimistic locking).
            $version = $article->external_version ?? $this->fetchRemoteVersion($article->external_id);
            $payload['version'] = $version;
            $payload['id'] = $article->external_id;
            $response = $this->api()->putJson($this->baseUrl . '/articles/' . $article->external_id, $payload);
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
            'gtin' => isset($item['gtin']) ? (string) $item['gtin'] : null,
            'description' => isset($item['description']) ? (string) $item['description'] : null,
            'note' => isset($item['note']) ? (string) $item['note'] : null,
            'type' => (string) ($item['type'] ?? 'service'),
            'unit_name' => isset($item['unitName']) ? (string) $item['unitName'] : null,
            'net_unit_price' => isset($price['netPrice']) ? (string) $price['netPrice'] : null,
            'gross_unit_price' => isset($price['grossPrice']) ? (string) $price['grossPrice'] : null,
            'currency' => (string) ($price['currency'] ?? 'EUR'),
            'vat_rate' => isset($price['taxRate']) ? (string) $price['taxRate'] : null,
            'leading_price' => isset($price['leadingPrice']) ? (string) $price['leadingPrice'] : null,
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
            'gtin' => $article->gtin,
            'note' => $article->note,
            'unitName' => $article->unit_name,
            'price' => array_filter([
                'netPrice' => $article->net_unit_price?->toFloat(),
                'grossPrice' => $article->gross_unit_price?->toFloat(),
                'currency' => $article->currency->value,
                'taxRate' => $article->vat_rate !== null ? (float) $article->vat_rate->getNumericValue() : null,
                'leadingPrice' => $article->leading_price ?: 'NET',
            ], static fn($v) => $v !== null),
        ], static fn($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array<int, string>
     */
    private function diffArticle(LexofficeArticle $local, array $remote): array {
        $fields = ['name', 'article_number', 'gtin', 'description', 'note', 'type', 'unit_name', 'net_unit_price', 'gross_unit_price', 'currency', 'vat_rate', 'leading_price'];
        $diff = [];
        foreach ($fields as $f) {
            $localValue = $local->{$f};
            if ($localValue instanceof \BackedEnum) {
                $localValue = $localValue->value;
            }
            $a = (string) ($localValue ?? '');
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
        $response = $this->api()->getResponse($this->baseUrl . '/articles/' . $externalId);

        if (! $response->successful()) {
            return 0;
        }

        return (int) ($response->json('version') ?? 0);
    }
}
