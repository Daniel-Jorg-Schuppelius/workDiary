<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductionOrderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print\Contracts;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\Organization;

/**
 * Fertigungsauftrag zum Druckauftrag (MVP-863): definiert vom Druckshop,
 * gebunden von der Fertigung ({@see \App\Services\Manufacturing\ManufacturingOrderService}).
 * Null-Bindung wirft {@see \App\Modules\ModuleUnavailableException}.
 */
interface ProductionOrderFactory {
    /** @param array<string, mixed> $attributes */
    public function createDraft(Organization $organization, Article $article, ?ArticleVariant $variant, string $targetQty, string $unit, array $attributes = []): ManufacturingOrder;
}
