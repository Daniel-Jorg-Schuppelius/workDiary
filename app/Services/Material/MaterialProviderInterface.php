<?php

namespace App\Services\Material;

use App\Models\Material;
use Illuminate\Support\Collection;

interface MaterialProviderInterface
{
    public function name(): string;

    /**
     * Suche nach Materialien (lokal oder extern).
     *
     * @return Collection<int, Material>
     */
    public function search(string $query, int $limit = 20): Collection;

    /**
     * Synchronisiert externe Materialien in die lokale `materials`-Tabelle.
     */
    public function sync(): int;
}
