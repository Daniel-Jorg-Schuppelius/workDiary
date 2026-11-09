<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCategoryCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification\Classification;

/**
 * Auswählbare Asset-Kategorien einer Organisation (MVP-1508): die Kategorien
 * der Organisation (Klassifikationen der Domäne `asset_category`, meist aus
 * einem Branchenprofil) vor dem globalen Pool `config('asset_categories')`.
 * Eine Organisationszeile mit Pool-Code ersetzt dessen Bezeichnung, eine
 * inaktive blendet ihn aus.
 */
final class AssetCategoryCatalog {
    /**
     * @param  string|null  $keep  Code des bearbeiteten Assets — bleibt wählbar, auch wenn er inzwischen inaktiv ist.
     * @return array<string, string> Code → Bezeichnung
     */
    public function options(?int $organizationId, ?string $keep = null): array {
        $rows = $organizationId === null ? collect() : Classification::query()
            ->where('organization_id', $organizationId)
            ->where('domain', ClassificationDomain::AssetCategory->value)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $own = [];
        $hidden = [];
        foreach ($rows as $row) {
            if ($row->active) {
                $own[$row->code] = $row->display_label;
            } else {
                $hidden[$row->code] = true;
            }
        }

        $pool = [];
        foreach ((array) config('asset_categories', []) as $code => $label) {
            $pool[(string) $code] = (string) __((string) $label);
        }

        $options = $own + array_diff_key($pool, $own, $hidden);
        if ($keep !== null && $keep !== '' && ! isset($options[$keep])) {
            $options[$keep] = $rows->firstWhere('code', $keep)->display_label ?? $pool[$keep] ?? $keep;
        }

        return $options;
    }
}
