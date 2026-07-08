<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppliesValueMappings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs\Concerns;

use App\Models\{ImportValueMapping, Organization, Tag};
use Illuminate\Database\Eloquent\Model;

/**
 * Tag-Anwendung beim CSV-Import (Rang 58): löst Quellwerte über das
 * persistente Mapping bzw. den case-insensitiven Namens-Treffer auf und
 * hängt die Tags ans Modell. Unbekannte Werte werden NIE blind angelegt —
 * sie bleiben liegen (Preflight sammelt sie fürs Mapping-Formular).
 */
trait AppliesValueMappings {
    /** @return list<string> */
    public function splitMappableValues(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $values = preg_split('/[;,]/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $values), static fn (string $v): bool => $v !== ''));
    }

    /**
     * Wendet die Tag-Werte auf das persistierte Modell an (syncWithoutDetaching
     * — Wiederholimporte bleiben idempotent).
     */
    protected function applyMappedTags(Model $model, Organization $organization, ?string $raw, string $entity): void {
        if (! method_exists($model, 'tags')) {
            return;
        }

        $tagIds = [];
        foreach ($this->splitMappableValues($raw) as $value) {
            $resolved = ImportValueMapping::resolveValue((int) $organization->id, $entity, $value);
            if ($resolved === ImportValueMapping::KIND_IGNORE) {
                continue;
            }
            if (is_int($resolved)) {
                $tagIds[] = $resolved;

                continue;
            }

            // Kein Mapping: deterministischer Namens-Treffer zählt, unbekannte
            // Werte bleiben unangewendet (kein Blind-Neuanlegen).
            $existing = Tag::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(name) = ?', [ImportValueMapping::normalize($value)])
                ->first();
            if ($existing !== null) {
                $tagIds[] = (int) $existing->id;
            }
        }

        if ($tagIds !== []) {
            $model->tags()->syncWithoutDetaching(array_unique($tagIds));
        }
    }

    /**
     * Unbekannte Werte einer Zeile (weder Mapping noch Namens-Treffer) —
     * Datengrundlage des Mapping-Formulars in der Preflight.
     *
     * @return list<string>
     */
    protected function unresolvedMappableValues(Organization $organization, ?string $raw, string $entity): array {
        $unresolved = [];
        foreach ($this->splitMappableValues($raw) as $value) {
            if (ImportValueMapping::resolveValue((int) $organization->id, $entity, $value) !== null) {
                continue;
            }
            $exists = Tag::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(name) = ?', [ImportValueMapping::normalize($value)])
                ->exists();
            if (! $exists) {
                $unresolved[] = $value;
            }
        }

        return $unresolved;
    }
}
