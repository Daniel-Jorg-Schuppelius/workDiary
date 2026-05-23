<?php

/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementValidator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\ClassificationRequirementPhase;
use App\Models\ClassificationRequirement;
use App\Models\DiaryEntry;

/**
 * Prüft Pflichtklassifikationen pro Auftragstyp und Phase.
 */
class ClassificationRequirementValidator {
    /**
     * @param  array<string, list<string>>  $valuesByDomain
     * @return list<RequirementResult>
     */
    public function validate(DiaryEntry $entry, ClassificationRequirementPhase|string $phase, array $valuesByDomain = []): array {
        $phaseValue = $phase instanceof ClassificationRequirementPhase ? $phase->value : $phase;
        $entryTypeCode = $this->entryTypeCode($entry, $valuesByDomain);
        if ($entryTypeCode === null) {
            return [];
        }

        $requirements = ClassificationRequirement::query()
            ->where('organization_id', $entry->organization_id)
            ->where('entry_type_code', $entryTypeCode)
            ->where('enforce_phase', $phaseValue)
            ->get();

        $results = [];
        foreach ($requirements as $requirement) {
            if (! $this->conditionsMatch($requirement, $valuesByDomain)) {
                continue;
            }

            $actualCount = count($valuesByDomain[$requirement->required_domain] ?? []);
            $isBelowMin = $actualCount < $requirement->min_count;
            $isAboveMax = $requirement->max_count !== null && $actualCount > $requirement->max_count;

            if (! $isBelowMin && ! $isAboveMax) {
                continue;
            }

            $result = new RequirementResult(
                $requirement->id,
                $requirement->required_domain,
                $requirement->severity,
                $actualCount,
                $requirement->min_count,
                $requirement->max_count,
                $phaseValue,
            );
            $results[] = $result;

            if ($result->isBlocking()) {
                $entry->audit('classification.requirementMissing', [
                    'requirement_id' => $requirement->id,
                    'required_domain' => $requirement->required_domain,
                    'phase' => $phaseValue,
                    'actual_count' => $actualCount,
                    'min_count' => $requirement->min_count,
                    'max_count' => $requirement->max_count,
                ]);
            }
        }

        return $results;
    }

    /**
     * @param  array<string, list<string>>  $valuesByDomain
     */
    private function entryTypeCode(DiaryEntry $entry, array $valuesByDomain): ?string {
        if (isset($valuesByDomain['entry_type'][0]) && $valuesByDomain['entry_type'][0] !== '') {
            return $valuesByDomain['entry_type'][0];
        }

        if ($entry->relationLoaded('entryType') || $entry->entry_type_id !== null) {
            $slug = $entry->entryType?->slug;
            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $valuesByDomain
     */
    private function conditionsMatch(ClassificationRequirement $requirement, array $valuesByDomain): bool {
        $conditions = $requirement->only_if_json;
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        foreach ($conditions as $domain => $allowedValues) {
            if ($allowedValues === []) {
                continue;
            }
            $actualValues = $valuesByDomain[$domain] ?? [];

            $matched = false;
            foreach ($actualValues as $actual) {
                if (in_array($actual, $allowedValues, true)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        return true;
    }
}
