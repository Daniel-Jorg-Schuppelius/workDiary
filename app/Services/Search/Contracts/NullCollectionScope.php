<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullCollectionScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Contracts;

use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

final class NullCollectionScope implements CollectionScope {
    public function visibleSubtreeIds(User $viewer, ?int $organizationId, int $collectionId): array {
        return [];
    }

    public function tree(User $viewer): array {
        return [];
    }

    public function keyFor(Model $model): ?string {
        return null;
    }
}
