<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationMethodResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\ValuationMethod;
use App\Models\{Article, ArticleVariant, Organization};

/**
 * Löst das Bewertungsverfahren je Organisation auf (Feature 048, E3). Kaskade:
 * organizations.settings['valuation_method'] → Fallback gleitender Durchschnitt.
 */
class ValuationMethodResolver {
    public function methodFor(Organization $organization): ValuationMethod {
        $setting = data_get($organization->settings, 'valuation_method');
        if (is_string($setting) && $setting !== '') {
            $method = ValuationMethod::tryFrom($setting);
            if ($method instanceof ValuationMethod) {
                return $method;
            }
        }

        return ValuationMethod::MovingAverage;
    }

    /**
     * Verfahren je Variante: Artikel-Override hat Vorrang vor der Organisation
     * (Feature 048, E3).
     */
    public function methodForVariant(ArticleVariant $variant, Organization $organization): ValuationMethod {
        $article = $variant->article;
        if ($article instanceof Article && is_string($article->valuation_method) && $article->valuation_method !== '') {
            $method = ValuationMethod::tryFrom($article->valuation_method);
            if ($method instanceof ValuationMethod) {
                return $method;
            }
        }

        return $this->methodFor($organization);
    }
}
