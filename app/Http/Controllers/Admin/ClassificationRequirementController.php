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

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\ClassificationRequirement;
use App\Services\Classification\{ClassificationResolver, RequirementIndexFilter, RequirementPresets};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassificationRequirementController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClassificationResolver $resolver,
        private readonly RequirementIndexFilter $indexFilter,
        private readonly RequirementPresets $presets,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClassificationRequirement::class);

        $organization = $this->currentOrganization();
        $query = trim($request->string('q')->toString());
        $domainFilter = $this->indexFilter->normalizeDomainFilter($request->string('domain')->toString());
        $conditionFilter = $this->indexFilter->normalizeConditionFilter($request->string('condition')->toString());
        $allowMultiFilter = $this->indexFilter->normalizeAllowMultiFilter($request->string('allow_multi')->toString());
        $noteFilter = $this->indexFilter->normalizeNoteFilter($request->string('note')->toString());
        $maxCountFilter = $this->indexFilter->normalizeMaxCountFilter($request->string('max_count')->toString());
        $phaseFilter = $this->indexFilter->normalizePhaseFilter($request->string('phase')->toString());
        $severityFilter = $this->indexFilter->normalizeSeverityFilter($request->string('severity')->toString());
        $sortField = $this->indexFilter->normalizeSortField($request->string('sort')->toString());

        $requirementsQuery = ClassificationRequirement::query()
            ->where('organization_id', $organization->id);

        if ($query !== '') {
            $requirementsQuery->where(function ($builder) use ($query): void {
                $builder
                    ->whereLikeEscaped('entry_type_code', $query)
                    ->orWhereLikeEscaped('required_domain', $query)
                    ->orWhereLikeEscaped('note', $query)
                    ->orWhereLikeEscaped('only_if_json', $query);
            });
        }

        if ($domainFilter !== null) {
            $requirementsQuery->where('required_domain', $domainFilter);
        }

        if ($conditionFilter === 'conditional') {
            $requirementsQuery->whereNotNull('only_if_json');
        }

        if ($conditionFilter === 'always') {
            $requirementsQuery->whereNull('only_if_json');
        }

        if ($allowMultiFilter === 'multi') {
            $requirementsQuery->where('allow_multi', true);
        }

        if ($allowMultiFilter === 'single') {
            $requirementsQuery->where('allow_multi', false);
        }

        if ($noteFilter === 'with_note') {
            $requirementsQuery->whereNotNull('note');
        }

        if ($noteFilter === 'without_note') {
            $requirementsQuery->whereNull('note');
        }

        if ($maxCountFilter === 'bounded') {
            $requirementsQuery->whereNotNull('max_count');
        }

        if ($maxCountFilter === 'open') {
            $requirementsQuery->whereNull('max_count');
        }

        if ($phaseFilter !== null) {
            $requirementsQuery->where('enforce_phase', $phaseFilter);
        }

        if ($severityFilter !== null) {
            $requirementsQuery->where('severity', $severityFilter);
        }

        $this->indexFilter->applySorting($requirementsQuery, $sortField);

        $requirements = $requirementsQuery->get();
        $phaseLabels = $this->indexFilter->phaseLabels();
        $severityLabels = $this->indexFilter->severityLabels();
        $domainLabels = $this->indexFilter->domainLabels();
        $conditionOptions = $this->indexFilter->conditionOptions();
        $allowMultiOptions = $this->indexFilter->allowMultiOptions();
        $noteOptions = $this->indexFilter->noteOptions();
        $maxCountOptions = $this->indexFilter->maxCountOptions();
        $sortOptions = $this->indexFilter->sortOptions();

        return view('admin.classification-requirements.index', [
            'organization' => $organization,
            'requirements' => $requirements,
            'phaseLabels' => $phaseLabels,
            'severityLabels' => $severityLabels,
            'domainLabels' => $domainLabels,
            'conditionOptions' => $conditionOptions,
            'allowMultiOptions' => $allowMultiOptions,
            'noteOptions' => $noteOptions,
            'maxCountOptions' => $maxCountOptions,
            'sortOptions' => $sortOptions,
            'activeFilters' => [
                'q' => $query,
                'domain' => $domainFilter ?? 'all',
                'condition' => $conditionFilter ?? 'all',
                'allow_multi' => $allowMultiFilter ?? 'all',
                'note' => $noteFilter ?? 'all',
                'max_count' => $maxCountFilter ?? 'all',
                'phase' => $phaseFilter ?? 'all',
                'severity' => $severityFilter ?? 'all',
                'sort' => $sortField,
            ],
            'activeFilterChips' => array_values(array_filter([
                $query !== '' ? __('Suche: :value', ['value' => $query]) : null,
                $domainFilter !== null ? __('Domain: :value', ['value' => $domainLabels[$domainFilter] ?? $domainFilter]) : null,
                $conditionFilter !== null ? __('Bedingung: :value', ['value' => $conditionOptions[$conditionFilter] ?? $conditionFilter]) : null,
                $allowMultiFilter !== null ? __('Mehrfachauswahl: :value', ['value' => $allowMultiOptions[$allowMultiFilter] ?? $allowMultiFilter]) : null,
                $noteFilter !== null ? __('Hinweis: :value', ['value' => $noteOptions[$noteFilter] ?? $noteFilter]) : null,
                $maxCountFilter !== null ? __('Maximalanzahl: :value', ['value' => $maxCountOptions[$maxCountFilter] ?? $maxCountFilter]) : null,
                $phaseFilter !== null ? __('Phase: :value', ['value' => $phaseLabels[$phaseFilter] ?? $phaseFilter]) : null,
                $severityFilter !== null ? __('Schweregrad: :value', ['value' => $severityLabels[$severityFilter] ?? $severityFilter]) : null,
                $sortField !== 'entry_type_code' ? __('Sortierung: :value', ['value' => $sortOptions[$sortField] ?? $sortField]) : null,
            ])),
            'hasActiveFilters' => $query !== '' || $domainFilter !== null || $conditionFilter !== null || $allowMultiFilter !== null || $noteFilter !== null || $maxCountFilter !== null || $phaseFilter !== null || $severityFilter !== null || $sortField !== 'entry_type_code',
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
            'entryTypePresets' => $this->presets->entryTypePresets(),
            'requiredDomainPresets' => $this->presets->requiredDomainPresets(),
            'requiredDomainOptions' => $this->indexFilter->requiredDomainOptions(),
            'phaseLabels' => $this->indexFilter->phaseLabels(),
            'severityLabels' => $this->indexFilter->severityLabels(),
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
            'entryTypePresets' => $this->presets->entryTypePresets(),
            'requiredDomainPresets' => $this->presets->requiredDomainPresets(),
            'requiredDomainOptions' => $this->indexFilter->requiredDomainOptions(),
            'phaseLabels' => $this->indexFilter->phaseLabels(),
            'severityLabels' => $this->indexFilter->severityLabels(),
            'onlyIfJsonText' => $classificationRequirement->only_if_json !== null
                ? JsonHelper::encode($classificationRequirement->only_if_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
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

    private function ensureOrganizationScoped(ClassificationRequirement $classificationRequirement): void {
        abort_unless($classificationRequirement->organization_id === $this->currentOrganization()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ClassificationRequirement $requirement = null): array {
        $request->merge($this->presets->applyFallbacks($request->all()));

        $organization = $this->currentOrganization();
        $entryTypeCodes = array_keys($this->entryTypeOptions());
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
}
