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

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\ClassificationRequirement;
use App\Services\Classification\{RequirementIndexFilter, RequirementInput, RequirementPresets};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassificationRequirementController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly RequirementIndexFilter $indexFilter,
        private readonly RequirementPresets $presets,
        private readonly RequirementInput $input,
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

        $this->indexFilter->applyFilters($requirementsQuery, [
            'q' => $query,
            'domain' => $domainFilter,
            'condition' => $conditionFilter,
            'allow_multi' => $allowMultiFilter,
            'note' => $noteFilter,
            'max_count' => $maxCountFilter,
            'phase' => $phaseFilter,
            'severity' => $severityFilter,
        ]);

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
            'entryTypeOptions' => $this->input->entryTypeOptions($this->currentOrganization()),
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

        $organization = $this->currentOrganization();
        $validated = $this->input->validated($request, $organization);

        ClassificationRequirement::query()->create(array_merge(
            ['organization_id' => $organization->id],
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
            'entryTypeOptions' => $this->input->entryTypeOptions($this->currentOrganization()),
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

        $validated = $this->input->validated($request, $this->currentOrganization(), $classificationRequirement);
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
}
