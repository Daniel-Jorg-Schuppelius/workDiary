<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article;

use App\Enums\Article\ArticleStatus;
use App\Enums\Numbering\NumberScope;
use App\Models\{Article, ArticleVariant, Organization};
use App\Services\Numbering\NumberSequenceService;
use RuntimeException;

/**
 * Orchestriert den Artikelstamm (Feature 048, MVP-060): Anlage mit SKU-Hoheit
 * über die zentrale {@see NumberSequenceService} (Scope `article`, lokal
 * geführt), Lebenszyklus (stilllegen statt löschen) und Löschschutz für
 * referenzierte Stammdaten.
 */
class ArticleService {
    public function __construct(
        private readonly NumberSequenceService $numbers = new NumberSequenceService(),
    ) {}

    /**
     * Legt einen Artikel an und vergibt – sofern WorkDiary die Nummernhoheit
     * hat – eine SKU. Eine bereits gesetzte Nummer wird respektiert (Übernahme
     * einer führenden externen SKU).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createArticle(Organization $organization, array $attributes): Article {
        $attributes['organization_id'] = $organization->id;
        if (empty($attributes['number'])) {
            $attributes['number'] = $this->numbers->next($organization, NumberScope::Article);
        }

        return Article::query()->create($attributes);
    }

    /**
     * Weist einer Variante eine SKU zu, falls noch keine (lokale Hoheit)
     * vorhanden ist.
     */
    public function assignVariantSku(ArticleVariant $variant): ArticleVariant {
        if (! empty($variant->sku)) {
            return $variant;
        }

        $orgId = $variant->organization_id;
        if ($orgId === null) {
            throw new RuntimeException('Variante ohne Organisation kann keine SKU erhalten.');
        }

        $variant->sku = $this->numbers->next($orgId, NumberScope::Article);
        $variant->save();

        return $variant;
    }

    /** Legt einen Artikel still (retired), statt ihn zu löschen. */
    public function retire(Article $article): void {
        $article->status = ArticleStatus::Retired;
        $article->save();

        // Varianten werden mit stillgelegt; historische Snapshots bleiben extern.
        $article->variants()->update(['status' => ArticleStatus::Retired->value]);
    }

    /**
     * Nur referenzlose Entwürfe dürfen gelöscht werden (Feature 048:
     * „Nur Entwürfe ohne Referenzen dürfen gelöscht werden").
     */
    public function canDelete(Article $article): bool {
        if ($article->status !== ArticleStatus::Draft) {
            return false;
        }

        return ! $article->externalMappings()->exists();
    }
}
