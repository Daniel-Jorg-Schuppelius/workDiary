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
    /**
     * Vergibt den Slug, solange keiner gesetzt ist. creating statt saving:
     * erst BelongsToOrganization::creating setzt die organization_id — saving
     * liefe davor und prüfte gegen NULL.
     *
     * @param  \Closure(self): string  $slugFor
     */
    protected static function assignSlugWhenMissing(\Closure $slugFor): void {
        $assign = static function (self $model) use ($slugFor): void {
            if ($model->slug === null || $model->slug === '') {
                $model->slug = $slugFor($model);
            }
        };
        static::creating($assign);
        static::updating($assign);
    }

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
