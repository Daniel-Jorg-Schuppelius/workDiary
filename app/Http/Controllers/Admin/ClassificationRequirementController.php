<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Classification\ClassificationRequirementSeverity;
use App\Http\Controllers\Controller;
use App\Models\ClassificationRequirement;
use App\Models\Organization;
use App\Services\Classification\ClassificationResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassificationRequirementController extends Controller {
    public function __construct(
        private readonly ClassificationResolver $resolver,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ClassificationRequirement::class);

        $organization = $this->currentOrganization();
        $query = trim(request()->string('q')->toString());
        $domainFilter = $this->normalizeDomainFilter(request()->string('domain')->toString());
        $phaseFilter = $this->normalizePhaseFilter(request()->string('phase')->toString());
        $severityFilter = $this->normalizeSeverityFilter(request()->string('severity')->toString());
        $sortField = $this->normalizeSortField(request()->string('sort')->toString());

        $requirementsQuery = ClassificationRequirement::query()
            ->where('organization_id', $organization->id);

        if ($query !== '') {
            $requirementsQuery->where(function ($builder) use ($query): void {
                $builder
                    ->where('entry_type_code', 'like', "%{$query}%")
                    ->orWhere('required_domain', 'like', "%{$query}%")
                    ->orWhere('note', 'like', "%{$query}%")
                    ->orWhere('only_if_json', 'like', "%{$query}%");
            });
        }

        if ($domainFilter !== null) {
            $requirementsQuery->where('required_domain', $domainFilter);
        }

        if ($phaseFilter !== null) {
            $requirementsQuery->where('enforce_phase', $phaseFilter);
        }

        if ($severityFilter !== null) {
            $requirementsQuery->where('severity', $severityFilter);
        }

        $this->applySorting($requirementsQuery, $sortField);

        $requirements = $requirementsQuery->get();
        $phaseLabels = $this->phaseLabels();
        $severityLabels = $this->severityLabels();
        $domainLabels = $this->domainLabels();
        $sortOptions = $this->sortOptions();

        return view('admin.classification-requirements.index', [
            'organization' => $organization,
            'requirements' => $requirements,
            'phaseLabels' => $phaseLabels,
            'severityLabels' => $severityLabels,
            'domainLabels' => $domainLabels,
            'sortOptions' => $sortOptions,
            'activeFilters' => [
                'q' => $query,
                'domain' => $domainFilter ?? 'all',
                'phase' => $phaseFilter ?? 'all',
                'severity' => $severityFilter ?? 'all',
                'sort' => $sortField,
            ],
            'activeFilterChips' => array_values(array_filter([
                $query !== '' ? __('Suche: :value', ['value' => $query]) : null,
                $domainFilter !== null ? __('Domain: :value', ['value' => $domainLabels[$domainFilter] ?? $domainFilter]) : null,
                $phaseFilter !== null ? __('Phase: :value', ['value' => $phaseLabels[$phaseFilter] ?? $phaseFilter]) : null,
                $severityFilter !== null ? __('Schweregrad: :value', ['value' => $severityLabels[$severityFilter] ?? $severityFilter]) : null,
                $sortField !== 'entry_type_code' ? __('Sortierung: :value', ['value' => $sortOptions[$sortField] ?? $sortField]) : null,
            ])),
            'hasActiveFilters' => $query !== '' || $domainFilter !== null || $phaseFilter !== null || $severityFilter !== null || $sortField !== 'entry_type_code',
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClassificationRequirement::class);

        return view('admin.classification-requirements._form_dialog', [
            'requirement' => new ClassificationRequirement([
                'allow_multi' => false,
                'min_count' => 1,
            ]),
            'entryTypeOptions' => $this->entryTypeOptions(),
            'entryTypePresets' => $this->entryTypePresets(),
            'requiredDomainPresets' => $this->requiredDomainPresets(),
            'requiredDomainOptions' => $this->requiredDomainOptions(),
            'phaseLabels' => $this->phaseLabels(),
            'severityLabels' => $this->severityLabels(),
            'onlyIfJsonText' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ClassificationRequirement::class);

        $validated = $this->validatePayload($request);

        ClassificationRequirement::query()->create(array_merge(
            ['organization_id' => $this->currentOrganization()->id],
            $validated,
        ));

        return redirect()->route('admin.classification-requirements.index')
            ->with('success', __('Pflichtregel wurde angelegt.'));
    }

    public function edit(ClassificationRequirement $classificationRequirement): View {
        Gate::authorize('update', $classificationRequirement);
        $this->ensureOrganizationScoped($classificationRequirement);

        return view('admin.classification-requirements._form_dialog', [
            'requirement' => $classificationRequirement,
            'entryTypeOptions' => $this->entryTypeOptions(),
            'entryTypePresets' => $this->entryTypePresets(),
            'requiredDomainPresets' => $this->requiredDomainPresets(),
            'requiredDomainOptions' => $this->requiredDomainOptions(),
            'phaseLabels' => $this->phaseLabels(),
            'severityLabels' => $this->severityLabels(),
            'onlyIfJsonText' => $classificationRequirement->only_if_json !== null
                ? json_encode($classificationRequirement->only_if_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : null,
        ]);
    }

    public function update(Request $request, ClassificationRequirement $classificationRequirement): RedirectResponse {
        Gate::authorize('update', $classificationRequirement);
        $this->ensureOrganizationScoped($classificationRequirement);

        $validated = $this->validatePayload($request, $classificationRequirement);
        $classificationRequirement->update($validated);

        return redirect()->route('admin.classification-requirements.index')
            ->with('success', __('Pflichtregel wurde aktualisiert.'));
    }

    public function destroy(ClassificationRequirement $classificationRequirement): RedirectResponse {
        Gate::authorize('delete', $classificationRequirement);
        $this->ensureOrganizationScoped($classificationRequirement);

        $classificationRequirement->delete();

        return redirect()->route('admin.classification-requirements.index')
            ->with('success', __('Pflichtregel wurde gelöscht.'));
    }

    private function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);

        $organization = app('currentOrganization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    private function ensureOrganizationScoped(ClassificationRequirement $classificationRequirement): void {
        abort_unless($classificationRequirement->organization_id === $this->currentOrganization()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ClassificationRequirement $requirement = null): array {
        $request->merge($this->applyRequirementPresetFallbacks($request));

        $organization = $this->currentOrganization();
        $entryTypeCodes = array_keys($this->entryTypeOptions());
        $requiredDomains = array_keys($this->requiredDomainOptions());
        $phases = array_map(static fn (ClassificationRequirementPhase $phase): string => $phase->value, ClassificationRequirementPhase::cases());
        $severities = array_map(static fn (ClassificationRequirementSeverity $severity): string => $severity->value, ClassificationRequirementSeverity::cases());

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
            return back()->withInput()->withErrors(['max_count' => __('Maximalanzahl darf nicht kleiner als Minimalanzahl sein.')])->throwResponse();
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
     * @return array<string, list<string>>|null
     */
    private function parseOnlyIfJson(?string $json): ?array {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);
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

    private function normalizePhaseFilter(string $value): ?string {
        foreach (ClassificationRequirementPhase::cases() as $phase) {
            if ($phase->value === $value) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeDomainFilter(string $value): ?string {
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

    private function normalizeSortField(string $value): string {
        return array_key_exists($value, $this->sortOptions()) ? $value : 'entry_type_code';
    }

    private function normalizeSeverityFilter(string $value): ?string {
        foreach (ClassificationRequirementSeverity::cases() as $severity) {
            if ($severity->value === $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function entryTypeOptions(): array {
        $rows = $this->resolver->list($this->currentOrganization()->id, ClassificationDomain::EntryType);
        $options = [];
        foreach ($rows as $row) {
            $options[$row->code] = $row->label;
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function applyRequirementPresetFallbacks(Request $request): array {
        $entryTypeCode = trim((string) $request->input('entry_type_code', ''));
        $requiredDomain = trim((string) $request->input('required_domain', ''));
        $preset = $this->resolveRequirementPreset($entryTypeCode, $requiredDomain);

        if ($preset === []) {
            return [];
        }

        $merged = [];

        foreach (['enforce_phase', 'severity', 'min_count', 'max_count'] as $field) {
            $value = $request->input($field);
            if (($value === null || $value === '') && array_key_exists($field, $preset)) {
                $merged[$field] = $preset[$field];
            }
        }

        if (! $request->has('allow_multi') && array_key_exists('allow_multi', $preset)) {
            $merged['allow_multi'] = $preset['allow_multi'];
        }

        return $merged;
    }

    /**
     * @return array{enforce_phase?: string, severity?: string, min_count?: int, max_count?: int|null, allow_multi?: bool}
     */
    private function resolveRequirementPreset(string $entryTypeCode, string $requiredDomain): array {
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
    private function entryTypePresets(): array {
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
    private function requiredDomainPresets(): array {
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

    /**
     * @return array<string, string>
     */
    private function requiredDomainOptions(): array {
        $options = [];
        foreach (ClassificationDomain::cases() as $domain) {
            if ($domain === ClassificationDomain::EntryType) {
                continue;
            }

            $options[$domain->value] = $this->domainLabels()[$domain->value] ?? $domain->value;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function domainLabels(): array {
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
     * @return list<string>
     */
    private function sortColumns(string $sortField): array {
        return match ($sortField) {
            'required_domain' => ['required_domain', 'entry_type_code', 'enforce_phase'],
            'enforce_phase' => ['enforce_phase', 'entry_type_code', 'required_domain'],
            default => ['entry_type_code', 'enforce_phase', 'required_domain'],
        };
    }

    /**
     * @param Builder<ClassificationRequirement> $requirementsQuery
     */
    private function applySorting(Builder $requirementsQuery, string $sortField): void {
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

        foreach ($this->sortColumns($sortField) as $column) {
            $requirementsQuery->orderBy($column);
        }
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(): array {
        return [
            'entry_type_code' => __('Auftragstyp'),
            'required_domain' => __('Pflicht-Domain'),
            'enforce_phase' => __('Phase'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function phaseLabels(): array {
        return [
            ClassificationRequirementPhase::OnCreate->value => __('Bei Erstellung'),
            ClassificationRequirementPhase::BeforeComplete->value => __('Vor Abschluss'),
            ClassificationRequirementPhase::BeforeSign->value => __('Vor Signatur'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function severityLabels(): array {
        return [
            ClassificationRequirementSeverity::Hard->value => __('Blockierend'),
            ClassificationRequirementSeverity::Soft->value => __('Hinweis'),
        ];
    }
}
