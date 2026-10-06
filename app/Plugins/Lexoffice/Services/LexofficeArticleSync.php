<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeArticleSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Integration\ExternalArticleSyncStatus;
use App\Enums\Integration\{ExternalConflictStatus, IntegrationInboxStatus};
use App\Models\Article\ArticleVariant;
use App\Models\Integration\{ExternalArticleMapping, IntegrationInboxItem, PendingExternalConflict};
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy;
use App\Plugins\Lexoffice\Exceptions\LexofficeApiException;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Plugins\Support\PluginApiClient;
use App\Services\Inventory\VariantMatcher;
use App\Support\MorphMap;
use CommonToolkit\ValueObjects\{Money, Percentage};
use RuntimeException;

/**
 * Synchronisiert Lexoffice-Artikel (Services/Produkte) in die lokale Tabelle
 * `lexoffice_articles` und unterstützt bidirektionalen Sync:
 *  - sync(): Pull mit optionaler {@see LexofficeMatchPolicy} für Konflikte
 *  - adoptRemote(): einzelnen Artikel frisch aus Lexoffice übernehmen (Konfliktauflösung)
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
    /** Inhaltsfelder, die Konflikterkennung und Konflikt-Schnappschuss vergleichen. */
    private const DIFF_FIELDS = ['name', 'article_number', 'gtin', 'description', 'note', 'type', 'unit_name', 'net_unit_price', 'gross_unit_price', 'currency', 'vat_rate', 'leading_price'];

    private LexofficeMatchPolicy $policy = LexofficeMatchPolicy::LexofficeWins;

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
        private readonly ?float $requestInterval = null,
    ) {}

    private function api(): PluginApiClient {
        return $this->api ??= app(LexofficeClientFactory::class)->make((string) $this->apiKey, $this->baseUrl, $this->requestInterval);
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
                    $local = $this->comparable($existing);
                    $remote = $this->comparable((new LexofficeArticle)->fill(array_intersect_key($attrs, array_flip(self::DIFF_FIELDS))));
                    $diff = array_keys(array_filter($local, static fn (?string $value, string $field): bool => (string) $value !== (string) $remote[$field], ARRAY_FILTER_USE_BOTH));
                    if ($diff !== []) {
                        $this->recordArticleConflict($existing, $local, $remote + ['external_version' => $attrs['external_version']], $external, $organization, $diff);
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
                    'target_type' => MorphMap::alias(ArticleVariant::class),
                    'external_type' => 'article',
                    'external_id' => $external,
                    'case_type' => IntegrationInboxItem::CASE_AMBIGUOUS,
                    'status' => IntegrationInboxStatus::Open,
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
                'sync_status' => ExternalArticleSyncStatus::Linked,
                'last_synced_at' => now(),
            ],
        );

        // Offene Inbox-Fälle zu diesem Artikel sind damit erledigt.
        IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('dedupe_key', LexofficePlugin::ID . ':article:' . $external)
            ->where('status', IntegrationInboxStatus::Open)
            ->update(['status' => IntegrationInboxStatus::ResolvedLinked, 'resolved_at' => now()]);

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
     * Holt einen Artikel frisch aus Lexoffice und übernimmt seinen Stand in die
     * Projektion — die Antwort „Lexoffice-Stand übernehmen“ auf einen
     * Artikelkonflikt bei manueller Prüfung (Entscheidung 2026-10-06). Die
     * lokale Änderung ist damit verworfen, der Artikel nicht mehr dirty.
     *
     * @throws RuntimeException Ohne API-Schlüssel oder external_id; bei Fehlern von Lexoffice als {@see LexofficeApiException}.
     */
    public function adoptRemote(LexofficeArticle $article): LexofficeArticle {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }
        if ($article->external_id === '') {
            throw new RuntimeException('Lexoffice article has no external id to adopt.');
        }

        $response = $this->api()->getResponse($this->baseUrl . '/articles/' . $article->external_id);
        if (! $response->successful()) {
            throw LexofficeApiException::fromResponse($response, __('Artikel'), __('Artikel abrufen'));
        }

        /** @var array<string, mixed> $item */
        $item = (array) ($response->json() ?? []);
        $article->fill($this->itemToAttrs($item) + ['is_dirty' => false])->save();

        return $article;
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
     * Inhaltsfelder in kanonischer Form. Beide Seiten laufen durch die Casts
     * des Modells — sonst stünde „100" (API) gegen „100.0000 EUR" (Wertobjekt)
     * und jeder Artikel mit Preis gälte als abweichend.
     *
     * @return array<string, string|null>
     */
    private function comparable(LexofficeArticle $article): array {
        $values = [];
        foreach (self::DIFF_FIELDS as $field) {
            $value = $article->getAttribute($field);
            $values[$field] = match (true) {
                $value === null => null,
                $value instanceof Money => $value->getAmount(),
                $value instanceof Percentage => $value->getNumericValue(),
                $value instanceof \BackedEnum => (string) $value->value,
                default => (string) $value,
            };
        }

        return $values;
    }

    /**
     * Beide Schnappschüsse tragen dieselben Felder in kanonischer Form, damit
     * die Konfliktliste sie ohne das Plugin nebeneinander zeigen kann.
     *
     * @param  array<string, string|null>  $localSnapshot
     * @param  array<string, mixed>  $remoteSnapshot
     * @param  list<string>  $diff
     */
    private function recordArticleConflict(LexofficeArticle $local, array $localSnapshot, array $remoteSnapshot, string $external, Organization $organization, array $diff): void {
        PendingExternalConflict::query()->updateOrCreate(
            [
                'plugin_id' => LexofficePlugin::ID,
                'conflict_type' => PendingExternalConflict::TYPE_ARTICLE,
                'referenceable_type' => $local->getMorphClass(),
                'referenceable_id' => $local->getKey(),
                'external_id' => $external,
                'status' => ExternalConflictStatus::Open,
            ],
            [
                'organization_id' => $organization->id,
                'local_snapshot' => $localSnapshot,
                'remote_snapshot' => $remoteSnapshot,
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
