<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequirementInput.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Models\{ClassificationRequirement, Organization};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validiert und normalisiert das Pflichtregel-Formular (Preset-Fallbacks,
 * Unique-Regel je Organisation/Domain/Phase, JSON-Bedingung, min/max-Prüfung)
 * und liefert die Auftragstyp-Optionen. Aus dem
 * ClassificationRequirementController extrahiert (Refactoring Welle 2, B6c).
 */
class RequirementInput {
    public function __construct(
        private readonly ClassificationResolver $resolver,
        private readonly RequirementIndexFilter $indexFilter,
        private readonly RequirementPresets $presets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validated(Request $request, Organization $organization, ?ClassificationRequirement $requirement = null): array {
        $request->merge($this->presets->applyFallbacks($request->all()));

        $entryTypeCodes = array_keys($this->entryTypeOptions($organization));
        $requiredDomains = array_keys($this->indexFilter->requiredDomainOptions());
        $phases = array_map(static fn(ClassificationRequirementPhase $phase): string => $phase->value, ClassificationRequirementPhase::cases());
        $severities = array_map(static fn(ClassificationRequirementSeverity $severity): string => $severity->value, ClassificationRequirementSeverity::cases());

        $validated = $request->validate([
            'entry_type_code' => [
                'required',
                'string',
                Rule::in($entryTypeCodes),
                Rule::unique('classification_requirements')
                    ->ignore($requirement?->id)
                    ->where(static function ($query) use ($organization, $request): void {
                        $query
                            ->where('organization_id', $organization->id)
                            ->where('required_domain', (string) $request->input('required_domain'))
                            ->where('enforce_phase', (string) $request->input('enforce_phase'));
                    }),
            ],
            'required_domain' => ['required', 'string', Rule::in($requiredDomains)],
            'enforce_phase' => ['required', 'string', Rule::in($phases)],
            'severity' => ['required', 'string', Rule::in($severities)],
            'allow_multi' => ['nullable', 'boolean'],
            'min_count' => ['required', 'integer', 'min:1', 'max:50'],
            'max_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'only_if_json' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $onlyIfJson = $this->parseOnlyIfJson($validated['only_if_json'] ?? null);
        $maxCount = $validated['max_count'] ?? null;
        if ($maxCount !== null && (int) $maxCount < (int) $validated['min_count']) {
            back()->withInput()->withErrors(['max_count' => __('Maximalanzahl darf nicht kleiner als Minimalanzahl sein.')])->throwResponse();
        }

        return [
            'entry_type_code' => (string) $validated['entry_type_code'],
            'required_domain' => (string) $validated['required_domain'],
            'enforce_phase' => (string) $validated['enforce_phase'],
            'severity' => (string) $validated['severity'],
            'allow_multi' => $request->boolean('allow_multi'),
            'min_count' => (int) $validated['min_count'],
            'max_count' => $maxCount !== null ? (int) $maxCount : null,
            'only_if_json' => $onlyIfJson,
            'note' => $this->nullableString($validated['note'] ?? null),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function entryTypeOptions(Organization $organization): array {
        $rows = $this->resolver->list($organization->id, ClassificationDomain::EntryType);
        $options = [];
        foreach ($rows as $row) {
            $options[$row->code] = $row->label;
        }

        return $options;
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function parseOnlyIfJson(?string $json): ?array {
        if ($json === null || trim($json) === '') {
            return null;
        }

        try {
            $decoded = JsonHelper::decode($json);
        } catch (\InvalidArgumentException) {
            return back()->withInput()->withErrors(['only_if_json' => __('Bedingung muss valides JSON sein.')])->throwResponse();
        }
        if (! is_array($decoded)) {
            return back()->withInput()->withErrors(['only_if_json' => __('Bedingung muss valides JSON sein.')])->throwResponse();
        }

        $normalized = [];
        foreach ($decoded as $key => $values) {
            if (! is_string($key) || $key === '' || ! is_array($values)) {
                return back()->withInput()->withErrors(['only_if_json' => __('Bedingung muss ein Objekt aus String-Keys und Listen sein.')])->throwResponse();
            }

            $normalizedValues = [];
            foreach ($values as $value) {
                if (! is_scalar($value)) {
                    return back()->withInput()->withErrors(['only_if_json' => __('Bedingungswerte müssen Strings oder Zahlen sein.')])->throwResponse();
                }
                $normalizedValues[] = (string) $value;
            }
            $normalized[$key] = array_values(array_unique($normalizedValues));
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
