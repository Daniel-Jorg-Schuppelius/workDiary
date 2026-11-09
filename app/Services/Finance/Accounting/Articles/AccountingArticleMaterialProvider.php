<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingArticleMaterialProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Articles;

use App\Models\Finance\AccountingArticle;
use App\Models\Material\Material;
use App\Services\Material\MaterialProviderInterface;
use App\Services\Platform\Catalog\ArticleCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Materialsuche über den Artikelspiegel `accounting_articles` (MVP-1500):
 * die Artikel aller Programme, deren Katalogquelle für die Organisation
 * eingerichtet ist (sevDesk, easybill, orgaMAX, Invoice Ninja, Akaunting, InvoicePlane).
 * Wie der Lexoffice-Anbieter legt jeder Treffer einen lokalen Materialsatz
 * an bzw. frischt ihn auf (`external_provider` = Programm), damit die API
 * eine buchbare Material-ID liefert. Eine Artikelnummer, die ein anderes
 * Material der Organisation schon trägt, bleibt am Satz leer (Eindeutigkeit
 * `organization_id` + `sku`).
 */
final class AccountingArticleMaterialProvider implements MaterialProviderInterface {
    private const int SYNC_LIMIT = 5000;

    /** @param  list<string>  $pluginIds  eingerichtete Programme */
    public function __construct(
        private readonly int $organizationId,
        private readonly array $pluginIds,
    ) {}

    /** Anbieter der Organisation; null, solange kein Programm mit Artikelspiegel eingerichtet ist. */
    public static function forOrganization(?int $organizationId): ?self {
        if ($organizationId === null) {
            return null;
        }
        $pluginIds = [];
        foreach (app(ArticleCatalog::class)->sources() as $source) {
            if ($source instanceof AccountingArticleCatalogSource && $source->isConfigured($organizationId)) {
                $pluginIds[] = $source->pluginId();
            }
        }

        return $pluginIds === [] ? null : new self($organizationId, $pluginIds);
    }

    public function name(): string {
        return 'accounting';
    }

    /** @return Collection<int, Material> */
    public function search(string $query, int $limit = 20): Collection {
        $q = trim($query);
        $articles = $this->articles()
            ->when($q !== '', static fn (Builder $builder) => $builder->where(static fn (Builder $w) => $w->whereLikeEscaped('name', $q)->orWhereLikeEscaped('number', $q)))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $articles->map(fn (AccountingArticle $article): Material => $this->upsert($article))->values();
    }

    public function sync(): int {
        $count = 0;
        foreach ($this->articles()->orderBy('id')->limit(self::SYNC_LIMIT)->get() as $article) {
            $this->upsert($article);
            $count++;
        }

        return $count;
    }

    /** @return Builder<AccountingArticle> */
    private function articles(): Builder {
        return AccountingArticle::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organizationId)
            ->whereIn('plugin_id', $this->pluginIds)
            ->active();
    }

    private function upsert(AccountingArticle $article): Material {
        $material = Material::query()->withoutGlobalScopes()->firstOrNew([
            'organization_id' => $this->organizationId,
            'external_provider' => (string) $article->plugin_id,
            'external_id' => (string) $article->external_id,
        ]);
        $material->fill([
            'sku' => Material::freeSku($this->organizationId, $article->number, $material->exists ? (int) $material->getKey() : null),
            'name' => mb_substr((string) $article->name, 0, 255),
            'unit' => mb_substr(trim((string) $article->unit_name) ?: 'Stk.', 0, 20),
            'default_unit_price' => $article->net_unit_price?->getAmount(),
            'tax_rate' => $article->vat_rate?->getNumericValue(),
            'is_active' => true,
        ]);
        if ($material->isDirty()) {
            $material->save();
        }

        return $material;
    }
}
