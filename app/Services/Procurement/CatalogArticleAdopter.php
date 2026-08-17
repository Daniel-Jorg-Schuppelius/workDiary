<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogArticleAdopter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Enums\Procurement\CatalogItemStatus;
use App\Models\{Article, ArticleOptionDefinition, ArticleOptionValue, ArticleVariant, Organization, SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Article\{ArticleService, VariantResolver};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Übernimmt unverknüpfte Katalogartikel als Dienstleistungs-Artikel in den
 * Artikelstamm (Feature 050, MVP-541). Gruppiert nach Produkttarif
 * (Hersteller-Nr., sonst Name): je Gruppe EIN Artikel, je Angebots-Item mit
 * Zusatzattributen (z. B. Laufzeit/Zahlungsintervall) EINE Variante mit
 * SKU = externe Artikelnummer (Offer-Key). Varianten tragen eigene EK-/VK-
 * Preise und wirken über den SKU-Match der externen Syncs (VariantMatcher,
 * ExternalArticleMapping) als eigenständige Artikel.
 */
class CatalogArticleAdopter {
    public function __construct(
        private readonly ArticleService $articles = new ArticleService(),
        private readonly VariantResolver $variants = new VariantResolver(),
        private readonly CatalogLinkService $links = new CatalogLinkService(),
        private readonly PriceSuggestionService $pricing = new PriceSuggestionService(),
    ) {}

    /**
     * Übernimmt alle übernehmbaren Gruppen der Quelle. Jede Gruppe läuft in
     * einer eigenen Transaktion — ein Konflikt stoppt den Massenlauf nicht.
     *
     * @return array{articles: int, variants: int, linked: int, skipped: int, errors: list<string>}
     */
    public function adoptSource(SupplierCatalogSource $source): array {
        $summary = $this->emptySummary();

        $groups = $this->adoptableItems($source)->groupBy(fn (SupplierCatalogItem $i): string => $this->groupKey($i));
        $articleMap = $this->linkedGroupArticles($source);

        foreach ($groups as $key => $group) {
            $this->adoptItems($source, array_values($group->all()), $articleMap[$key] ?? null, $summary);
        }

        return $summary;
    }

    /**
     * Übernimmt die komplette Tarif-Gruppe des übergebenen Items.
     *
     * @return array{articles: int, variants: int, linked: int, skipped: int, errors: list<string>}
     */
    public function adoptGroup(SupplierCatalogSource $source, SupplierCatalogItem $seed): array {
        $summary = $this->emptySummary();
        $key = $this->groupKey($seed);

        $items = array_values($this->adoptableItems($source)
            ->filter(fn (SupplierCatalogItem $i): bool => $this->groupKey($i) === $key)
            ->all());

        $this->adoptItems($source, $items, $this->linkedGroupArticles($source)[$key] ?? null, $summary);

        return $summary;
    }

    /**
     * Vorschau für den Übernahme-Dialog.
     *
     * @return array{items: int, groups: int}
     */
    public function countAdoptable(SupplierCatalogSource $source): array {
        $items = $this->adoptableItems($source);

        return [
            'items' => $items->count(),
            'groups' => $items->groupBy(fn (SupplierCatalogItem $i): string => $this->groupKey($i))->count(),
        ];
    }

    /** @return array{articles: int, variants: int, linked: int, skipped: int, errors: list<string>} */
    private function emptySummary(): array {
        return ['articles' => 0, 'variants' => 0, 'linked' => 0, 'skipped' => 0, 'errors' => []];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, SupplierCatalogItem> */
    private function adoptableItems(SupplierCatalogSource $source): \Illuminate\Database\Eloquent\Collection {
        return $source->items()
            ->whereNull('article_id')
            ->whereIn('status', [CatalogItemStatus::New->value, CatalogItemStatus::Proposed->value])
            ->orderBy('external_no')
            ->get();
    }

    /**
     * Varianten-relevante Attribute: die reservierten DATANORM-Metadaten
     * (`datanorm_*` — Rohstoffzuschläge, Arbeitszeiten, Vormerkungen) sind
     * KEINE Optionsmerkmale und dürfen keine Varianten erzeugen (Feature 107).
     *
     * @return array<string, mixed>
     */
    private function variantAttributes(SupplierCatalogItem $item): array {
        return array_filter(
            (array) $item->extra_attributes,
            static fn ($value, $key): bool => ! str_starts_with((string) $key, 'datanorm_'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** Montagezeit (Zweck 2) aus den DATANORM-Arbeitszeiten, sonst erste Zeit. */
    private function assemblyMinutes(SupplierCatalogItem $item): ?string {
        $workTimes = (array) (((array) $item->extra_attributes)['datanorm_worktimes'] ?? []);
        $fallback = null;
        foreach ($workTimes as $workTime) {
            if (! is_array($workTime) || ! isset($workTime['minutes'])) {
                continue;
            }
            $minutes = (float) $workTime['minutes'];
            if ((int) ($workTime['purpose'] ?? 0) === 2) {
                return (string) $minutes;
            }
            $fallback ??= (string) $minutes;
        }

        return $fallback;
    }

    /** MwSt-Kennzeichen (MVP-601): ermäßigt/erhöht aus dem DATANORM-Satz. */
    private function taxClass(SupplierCatalogItem $item): ?string {
        return match (((array) $item->extra_attributes)['datanorm_vat'] ?? null) {
            'reduced' => 'ermäßigt',
            'increased' => 'erhöht',
            default => null,
        };
    }

    /** Tarif-Gruppe: Hersteller-Nr. (CSP-Produkt), sonst Name (Domains u. ä.). */
    private function groupKey(SupplierCatalogItem $item): string {
        $manufacturerNo = trim((string) $item->manufacturer_no);

        return $manufacturerNo !== '' ? 'm:' . $manufacturerNo : 'n:' . mb_strtolower(trim($item->name));
    }

    /**
     * Zielartikel bereits verknüpfter Gruppen (Nachzügler-Angebote nach einem
     * Re-Import landen am bestehenden Artikel statt in einem Duplikat).
     *
     * @return array<string, Article>
     */
    private function linkedGroupArticles(SupplierCatalogSource $source): array {
        $map = [];
        $linked = $source->items()->whereNotNull('article_id')->with('article')->get();
        foreach ($linked as $item) {
            $article = $item->article;
            if ($article instanceof Article) {
                $map[$this->groupKey($item)] ??= $article;
            }
        }

        return $map;
    }

    /**
     * @param  list<SupplierCatalogItem>  $items
     * @param  array{articles: int, variants: int, linked: int, skipped: int, errors: list<string>}  $summary
     */
    private function adoptItems(SupplierCatalogSource $source, array $items, ?Article $article, array &$summary): void {
        if ($items === []) {
            return;
        }

        try {
            DB::transaction(function () use ($source, $items, $article, &$summary): void {
                /** @var Organization $organization */
                $organization = Organization::query()->findOrFail($source->organization_id);
                $withAttrs = array_values(array_filter($items, fn (SupplierCatalogItem $i): bool => $this->variantAttributes($i) !== []));
                $plainItems = array_values(array_filter($items, fn (SupplierCatalogItem $i): bool => $this->variantAttributes($i) === []));

                if ($article === null) {
                    $first = $items[0];
                    $plainSingle = $withAttrs === [] && count($plainItems) === 1;
                    // Aktiv statt Draft: nur aktive Artikel wirken nach außen
                    // (Lexoffice-Sync u. a.) als eigenständige Artikel.
                    $article = $this->articles->createArticle($organization, [
                        'name' => $first->name,
                        'type' => ArticleType::Service,
                        'status' => ArticleStatus::Active,
                        'sellable' => true,
                        'purchasable' => true,
                        'stockable' => false,
                        'currency' => $first->currency ?? \CommonToolkit\Enums\CurrencyCode::Euro,
                        'gtin' => $plainSingle ? ($first->gtin ?: null) : null,
                        'default_purchase_price' => $plainSingle ? $first->purchase_price?->getAmount() : null,
                        'default_sale_price' => $plainSingle ? $this->salePrice($first) : null,
                        // MVP-565: DATANORM-Montagezeit (ARBA) in die Kalkulationsbasis.
                        'assembly_minutes' => $plainSingle ? $this->assemblyMinutes($first) : null,
                        // MVP-601: MwSt-Kennzeichen aus dem DATANORM-A-/B-Satz —
                        // der ermäßigte/erhöhte Satz geht nicht mehr verloren.
                        'tax_class' => $plainSingle ? $this->taxClass($first) : null,
                    ]);
                    $summary['articles']++;
                }

                // Angebote ohne Attribute hängen direkt am Artikel (keine Variante).
                foreach ($plainItems as $item) {
                    $this->links->link($item, $article, null);
                    $summary['linked']++;
                }

                if ($withAttrs === []) {
                    return;
                }

                $definitions = $this->ensureOptionDefinitions($article, $withAttrs);

                // EK absteigend: der letzte link() setzt die Bezugsquelle
                // (ArticleSupply, unique je Artikel+Lieferant) aufs günstigste
                // Angebot; die Angebotspreise selbst liegen an den Varianten.
                usort($withAttrs, fn (SupplierCatalogItem $a, SupplierCatalogItem $b): int => bccomp(
                    $b->purchase_price?->getAmount() ?? '0',
                    $a->purchase_price?->getAmount() ?? '0',
                    CatalogItemUpserter::SCALE,
                ));

                $hasDefault = $article->variants()->where('is_default', true)->exists();
                foreach ($withAttrs as $item) {
                    $created = $this->adoptVariant($article, $definitions, $item, $hasDefault, $summary);
                    $hasDefault = $hasDefault || $created;
                }
            });
        } catch (Throwable $e) {
            $summary['skipped'] += count($items);
            $summary['errors'][] = $e->getMessage();
        }
    }

    /**
     * @param  array<string, ArticleOptionDefinition>  $definitions
     * @param  array{articles: int, variants: int, linked: int, skipped: int, errors: list<string>}  $summary
     * @return bool ob eine neue Variante entstanden ist
     */
    private function adoptVariant(Article $article, array $definitions, SupplierCatalogItem $item, bool $hasDefault, array &$summary): bool {
        // Org-weite SKU-Kollision (article_variant_sku_unique): überspringen
        // statt Transaktionsabbruch — der Offer-Key gehört schon jemand anderem.
        $skuTaken = ArticleVariant::query()
            ->where('organization_id', $article->organization_id)
            ->where('sku', $item->external_no)
            ->where('article_id', '!=', $article->id)
            ->exists();
        if ($skuTaken) {
            $summary['skipped']++;
            $summary['errors'][] = (string) __('procurement.catalog.adopt.error.sku_conflict', ['sku' => $item->external_no]);

            return false;
        }

        $valueIds = [];
        foreach ($this->variantAttributes($item) as $code => $value) {
            $definition = $definitions[$code] ?? null;
            if ($definition === null) {
                continue;
            }
            $valueIds[] = ArticleOptionValue::query()->firstOrCreate(
                ['article_option_definition_id' => $definition->id, 'code' => $this->valueCode((string) $value)],
                ['label' => (string) $value],
            )->id;
        }

        $values = ArticleOptionValue::query()->with('definition')->whereIn('id', $valueIds)->get();
        $signature = $this->variants->signature($values);
        $existing = $article->variants()->where('option_signature', $signature)->first();
        if ($existing instanceof ArticleVariant) {
            if (trim((string) $existing->sku) === trim((string) $item->external_no)) {
                $this->links->link($item, $article, $existing);
                $summary['linked']++;
            } else {
                $summary['skipped']++;
                $summary['errors'][] = (string) __('procurement.catalog.adopt.error.combination_exists', [
                    'sku' => $item->external_no, 'existing' => (string) $existing->sku,
                ]);
            }

            return false;
        }

        $variant = $this->variants->createVariant($article, $valueIds, [
            'sku' => $item->external_no,
            'gtin' => $item->gtin ?: null,
            'purchase_price' => $item->purchase_price?->getAmount(),
            'sale_price' => $this->salePrice($item),
            'currency' => ($item->currency ?? \CommonToolkit\Enums\CurrencyCode::Euro)->value,
            'status' => ArticleStatus::Active->value,
            'is_default' => ! $hasDefault,
        ]);
        $this->links->link($item, $article, $variant);
        $summary['variants']++;
        $summary['linked']++;

        return true;
    }

    /**
     * Options-Definitionen der Gruppe (Union der Attribut-Codes, idempotent).
     *
     * @param  list<SupplierCatalogItem>  $items
     * @return array<string, ArticleOptionDefinition>
     */
    private function ensureOptionDefinitions(Article $article, array $items): array {
        $codes = [];
        foreach ($items as $item) {
            foreach (array_keys($this->variantAttributes($item)) as $code) {
                $codes[(string) $code] = true;
            }
        }

        $definitions = [];
        foreach (array_keys($codes) as $position => $code) {
            $definitions[$code] = ArticleOptionDefinition::query()->firstOrCreate(
                ['article_id' => $article->id, 'code' => $code],
                ['name' => Str::ucfirst(str_replace('_', ' ', $code)), 'position' => $position],
            );
        }

        return $definitions;
    }

    /** VK-Vorschlag: Hersteller-UVP der Preisliste, sonst Margenregel (MVP-095). */
    private function salePrice(SupplierCatalogItem $item): ?string {
        if ($item->list_price !== null) {
            return $item->list_price->getAmount();
        }

        return $this->pricing->suggestForItem($item)['price'] ?? null;
    }

    /** Options-Wert-Code im Format der Definition-Codes (Slug, ≤40). */
    private function valueCode(string $value): string {
        $code = mb_substr(Str::slug($value, '_'), 0, 40);

        return $code !== ''
            ? $code
            : substr(\CommonToolkit\Helper\Data\CryptoHelper::hash($value, \CommonToolkit\Enums\HashAlgorithm::SHA1), 0, 12);
    }
}
