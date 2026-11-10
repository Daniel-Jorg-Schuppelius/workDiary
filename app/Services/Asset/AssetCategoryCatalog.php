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
        [$own, $hidden, $pool] = $this->sources($organizationId);

        $options = $own + array_diff_key($pool, $own, $hidden);
        if ($keep !== null && $keep !== '' && ! isset($options[$keep])) {
            $options[$keep] = $hidden[$keep] ?? $pool[$keep] ?? $keep;
        }

        return $options;
    }

    /**
     * Bezeichnungen aller bekannten Codes, auch inaktiver (MVP-1527): für
     * Anzeige, Filter und Auswertungen gespeicherter Werte.
     *
     * @return array<string, string> Code → Bezeichnung
     */
    public function labels(?int $organizationId): array {
        [$own, $hidden, $pool] = $this->sources($organizationId);

        return $own + $hidden + $pool;
    }

    /**
     * Gespeicherte Codes beschriftet und nach Bezeichnung sortiert; ein
     * unbekannter Code bleibt als Code stehen.
     *
     * @param  iterable<mixed>  $codes
     * @return array<string, string> Code → Bezeichnung
     */
    public function labelled(?int $organizationId, iterable $codes): array {
        $labels = $this->labels($organizationId);
        $out = [];
        foreach ($codes as $code) {
            $code = (string) $code;
            if ($code !== '') {
                $out[$code] = $labels[$code] ?? $code;
            }
        }
        natcasesort($out);

        return $out;
    }

    /** @return array{0: array<string, string>, 1: array<string, string>, 2: array<string, string>} aktive Organisationszeilen, inaktive, globaler Pool */
    private function sources(?int $organizationId): array {
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
                $hidden[$row->code] = $row->display_label;
            }
        }

        $pool = [];
        foreach ((array) config('asset_categories', []) as $code => $label) {
            $pool[(string) $code] = (string) __((string) $label);
        }

        return [$own, $hidden, $pool];
    }
}
