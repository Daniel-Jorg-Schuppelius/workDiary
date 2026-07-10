<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalMaterialProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Material\Provider;

use App\Models\Material;
use App\Services\Material\MaterialProviderInterface;
use Illuminate\Support\Collection;

class LocalMaterialProvider implements MaterialProviderInterface {
    public function name(): string {
        return 'local';
    }

    /**
     * @return Collection<int, Material>
     */
    public function search(string $query, int $limit = 20): Collection {
        $q = trim($query);

        return Material::query()
            ->where('is_active', true)
            ->when($q !== '', function ($builder) use ($q): void {
                $builder->where(function ($w) use ($q): void {
                    $w->whereLikeEscaped('name', $q)
                        ->orWhereLikeEscaped('sku', $q);
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function sync(): int {
        return 0;
    }
}
