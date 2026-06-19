<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleUnitKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Article;

/**
 * Rolle einer artikelbezogenen Einheit (Feature 048, MVP-060). Bestände und
 * Bewegungen werden intern in der Basiseinheit geführt; weitere Einheiten
 * tragen einen exakten Faktor zur Basiseinheit.
 */
enum ArticleUnitKind: string {
    case Base = 'base';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Packaging = 'packaging';

    public function label(): string {
        return match ($this) {
            self::Base => __('article.unit_kind.base'),
            self::Purchase => __('article.unit_kind.purchase'),
            self::Sale => __('article.unit_kind.sale'),
            self::Packaging => __('article.unit_kind.packaging'),
        };
    }
}
