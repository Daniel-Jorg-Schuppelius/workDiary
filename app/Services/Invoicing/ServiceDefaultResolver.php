<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceDefaultResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Platform\Organization;
use App\Models\Project\{Project, ProjectBillingRule};
use App\Plugins\Support\PluginOrgContext;
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};

/**
 * Löst die Standardleistung einer Position auf (MVP-486):
 * Projekt-Abrechnungsregel (je Tätigkeitsart, rekursiv über Parent) →
 * Organisations-Standardleistung → keine.
 *
 * Fehlende Angaben der Regel ergänzt der Artikel aus dem Artikelkatalog
 * (MVP-1026, Artikelstamm oder Plugin-Quelle): Bezeichnung, Einheit,
 * Standardtext, MwSt und Nettopreis. Der Preis ist nur ein Rückfall — die
 * Preisfindung selbst steckt im {@see BlockPriceResolver}.
 *
 * Der Cache lebt so lange wie die Instanz, damit ein Übergabe-Lauf mit
 * vielen Positionen nicht je Position nachlädt.
 */
class ServiceDefaultResolver {
    /** @var array<string, ResolvedService|null> Cache je "orgId|projectId|kind". */
    private array $cache = [];

    /** @var array<string, CatalogArticle|null> Artikel-Cache je "orgId|Katalogschlüssel". */
    private array $articles = [];

    public function __construct(private readonly ArticleCatalog $catalog) {}

    public function flush(): void {
        $this->cache = [];
        $this->articles = [];
    }

    public function resolve(?Organization $organization, ?Project $project, ?string $kind): ?ResolvedService {
        $organizationId = $organization !== null
            ? (int) $organization->id
            : ($project?->organization_id !== null ? (int) $project->organization_id : PluginOrgContext::currentId());
        if ($organizationId === null) {
            return null;
        }

        $key = $organizationId . '|' . ($project !== null ? (int) $project->id : 0) . '|' . ((string) $kind);
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->build((int) $organizationId, $project, $kind);
        }

        return $this->cache[$key];
    }

    private function build(int $organizationId, ?Project $project, ?string $kind): ?ResolvedService {
        $rule = $project?->resolveBillingRule($kind);
        if ($rule instanceof ProjectBillingRule) {
            return $this->fromRule($organizationId, $rule);
        }

        return $this->fromOrganization($organizationId);
    }

    private function fromRule(int $organizationId, ProjectBillingRule $rule): ResolvedService {
        $article = $this->article($organizationId, $rule->article_ref);

        return new ResolvedService(
            articleRef: $rule->article_ref,
            name: $article?->name,
            unitName: $rule->unit_name ?: $article?->unitName,
            netPrice: $rule->net_unit_price?->toFloat() ?? $article?->netPrice?->toFloat(),
            vatRate: $rule->vat_rate !== null
                ? (float) $rule->vat_rate->getNumericValue()
                : ($article?->vatRate !== null ? (float) $article->vatRate->getNumericValue() : null),
            standardText: $article?->description,
            itemType: (string) ($rule->item_type ?: ($article->itemType ?? 'service')),
            source: ResolvedService::SOURCE_PROJECT_RULE,
            priceIsExplicit: ($rule->net_unit_price?->toFloat() ?? 0.0) > 0.0,
        );
    }

    private function fromOrganization(int $organizationId): ?ResolvedService {
        $organization = Organization::query()->withoutGlobalScopes()->find($organizationId);
        if (! $organization instanceof Organization) {
            return null;
        }

        $articleRef = trim((string) ($organization->invoicingSettings()['default_service_article'] ?? ''));
        if ($articleRef === '') {
            return null;
        }

        // Ein nicht (mehr) auffindbarer Artikel lässt den Bezug stehen; das
        // Zielsystem entscheidet, ob es ihn auflösen kann.
        $article = $this->article($organizationId, $articleRef);

        return new ResolvedService(
            articleRef: $articleRef,
            name: $article?->name,
            unitName: $article?->unitName,
            netPrice: $article?->netPrice?->toFloat(),
            vatRate: $article?->vatRate !== null ? (float) $article->vatRate->getNumericValue() : null,
            standardText: $article?->description,
            itemType: $article->itemType ?? 'service',
            source: ResolvedService::SOURCE_ORGANIZATION,
        );
    }

    private function article(int $organizationId, ?string $articleRef): ?CatalogArticle {
        if ($articleRef === null || $articleRef === '') {
            return null;
        }

        $key = $organizationId . '|' . $articleRef;
        if (! array_key_exists($key, $this->articles)) {
            $this->articles[$key] = $this->catalog->find($organizationId, $articleRef);
        }

        return $this->articles[$key];
    }
}
