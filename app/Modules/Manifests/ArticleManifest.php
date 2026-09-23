<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Artikel, Produkte, Preise“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ArticleManifest extends Manifest {
    public function code(): string {
        return 'article';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Artikel, Produkte, Preise';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Article',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'article_merge_dismissals',
            'article_option_definitions',
            'article_option_values',
            'article_price_tiers',
            'article_sale_price_histories',
            'article_supplies',
            'article_units',
            'article_variant_bom_overrides',
            'article_variant_option_values',
            'article_variants',
            'articles',
            'metal_quotations',
            'price_change_requests',
            'pricing_change_alerts',
            'pricing_margin_rules',
            'products',
        ];
    }
}
