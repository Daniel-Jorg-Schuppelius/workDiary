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
        $requirements = ClassificationRequirement::query()
            ->where('organization_id', $organization->id)
            ->orderBy('entry_type_code')
            ->orderBy('enforce_phase')
            ->orderBy('required_domain')
            ->get();

        return view('admin.classification-requirements.index', [
            'organization' => $organization,
            'requirements' => $requirements,
            'phaseLabels' => $this->phaseLabels(),
            'severityLabels' => $this->severityLabels(),
            'domainLabels' => $this->domainLabels(),
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
