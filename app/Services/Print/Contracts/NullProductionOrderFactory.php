<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullProductionOrderFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print\Contracts;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\Organization;
use App\Modules\ModuleUnavailableException;

final class NullProductionOrderFactory implements ProductionOrderFactory {
    public function createDraft(Organization $organization, Article $article, ?ArticleVariant $variant, string $targetQty, string $unit, array $attributes = []): ManufacturingOrder {
        throw ModuleUnavailableException::for('manufacturing');
    }
}
