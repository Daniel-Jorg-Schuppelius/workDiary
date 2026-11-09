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

use APIToolkit\Exceptions\ApiException;
use App\Enums\Integration\{ExternalArticleSyncStatus, IntegrationInboxStatus};
use App\Models\Article\ArticleVariant;
use App\Models\Integration\{ExternalArticleMapping, IntegrationInboxItem};
use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Exceptions\{LexofficeApiException, LexofficeNotConfiguredException};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Services\Inventory\VariantMatcher;
use App\Support\MorphMap;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\ArticlesEndpoint;

/**
 * Synchronisiert Lexoffice-Artikel (Services/Produkte) in die lokale Tabelle
 * `lexoffice_articles` (nur lesend; Lexoffice führt den Artikel).
 *
 * Zusätzlich verknüpft sync() jeden Lexoffice-Artikel über den
 * {@see VariantMatcher} (SKU/Artikelnummer primär, eindeutige GTIN als
 * systemübergreifende Brücke — Feature 048/078) mit dem lokalen
 * Artikelstamm ({@see ExternalArticleMapping}, plugin_id `lexoffice`).
 * Mehrdeutige GTIN-Treffer landen in der Integrations-Inbox; Artikel ohne
 * lokalen Stammsatz bleiben bewusst reine Projektion (z. B.
 * Dienstleistungen) — anders als bei JTL blockiert hier nichts.
 *
 * Läuft über den Articles-Endpunkt des SDK; gelesen wird dessen Array-Form.
 *
 * Quelle: https://developers.lexware.io/docs/#articles-endpoint
 */
class LexofficeArticleSync {
    private ?Client $client = null;

    /** @param  int|null  $organizationId  null = gebundene Organisation bzw. Einzelplatz-Konfiguration */
    public function __construct(
        private readonly ?int $organizationId,
    ) {}

    private function client(): Client {
        return $this->client ??= app(LexofficeClientFactory::class)->for($this->organizationId);
    }

    /**
     * @return array{created: int, updated: int, archived: int, linked: int, ambiguous: int}
     */
    public function sync(Organization $organization): array {
        if (! app(LexofficeClientFactory::class)->isConfigured($this->organizationId)) {
            throw new LexofficeNotConfiguredException();
        }

        $seen = [];
        $created = 0;
        $updated = 0;
        $linked = 0;
        $ambiguous = 0;
        $page = 0;
        $pageSize = 100;

        $articles = new ArticlesEndpoint($this->client());

        do {
            try {
                $found = $articles->search([
                    'page' => $page,
                    'size' => $pageSize,
                ]);
            } catch (ApiException $e) {
                throw LexofficeApiException::fromApiException($e, __('Artikel'), __('Artikel abrufen'));
            }

            foreach ($found->getValues() as $remoteArticle) {
                $item = $remoteArticle->toArray();
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

                // Gleiche Lexoffice-Version: nichts zu schreiben (perf-07).
                if ($attrs['external_version'] !== null && (int) $existing->external_version === (int) $attrs['external_version']) {
                    continue;
                }

                $existing->fill($attrs)->save();
                $updated++;
            }

            $totalPages = $found->getTotalPages();
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
}
