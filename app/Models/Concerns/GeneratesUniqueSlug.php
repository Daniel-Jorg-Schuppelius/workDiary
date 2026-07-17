<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeneratesUniqueSlug.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gemeinsamer Kern der Slug-Vergabe (konsolidierungs-audit-2026-07, Befund
 * D2): Basis-Slug aus dem Namen (Sentinel bei leerem Ergebnis), bei
 * Kollision Suffix-Zähler -2, -3, … Den Eindeutigkeits-Scope (Org/Kunde/
 * global, ignoreId, trashed) bestimmt der $taken-Hook des Models.
 */
trait GeneratesUniqueSlug {
    /**
     * @param  string  $name  Rohname; wird via Str::slug() normalisiert
     * @param  string  $sentinel  Basis-Slug, falls der Name keinen Slug ergibt
     * @param  callable(string): bool  $taken  true = Slug bereits vergeben
     */
    protected static function resolveUniqueSlug(string $name, string $sentinel, callable $taken): string {
        $base = Str::slug($name) ?: $sentinel;
        $slug = $base;
        $i = 2;
        while ($taken($slug)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
