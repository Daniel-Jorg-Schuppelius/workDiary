<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullArticleValuation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article\Contracts;

use App\Enums\Inventory\ValuationMethod;
use App\Models\Article\Article;
use App\Models\Platform\Organization;
use Closure;

final class NullArticleValuation implements ArticleValuation {
    public function organizationMethod(Organization $organization): ValuationMethod {
        return ValuationMethod::MovingAverage;
    }

    public function saveArticle(Article $article, array $attributes, Organization $organization, Closure $save): void {
        $save();
    }
}
