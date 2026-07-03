<?php
/*
 * Created on   : Wed Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequirementIndexFilter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Models\ClassificationRequirement;
use Illuminate\Database\Eloquent\Builder;

class RequirementIndexFilter {
    public function normalizePhaseFilter(string $value): ?string {
        foreach (ClassificationRequirementPhase::cases() as $phase) {
            if ($phase->value === $value) {
                return $value;
            }
        }

        return null;
    }

    public function normalizeDomainFilter(string $value): ?string {
        foreach (ClassificationDomain::cases() as $domain) {
            if ($domain === ClassificationDomain::EntryType) {
                continue;
            }

            if ($domain->value === $value) {
                return $value;
            }
        }

        return null;
    }

    public function normalizeConditionFilter(string $value): ?string {
        return array_key_exists($value, $this->conditionOptions()) ? $value : null;
    }

    public function normalizeAllowMultiFilter(string $value): ?string {
        return array_key_exists($value, $this->allowMultiOptions()) ? $value : null;
    }

    public function normalizeNoteFilter(string $value): ?string {
        return array_key_exists($value, $this->noteOptions()) ? $value : null;
    }

    public function normalizeMaxCountFilter(string $value): ?string {
        return array_key_exists($value, $this->maxCountOptions()) ? $value : null;
    }

    public function normalizeSortField(string $value): string {
        return array_key_exists($value, $this->sortOptions()) ? $value : 'entry_type_code';
    }

    public function normalizeSeverityFilter(string $value): ?string {
        foreach (ClassificationRequirementSeverity::cases() as $severity) {
            if ($severity->value === $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function sortColumns(string $sortField): array {
        return match ($sortField) {
            'required_domain' => ['required_domain', 'entry_type_code', 'enforce_phase'],
            'enforce_phase' => ['enforce_phase', 'entry_type_code', 'required_domain'],
            'severity' => ['severity', 'entry_type_code', 'required_domain'],
            'max_count' => ['max_count', 'entry_type_code', 'required_domain'],
            default => ['entry_type_code', 'enforce_phase', 'required_domain'],
        };
    }

    /**
     * @param  Builder<ClassificationRequirement>  $requirementsQuery
     */
    public function applySorting(Builder $requirementsQuery, string $sortField): void {
        if ($sortField === 'enforce_phase') {
            $requirementsQuery
                ->orderByRaw(
                    'case enforce_phase when ? then 0 when ? then 1 when ? then 2 else 3 end',
                    [
                        ClassificationRequirementPhase::OnCreate->value,
                        ClassificationRequirementPhase::BeforeComplete->value,
                        ClassificationRequirementPhase::BeforeSign->value,
                    ]
                )
                ->orderBy('entry_type_code')
                ->orderBy('required_domain');

            return;
        }

        if ($sortField === 'severity') {
            $requirementsQuery
                ->orderByRaw(
                    'case severity when ? then 0 when ? then 1 else 2 end',
                    [
                        ClassificationRequirementSeverity::Hard->value,
                        ClassificationRequirementSeverity::Soft->value,
                    ]
                )
                ->orderBy('entry_type_code')
                ->orderBy('required_domain');

            return;
        }

        if ($sortField === 'max_count') {
            $requirementsQuery
                ->orderByRaw('case when max_count is null then 0 else 1 end')
                ->orderBy('max_count')
                ->orderBy('entry_type_code')
                ->orderBy('required_domain');

            return;
        }

        foreach ($this->sortColumns($sortField) as $column) {
            $requirementsQuery->orderBy($column);
        }
    }

    /**
     * @return array<string, string>
     */
    public function sortOptions(): array {
        return [
            'entry_type_code' => __('Auftragstyp'),
            'required_domain' => __('Pflicht-Domain'),
            'enforce_phase' => __('Phase'),
            'severity' => __('Schweregrad'),
            'max_count' => __('Maximalanzahl'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function conditionOptions(): array {
        return [
            'always' => __('Immer'),
            'conditional' => __('Mit Bedingung'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function allowMultiOptions(): array {
        return [
            'single' => __('Einzelauswahl'),
            'multi' => __('Mehrfachauswahl'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function noteOptions(): array {
        return [
            'with_note' => __('Mit Hinweis'),
            'without_note' => __('Ohne Hinweis'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function maxCountOptions(): array {
        return [
            'open' => __('Offen'),
            'bounded' => __('Begrenzt'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function phaseLabels(): array {
        return [
            ClassificationRequirementPhase::OnCreate->value => __('Bei Erstellung'),
            ClassificationRequirementPhase::BeforeComplete->value => __('Vor Abschluss'),
            ClassificationRequirementPhase::BeforeSign->value => __('Vor Signatur'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function severityLabels(): array {
        return [
            ClassificationRequirementSeverity::Hard->value => __('Blockierend'),
            ClassificationRequirementSeverity::Soft->value => __('Hinweis'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function domainLabels(): array {
        return [
            ClassificationDomain::EntryType->value => __('Auftragstypen'),
            ClassificationDomain::Activity->value => __('Tätigkeiten'),
            ClassificationDomain::DefectType->value => __('Fehlertypen'),
            ClassificationDomain::RootCause->value => __('Ursachen'),
            ClassificationDomain::Result->value => __('Ergebnisse'),
            ClassificationDomain::Priority->value => __('Prioritäten'),
            ClassificationDomain::GoodwillReason->value => __('Kulanzgründe'),
            ClassificationDomain::ReworkReason->value => __('Nacharbeitsgründe'),
            ClassificationDomain::ProductGroup->value => __('Produktgruppen'),
            ClassificationDomain::DienstmittelType->value => __('Dienstmitteltypen'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function requiredDomainOptions(): array {
        $options = [];
        foreach (ClassificationDomain::cases() as $domain) {
            if ($domain === ClassificationDomain::EntryType) {
                continue;
            }

            $options[$domain->value] = $this->domainLabels()[$domain->value] ?? $domain->value;
        }

        return $options;
    }
}
