<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Procedure\{ProcedureProofType, ProcedureRiskLevel, ProcedureStepType};
use App\Models\{ProcedureTemplate, ProcedureTemplateVersion, User};
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Prozedurvorlagen-Designer (Feature 026 / MVP-025): Listenseite +
 * Modal zum Anlegen der Vorlage und Voll-Seiten-Designer zum Bearbeiten
 * der Schrittdefinitionen der jeweils aktuellen Draft-Version.
 *
 * Die Schritte werden als dynamische Zeilen
 * `steps[i][code|step_type|label|...|config]` aus dem Designer geliefert
 * und ueber {@see ProcedureTemplateService::syncSteps()} ersetzt. Nach
 * Veroeffentlichung einer Version sind deren Schritte unveraenderlich;
 * Korrekturen erzeugen eine neue Draft-Version.
 */
class ProcedureTemplateController extends Controller {
    public function __construct(
        private readonly ProcedureTemplateService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ProcedureTemplate::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $query = ProcedureTemplate::query()->with('versions');

        if ($filters['q'] !== '') {
            $query->search($filters['q']);
        }
        if ($filters['status'] === 'active') {
            $query->where('active', true);
        } elseif ($filters['status'] === 'archived') {
            $query->where('active', false);
        }

        $templates = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('procedures.templates.index', [
            'templates' => $templates,
            'filters' => $filters,
            'hasActiveFilters' => $filters['q'] !== '' || $filters['status'] !== 'all',
            'canManage' => Gate::allows('create', ProcedureTemplate::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ProcedureTemplate::class);

        return view('procedures.templates._form_dialog', ['template' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ProcedureTemplate::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'domain' => ['nullable', 'string', 'max:40'],
        ]);

        /** @var User $author */
        $author = Auth::user();
        $template = $this->service->create($author->organization()->firstOrFail(), $author, $data);

        return redirect()
            ->route('procedures.edit', $template)
            ->with('success', __('procedure.flash.created'));
    }

    /**
     * Voll-Seiten-Designer: Stammdaten der Vorlage + Schritte der
     * aktuellen Draft-Version. Existiert keine Draft-Version (alle
     * veroeffentlicht), wird die Schritt-Liste read-only dargestellt;
     * der Nutzer kann eine neue Version anlegen.
     */
    public function edit(ProcedureTemplate $template): View {
        Gate::authorize('update', $template);

        $template->load('versions.steps');
        $draft = $this->latestDraft($template);

        return view('procedures.templates.edit', [
            'template' => $template,
            'draft' => $draft,
            'versions' => $template->versions->sortByDesc('version')->values(),
            'stepTypes' => ProcedureStepType::cases(),
            'proofTypes' => ProcedureProofType::cases(),
            'riskLevels' => ProcedureRiskLevel::cases(),
            'canPublish' => Gate::allows('publish', $template),
        ]);
    }

    /**
     * Speichert Stammdaten, Versions-Metadaten und (bei Draft) die
     * komplette Schritt-Liste.
     */
    public function update(Request $request, ProcedureTemplate $template): RedirectResponse {
        Gate::authorize('update', $template);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'domain' => ['nullable', 'string', 'max:40'],
            'risk_level' => ['nullable', Rule::enum(ProcedureRiskLevel::class)],
            'change_note' => ['nullable', 'string', 'max:2000'],
            'applicability_entry_types' => ['nullable', 'string', 'max:2000'],
            'applicability_tags' => ['nullable', 'string', 'max:2000'],
            'steps' => ['nullable', 'array'],
            'steps.*' => ['array'],
            'steps.*.code' => ['nullable', 'string', 'max:60'],
            'steps.*.step_type' => ['nullable', Rule::enum(ProcedureStepType::class)],
            'steps.*.label' => ['nullable', 'string', 'max:180'],
            'steps.*.description' => ['nullable', 'string', 'max:2000'],
            'steps.*.required' => ['nullable'],
            'steps.*.blocking' => ['nullable'],
            'steps.*.requires_second_person' => ['nullable'],
            'steps.*.requires_proof_type' => ['nullable', Rule::enum(ProcedureProofType::class)],
            'steps.*.required_role' => ['nullable', 'string', 'max:40'],
            'steps.*.required_qualification_code' => ['nullable', 'string', 'max:60'],
            'steps.*.condition_step' => ['nullable', 'string', 'max:60'],
            'steps.*.condition_equals' => ['nullable', 'string', 'max:120'],
        ]);

        $this->service->updateTemplate($template, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'domain' => $data['domain'] ?? null,
        ]);

        $draft = $this->latestDraft($template);
        if ($draft !== null) {
            $this->service->updateVersion($draft, [
                'risk_level' => $data['risk_level'] ?? ProcedureRiskLevel::Normal->value,
                'change_note' => $data['change_note'] ?? null,
                'applicability' => $this->buildApplicability($data),
            ]);

            $this->service->syncSteps($draft, $this->normalizeSteps($data['steps'] ?? []));
        }

        return redirect()
            ->route('procedures.edit', $template)
            ->with('success', __('procedure.flash.updated'));
    }

    public function storeVersion(ProcedureTemplate $template): RedirectResponse {
        Gate::authorize('update', $template);

        if ($this->latestDraft($template) !== null) {
            return redirect()
                ->route('procedures.edit', $template)
                ->with('info', __('procedure.flash.draftExists'));
        }

        /** @var User $author */
        $author = Auth::user();
        $latest = $template->versions()->orderByDesc('version')->first();
        $version = $this->service->addVersion($template, $author, [
            'change_note' => __('procedure.flash.versionInitial'),
            'risk_level' => $latest !== null ? $latest->risk_level : ProcedureRiskLevel::Normal,
            'applicability' => $latest?->applicability,
        ]);

        // Schritte der letzten Version als Startpunkt uebernehmen.
        if ($latest !== null) {
            $carryOver = [];
            foreach ($latest->steps as $s) {
                $carryOver[] = [
                    'code' => $s->code,
                    'step_type' => $s->step_type->value,
                    'label' => $s->label,
                    'description' => $s->description,
                    'required' => $s->required,
                    'blocking' => $s->blocking,
                    'config' => $s->config,
                    'required_role' => $s->required_role,
                    'required_qualification_code' => $s->required_qualification_code,
                    'requires_second_person' => $s->requires_second_person,
                    'requires_proof_type' => $s->requires_proof_type?->value,
                ];
            }
            $this->service->syncSteps($version, $carryOver);
        }

        return redirect()
            ->route('procedures.edit', $template)
            ->with('success', __('procedure.flash.versionCreated', ['version' => $version->version]));
    }

    public function publish(ProcedureTemplate $template, ProcedureTemplateVersion $version): RedirectResponse {
        Gate::authorize('publish', $template);
        abort_unless($version->procedure_template_id === $template->id, 404);

        /** @var User $publisher */
        $publisher = Auth::user();
        $published = $this->service->publish($version, $publisher);

        return redirect()
            ->route('procedures.edit', $template)
            ->with('success', __('procedure.flash.published', ['version' => $published->version]));
    }

    public function activate(ProcedureTemplate $template): RedirectResponse {
        Gate::authorize('update', $template);

        $this->service->updateTemplate($template, ['active' => true]);

        return redirect()->back()->with('success', __('procedure.flash.activated'));
    }

    public function archive(ProcedureTemplate $template): RedirectResponse {
        Gate::authorize('update', $template);

        $this->service->updateTemplate($template, ['active' => false]);

        return redirect()->back()->with('success', __('procedure.flash.archived'));
    }

    /**
     * Liefert die einzige offene Draft-Version (groesste Versionsnummer
     * ohne published_at) oder null.
     */
    private function latestDraft(ProcedureTemplate $template): ?ProcedureTemplateVersion {
        return $template->versions
            ->whereNull('published_at')
            ->sortByDesc('version')
            ->first();
    }

    /**
     * Normalisiert die Designer-Zeilen, verwirft leere Zeilen und
     * bettet bedingte Schritte (depends_on) sowie Mess-Toleranz in
     * `config` ein.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeSteps(array $rows): array {
        $steps = [];
        $index = 0;
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $type = (string) ($row['step_type'] ?? '');
            if ($code === '' || $label === '' || ProcedureStepType::tryFrom($type) === null) {
                continue;
            }

            $config = [];
            $conditionStep = trim((string) ($row['condition_step'] ?? ''));
            if ($conditionStep !== '') {
                // Bedingter Schritt (Folge-MVP, additiv in config): dieser
                // Schritt ist nur anwendbar, wenn der referenzierte Schritt
                // den angegebenen Wert/Status hat.
                $config['depends_on'] = [
                    'step_code' => $conditionStep,
                    'equals' => trim((string) ($row['condition_equals'] ?? '')) ?: null,
                ];
            }

            $proof = (string) ($row['requires_proof_type'] ?? '');

            $steps[] = [
                'code' => $code,
                'step_type' => $type,
                'label' => $label,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'required' => $this->boolFlag($row['required'] ?? false),
                'blocking' => $this->boolFlag($row['blocking'] ?? false),
                'config' => $config === [] ? null : $config,
                'required_role' => trim((string) ($row['required_role'] ?? '')) ?: null,
                'required_qualification_code' => trim((string) ($row['required_qualification_code'] ?? '')) ?: null,
                'requires_second_person' => $this->boolFlag($row['requires_second_person'] ?? false),
                'requires_proof_type' => ProcedureProofType::tryFrom($proof)?->value,
            ];
            $index++;
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function buildApplicability(array $data): ?array {
        $rules = [];
        $entryTypes = $this->splitList((string) ($data['applicability_entry_types'] ?? ''));
        if ($entryTypes !== []) {
            $rules['diary_entry_type'] = $entryTypes;
        }
        $tags = $this->splitList((string) ($data['applicability_tags'] ?? ''));
        if ($tags !== []) {
            $rules['tags_any'] = $tags;
        }

        return $rules === [] ? null : $rules;
    }

    /**
     * @return array<int, string>
     */
    private function splitList(string $value): array {
        return collect(preg_split('/[,\n]+/', $value) ?: [])
            ->map(fn($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }

    private function boolFlag(mixed $value): bool {
        return in_array($value, [true, '1', 1, 'on', 'true'], true);
    }
}
