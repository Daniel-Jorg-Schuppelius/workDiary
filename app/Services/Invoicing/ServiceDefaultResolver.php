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

use App\Models\{LexofficeArticle, Organization, Project, ProjectBillingRule};
use App\Plugins\Support\PluginOrgContext;

/**
 * Löst die Standardleistung einer Position auf (MVP-486):
 * Projekt-Abrechnungsregel (je Tätigkeitsart, rekursiv über Parent) →
 * Organisations-Standardleistung → keine.
 *
 * Fehlende Angaben der Regel werden aus dem lokalen Artikel-Cache
 * ({@see LexofficeArticle}) ergänzt: Bezeichnung, Einheit, Standardtext,
 * MwSt und Nettopreis. Der Preis ist nur ein Rückfall — die Preisfindung
 * selbst steckt im {@see BlockPriceResolver}.
 *
 * Als `scoped` gebunden: der Cache lebt pro Request/Job, damit ein
 * Übergabe-Lauf mit vielen Positionen nicht je Position nachlädt.
 */
class ServiceDefaultResolver {
    /** @var array<string, ResolvedService|null> Cache je "orgId|projectId|kind". */
    private array $cache = [];

    /** @var array<string, LexofficeArticle|null> Artikel-Cache je "orgId|externalId". */
    private array $articles = [];

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
        $article = $rule->lexoffice_article_id !== null
            ? $this->article($organizationId, (string) $rule->lexoffice_article_id)
            : null;

        return new ResolvedService(
            articleId: $rule->lexoffice_article_id !== null ? (string) $rule->lexoffice_article_id : null,
            name: $article?->name,
            unitName: $rule->unit_name ?: $article?->unit_name,
            netPrice: $rule->net_unit_price?->toFloat() ?? $article?->net_unit_price?->toFloat(),
            vatRate: $rule->vat_rate !== null
                ? (float) $rule->vat_rate->getNumericValue()
                : ($article?->vat_rate !== null ? (float) $article->vat_rate->getNumericValue() : null),
            standardText: $article?->description,
            itemType: (string) ($rule->item_type ?: ($article?->type ?: 'service')),
            source: ResolvedService::SOURCE_PROJECT_RULE,
        );
    }

    private function fromOrganization(int $organizationId): ?ResolvedService {
        $organization = Organization::query()->withoutGlobalScopes()->find($organizationId);
        if (! $organization instanceof Organization) {
            return null;
        }

        $settings = $organization->invoicingSettings();
        $externalId = trim((string) ($settings['default_service_article'] ?? ''));
        if ($externalId === '') {
            return null;
        }

        $article = $this->article($organizationId, $externalId);
        if (! $article instanceof LexofficeArticle) {
            // Artikel (noch) nicht synchronisiert: der Bezug bleibt trotzdem
            // erhalten, damit das Zielsystem ihn auflösen kann.
            return new ResolvedService(
                articleId: $externalId,
                name: null,
                unitName: null,
                netPrice: null,
                vatRate: null,
                standardText: null,
                itemType: 'service',
                source: ResolvedService::SOURCE_ORGANIZATION,
            );
        }

        return new ResolvedService(
            articleId: (string) $article->external_id,
            name: $article->name,
            unitName: $article->unit_name,
            netPrice: $article->net_unit_price?->toFloat(),
            vatRate: $article->vat_rate !== null ? (float) $article->vat_rate->getNumericValue() : null,
            standardText: $article->description,
            itemType: $article->type ?: 'service',
            source: ResolvedService::SOURCE_ORGANIZATION,
        );
    }

    private function article(int $organizationId, string $externalId): ?LexofficeArticle {
        $key = $organizationId . '|' . $externalId;
        if (! array_key_exists($key, $this->articles)) {
            $this->articles[$key] = LexofficeArticle::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('external_id', $externalId)
                ->first();
        }

        return $this->articles[$key];
    }
}
