<?php
/*
 * Created on   : Wed Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequirementPresets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase, ClassificationRequirementSeverity};

class RequirementPresets {
    /**
     * Füllt leere Preset-Felder des Request-Inputs aus den Preset-Maps auf.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function applyFallbacks(array $input): array {
        $entryTypeCode = trim((string) ($input['entry_type_code'] ?? ''));
        $requiredDomain = trim((string) ($input['required_domain'] ?? ''));
        $preset = $this->resolve($entryTypeCode, $requiredDomain);

        if ($preset === []) {
            return [];
        }

        $merged = [];

        foreach (['enforce_phase', 'severity', 'min_count', 'max_count'] as $field) {
            $value = $input[$field] ?? null;
            if (($value === null || $value === '') && array_key_exists($field, $preset)) {
                $merged[$field] = $preset[$field];
            }
        }

        if (! array_key_exists('allow_multi', $input) && array_key_exists('allow_multi', $preset)) {
            $merged['allow_multi'] = $preset['allow_multi'];
        }

        return $merged;
    }

    /**
     * @return array{enforce_phase?: string, severity?: string, min_count?: int, max_count?: int|null, allow_multi?: bool}
     */
    public function resolve(string $entryTypeCode, string $requiredDomain): array {
        $preset = [];

        if ($requiredDomain !== '' && isset($this->requiredDomainPresets()[$requiredDomain])) {
            $preset = $this->requiredDomainPresets()[$requiredDomain];
        }

        if ($entryTypeCode !== '' && isset($this->entryTypePresets()[$entryTypeCode])) {
            $preset = array_merge($preset, $this->entryTypePresets()[$entryTypeCode]);
        }

        return $preset;
    }

    /**
     * @return array<string, array{enforce_phase: string, severity: string, min_count: int, max_count: int|null, allow_multi: bool}>
     */
    public function entryTypePresets(): array {
        return [
            'service' => [
                'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Soft->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            'incident' => [
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            'change' => [
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            'repair' => [
                'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            'installation' => [
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            'wartung' => [
                'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            'reklamation' => [
                'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Soft->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
        ];
    }

    /**
     * @return array<string, array{enforce_phase: string, severity: string, min_count: int, max_count: int|null, allow_multi: bool}>
     */
    public function requiredDomainPresets(): array {
        return [
            ClassificationDomain::DefectType->value => [
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            ClassificationDomain::Priority->value => [
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            ClassificationDomain::ProductGroup->value => [
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            ClassificationDomain::Result->value => [
                'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
            ClassificationDomain::RootCause->value => [
                'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
                'severity' => ClassificationRequirementSeverity::Hard->value,
                'min_count' => 1,
                'max_count' => null,
                'allow_multi' => false,
            ],
        ];
    }
}
