<?php

namespace App\Services\Material\Provider;

use App\Models\Material;
use App\Services\Material\MaterialProviderInterface;
use Illuminate\Support\Collection;

class LocalMaterialProvider implements MaterialProviderInterface
{
    public function name(): string
    {
        return 'local';
    }

    /**
     * @return Collection<int, Material>
     */
    public function search(string $query, int $limit = 20): Collection
    {
        $q = trim($query);

        return Material::query()
            ->where('is_active', true)
            ->when($q !== '', function ($builder) use ($q): void {
                $builder->where(function ($w) use ($q): void {
                    $w->where('name', 'like', '%'.$q.'%')
                        ->orWhere('sku', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function sync(): int
    {
        return 0;
    }
}
