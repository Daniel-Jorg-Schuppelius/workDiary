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
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Platform\Organization;
use App\Settings\SettingsRegistry;

/**
 * Löst das Bewertungsverfahren je Organisation auf (Feature 048, E3). Kaskade:
 * Einstellung `inventory.valuation_method` → Fallback gleitender Durchschnitt.
 */
class ValuationMethodResolver {
    public function methodFor(Organization $organization): ValuationMethod {
        // Altformat ohne Gruppe: vor MVP-1579 gab es keine Schreibstelle, der Wert stand nur von Hand in der Datenbank.
        foreach ([SettingsRegistry::organizationValue($organization, 'inventory.valuation_method'), data_get($organization->settings, 'valuation_method')] as $setting) {
            $method = is_string($setting) ? ValuationMethod::tryFrom($setting) : null;
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
