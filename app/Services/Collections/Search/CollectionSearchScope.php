<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollectionSearchScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Search;

use App\Models\Platform\User;
use App\Services\Collections\{CollectableTypes, ContentCollectionService};
use App\Services\Search\Contracts\CollectionScope;
use Illuminate\Database\Eloquent\Model;

/** Sammlungen (Feature 155, MVP-812/813) als Facette und Sammelziel der Suche. */
final class CollectionSearchScope implements CollectionScope {
    public function __construct(
        private readonly ContentCollectionService $collections,
        private readonly CollectableTypes $collectables,
    ) {}

    public function visibleSubtreeIds(User $viewer, ?int $organizationId, int $collectionId): array {
        return $this->collections->visibleSubtreeIds($viewer, $organizationId, $collectionId);
    }

    public function tree(User $viewer): array {
        return $this->collections->tree($viewer);
    }

    public function keyFor(Model $model): ?string {
        return $this->collectables->keyFor($model);
    }
}
