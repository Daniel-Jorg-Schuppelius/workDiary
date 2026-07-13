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

use App\Models\{Classification, ImportValueMapping, Organization, Tag};
use App\Services\Classification\ClassificationManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Tag-/Klassifikations-Anwendung beim Import (Rang 58, A13): löst Quellwerte
 * über das persistente Mapping, den case-insensitiven Tag-Namens-Treffer oder
 * — für Entitäten mit Klassifikations-Trägerschaft — den eindeutigen
 * Klassifikations-Code (CODE_REGEX) auf und hängt die Ziele ans Modell.
 * Unbekannte Werte werden NIE blind angelegt — sie bleiben liegen
 * (Preflight sammelt sie fürs Mapping-Formular).
 */
trait AppliesValueMappings {
    /** Entität der nutzenden Spec ({@see \App\Services\Import\EntitySpec::entity()}). */
    abstract public function entity(): \App\Enums\Import\ImportEntity;

    /** @return list<string> */
    public function splitMappableValues(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $values = preg_split('/[;,]/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $values), static fn (string $v): bool => $v !== ''));
    }

    /**
     * Wendet die Quellwerte auf das persistierte Modell an
     * (syncWithoutDetaching — Wiederholimporte bleiben idempotent):
     * Mapping (Tag/Klassifikation/Ignorieren) → Tag-Namens-Treffer →
     * eindeutiger Klassifikations-Code.
     */
    protected function applyMappedValues(Model $model, Organization $organization, ?string $raw, string $entity): void {
        $tagIds = [];
        $classificationIds = [];

        foreach ($this->splitMappableValues($raw) as $value) {
            $mapping = ImportValueMapping::findFor((int) $organization->id, $entity, $value);
            if ($mapping !== null) {
                if ($mapping->target_kind === ImportValueMapping::KIND_TAG && $mapping->tag_id !== null) {
                    $tagIds[] = (int) $mapping->tag_id;
                } elseif ($mapping->target_kind === ImportValueMapping::KIND_CLASSIFICATION) {
                    // Org-Guard: die Klassifikation muss weiterhin für die Org
                    // sichtbar und aktiv sein (sonst still überspringen).
                    $classification = $this->visibleClassification($organization, (int) $mapping->classification_id);
                    if ($classification !== null) {
                        $classificationIds[] = (int) $classification->id;
                    }
                }

                continue; // KIND_IGNORE bzw. aufgelöst
            }

            // Kein Mapping: deterministischer Tag-Namens-Treffer zählt, danach
            // der eindeutige Klassifikations-Code; unbekannte Werte bleiben
            // unangewendet (kein Blind-Neuanlegen).
            $existing = Tag::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(name) = ?', [ImportValueMapping::normalize($value)])
                ->first();
            if ($existing !== null) {
                $tagIds[] = (int) $existing->id;

                continue;
            }

            $byCode = $this->matchClassificationByCode($organization, $value);
            if ($byCode !== null) {
                $classificationIds[] = (int) $byCode->id;
            }
        }

        if ($tagIds !== [] && method_exists($model, 'tags')) {
            $model->tags()->syncWithoutDetaching(array_unique($tagIds));
        }
        if ($classificationIds !== [] && method_exists($model, 'classifications')) {
            $model->classifications()->syncWithoutDetaching(array_unique($classificationIds));
        }
    }

    /**
     * Unbekannte Werte einer Zeile (weder Mapping noch Tag-Namens- oder
     * Klassifikations-Code-Treffer) — Datengrundlage des Mapping-Formulars
     * in der Preflight.
     *
     * @return list<string>
     */
    public function unresolvedMappableValues(Organization $organization, ?string $raw, string $entity): array {
        $unresolved = [];
        foreach ($this->splitMappableValues($raw) as $value) {
            if (ImportValueMapping::findFor((int) $organization->id, $entity, $value) !== null) {
                continue;
            }
            $exists = Tag::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(name) = ?', [ImportValueMapping::normalize($value)])
                ->exists();
            if ($exists) {
                continue;
            }
            if ($this->matchClassificationByCode($organization, $value) !== null) {
                continue;
            }
            $unresolved[] = $value;
        }

        return $unresolved;
    }

    /**
     * CODE_REGEX-Mechanik (A13): ein Quellwert, der normalisiert ein gültiger
     * Klassifikations-Code ist und org-sichtbar auf GENAU EINE effektive
     * Klassifikation zeigt, wird deterministisch aufgelöst. Org-Overrides
     * verdrängen den Plattform-Default gleicher Domäne; Codes in mehreren
     * Domänen sind mehrdeutig und bleiben unaufgelöst (Mapping-Formular).
     */
    protected function matchClassificationByCode(Organization $organization, string $value): ?Classification {
        if (! $this->entity()->supportsClassifications()) {
            return null;
        }

        $normalized = ImportValueMapping::normalize($value);
        if (preg_match(ClassificationManager::CODE_REGEX, $normalized) !== 1) {
            return null;
        }

        $candidates = Classification::query()
            ->where('code', $normalized)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organization->id))
            ->get();

        /** @var array<string, Classification> $byDomain */
        $byDomain = [];
        foreach ($candidates as $candidate) {
            $key = $candidate->domain->value;
            if (! isset($byDomain[$key]) || $candidate->organization_id !== null) {
                $byDomain[$key] = $candidate;
            }
        }

        return count($byDomain) === 1 ? array_values($byDomain)[0] : null;
    }

    /**
     * Org-sichtbare, aktive Klassifikation (Plattform-Default oder eigene).
     */
    private function visibleClassification(Organization $organization, int $classificationId): ?Classification {
        return Classification::query()
            ->whereKey($classificationId)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organization->id))
            ->first();
    }
}
