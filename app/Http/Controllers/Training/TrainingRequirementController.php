<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingRequirementController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Training;

use App\Enums\Training\TrainingRequirementSubject;
use App\Enums\User\UserRole;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Training\{TrainingCourse, TrainingRequirement};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Training\TrainingAssignmentService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pflichtmatrix (Feature 145): Zuordnung Rolle bzw. Tätigkeitsbereich
 * (Team) × Kurs. Jede Änderung zieht die Soll-Einträge nach — die Matrix
 * ist die einzige Quelle der Pflicht-Zuweisungen.
 */
class TrainingRequirementController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly TrainingAssignmentService $assignments,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', TrainingRequirement::class);

        $kind = (string) $request->query('kind', '');

        $query = TrainingRequirement::query()
            ->with('course')
            ->orderBy('subject_kind')
            ->orderBy('subject_key');
        if (TrainingRequirementSubject::tryFrom($kind) instanceof TrainingRequirementSubject) {
            $query->where('subject_kind', $kind);
        }

        return view('training.requirements.index', [
            'requirements' => $query->paginate(30)->withQueryString(),
            'kind' => $kind,
            'activeCount' => TrainingRequirement::query()->where('is_active', true)->count(),
            'canManage' => Gate::allows('create', TrainingRequirement::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', TrainingRequirement::class);

        return view('training.requirements._form_dialog', [
            'requirement' => null,
            'courses' => $this->courseOptions(),
            'teams' => $this->teamOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', TrainingRequirement::class);

        $organization = $this->currentOrganization();
        TrainingRequirement::query()->create([
            'organization_id' => $organization->id,
            'source' => 'manual',
        ] + $this->validateRequirement($request));

        $this->assignments->syncOrganization($organization);

        return redirect()
            ->route('training.requirements.index')
            ->with('success', __('training.flash.requirement_created'));
    }

    public function edit(TrainingRequirement $requirement): View {
        Gate::authorize('update', $requirement);

        return view('training.requirements._form_dialog', [
            'requirement' => $requirement,
            'courses' => $this->courseOptions(),
            'teams' => $this->teamOptions(),
        ]);
    }

    public function update(Request $request, TrainingRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $requirement);

        $requirement->update($this->validateRequirement($request));
        $this->assignments->syncOrganization($this->currentOrganization());

        return redirect()
            ->back()
            ->with('success', __('training.flash.requirement_updated'));
    }

    public function destroy(TrainingRequirement $requirement): RedirectResponse {
        Gate::authorize('delete', $requirement);

        $requirement->delete();
        $this->assignments->syncOrganization($this->currentOrganization());

        return redirect()
            ->route('training.requirements.index')
            ->with('success', __('training.flash.requirement_deleted'));
    }

    /** Soll-Einträge von Hand nachziehen (neue Mitarbeitende, Rollenwechsel). */
    public function sync(): RedirectResponse {
        Gate::authorize('create', TrainingRequirement::class);

        $result = $this->assignments->syncOrganization($this->currentOrganization());

        return redirect()
            ->route('training.requirements.index')
            ->with('success', __('training.flash.assignments_synced', $result));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequirement(Request $request): array {
        if ($request->filled('training_course_id')) {
            $request->merge(['training_course_id' => Sqid::decodeOrNumeric(TrainingCourse::class, $request->input('training_course_id'))]);
        }
        // Zielgruppe kommt je nach Art aus zwei Feldern; der Schlüssel wird
        // hier vereinheitlicht (Rolle = Slug, Team = numerische ID).
        $kind = (string) $request->input('subject_kind', TrainingRequirementSubject::Role->value);
        if ($kind === TrainingRequirementSubject::Team->value) {
            $request->merge(['subject_team_id' => Sqid::decodeOrNumeric(Team::class, $request->input('subject_team_id'))]);
        }

        $data = $request->validate([
            'training_course_id' => ['required', 'integer', new ExistsInCurrentOrganization('training_courses')],
            'subject_kind' => ['required', 'string', Rule::enum(TrainingRequirementSubject::class)],
            'subject_role' => ['required_if:subject_kind,role', 'nullable', 'string', Rule::in(array_column(UserRole::cases(), 'value'))],
            'subject_team_id' => ['required_if:subject_kind,team', 'nullable', 'integer', new ExistsInCurrentOrganization('teams')],
            'first_due_days' => ['required', 'integer', 'between:0,3650'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'training_course_id' => (int) $data['training_course_id'],
            'subject_kind' => $data['subject_kind'],
            'subject_key' => $data['subject_kind'] === TrainingRequirementSubject::Team->value
                ? (string) $data['subject_team_id']
                : (string) $data['subject_role'],
            'first_due_days' => (int) $data['first_due_days'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'note' => $data['note'] ?? null,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, TrainingCourse> */
    private function courseOptions() {
        return TrainingCourse::query()->active()->orderBy('title')->get(['id', 'title']);
    }

    /** @return \Illuminate\Support\Collection<int, Team> */
    private function teamOptions() {
        return Team::query()->orderBy('name')->get(['id', 'name']);
    }
}
