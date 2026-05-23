<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetNumberGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Models\Asset;
use Carbon\CarbonImmutable;

class AssetNumberGenerator {
    public function generate(int $organizationId, ?CarbonImmutable $now = null): string {
        $now ??= CarbonImmutable::now();
        $year = $now->format('Y');
        $prefix = "AS-{$year}-";

        $last = Asset::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('asset_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('asset_no');

        $next = 1;
        if (is_string($last) && str_starts_with($last, $prefix)) {
            $suffix = substr($last, strlen($prefix));
            $parsed = (int) $suffix;
            if ($parsed > 0) {
                $next = $parsed + 1;
            }
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
