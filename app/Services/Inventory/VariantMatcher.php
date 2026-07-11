<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VariantMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\ArticleVariant;

/**
 * Sicherer Artikel-Abgleich für externe Integrationen (Feature 048/078):
 * exakte SKU primär, sekundär die **eindeutige** GTIN — die GTIN ist der
 * systemübergreifende Brückenschlüssel (JTL-Wawi und Lexoffice führen sie
 * beide). Mehrdeutige GTIN-Treffer werden als `ambiguous` gemeldet und
 * gehören in die Integrations-Inbox; es wird nie geraten und nie ein
 * Stammsatz automatisch angelegt.
 */
class VariantMatcher {
    /** @return array{variant: ArticleVariant|null, ambiguous: bool} */
    public function match(int $organizationId, string $sku, string $gtin): array {
        $sku = trim($sku);
        $gtin = trim($gtin);

        if ($sku !== '') {
            $variant = ArticleVariant::query()
                ->where('organization_id', $organizationId)
                ->where('sku', $sku)
                ->first();
            if ($variant instanceof ArticleVariant) {
                return ['variant' => $variant, 'ambiguous' => false];
            }
        }

        if ($gtin !== '') {
            $candidates = ArticleVariant::query()
                ->where('organization_id', $organizationId)
                ->where('gtin', $gtin)
                ->limit(2)
                ->get();
            if ($candidates->count() === 1) {
                return ['variant' => $candidates->first(), 'ambiguous' => false];
            }
            if ($candidates->count() > 1) {
                return ['variant' => null, 'ambiguous' => true];
            }
        }

        return ['variant' => null, 'ambiguous' => false];
    }
}
