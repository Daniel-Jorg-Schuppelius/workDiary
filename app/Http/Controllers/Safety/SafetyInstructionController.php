<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstructionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Safety;

use App\Enums\Safety\HazardAssessmentStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Safety\{HazardAssessment, SafetyInstruction, SafetyInstructionParticipant};
use App\Models\Training\{TrainingCourse, TrainingCourseVersion};
use App\Models\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Safety\SafetyInstructionService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Unterweisungs-Register (Feature 132): Liste, Erfassungsdialog mit
 * Teilnehmer-Mehrfachauswahl, Nachweis-Ansicht (Teilnehmerliste +
 * Signaturstatus) und der Bestätigungs-Klick der Teilnehmerin.
 */
class SafetyInstructionController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly SafetyInstructionService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', SafetyInstruction::class);

        $query = SafetyInstruction::query()
            ->with(['instructor:id,name', 'assessment:id,assessment_no,version,area'])
            ->withCount([
                'participants',
                'participants as signed_participants_count' => fn($q) => $q->whereNotNull('signed_at'),
            ])
            ->orderByDesc('held_on')
            ->orderByDesc('instruction_no');

        if ($request->query('open') === '1') {
            $query->whereHas('participants', fn($q) => $q->whereNull('signed_at'));
        }

        return view('safety.instructions.index', [
            'instructions' => $query->paginate(30)->withQueryString(),
            'onlyOpen' => $request->query('open') === '1',
            'dueCount' => SafetyInstructionParticipant::query()
                ->whereNotNull('next_due_on')
                ->whereDate('next_due_on', '<=', now()->toDateString())
                ->count(),
            'canManage' => Gate::allows('create', SafetyInstruction::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', SafetyInstruction::class);

        return view('safety.instructions._form_dialog', [
            'instruction' => null,
            'users' => $this->userOptions(),
            'assessments' => $this->assessmentOptions(),
            'courses' => $this->courseOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', SafetyInstruction::class);

        /** @var User $actor */
        $actor = Auth::user();
        [$data, $participantIds] = $this->validateInstruction($request);

        $instruction = $this->service->create($this->currentOrganization(), $actor, $data, $participantIds);

        return redirect()
            ->route('safety.instructions.show', $instruction)
            ->with('success', __('safety.register.flash.instruction_created'));
    }

    /** Nachweis-Ansicht: Teilnehmerliste mit Signaturstatus. */
    public function show(SafetyInstruction $instruction): View {
        Gate::authorize('view', $instruction);

        $instruction->load([
            'participants' => fn($q) => $q->with('user:id,name')->orderBy('id'),
            'instructor:id,name',
            'assessment',
            'trainingCourse',
            'trainingCourseVersion',
        ]);

        /** @var User $viewer */
        $viewer = Auth::user();

        return view('safety.instructions.show', [
            'instruction' => $instruction,
            'canManage' => Gate::allows('update', $instruction),
            'ownParticipant' => $instruction->participants->first(fn(SafetyInstructionParticipant $p) => (int) $p->user_id === (int) $viewer->id),
        ]);
    }

    public function edit(SafetyInstruction $instruction): View {
        Gate::authorize('update', $instruction);

        return view('safety.instructions._form_dialog', [
            'instruction' => $instruction->load('participants'),
            'users' => $this->userOptions(),
            'assessments' => $this->assessmentOptions(),
            'courses' => $this->courseOptions(),
        ]);
    }

    public function update(Request $request, SafetyInstruction $instruction): RedirectResponse {
        Gate::authorize('update', $instruction);

        [$data, $participantIds] = $this->validateInstruction($request);
        $this->service->update($instruction, $data, $participantIds);

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.instruction_updated'));
    }

    public function destroy(SafetyInstruction $instruction): RedirectResponse {
        Gate::authorize('delete', $instruction);

        $this->service->delete($instruction);

        return redirect()
            ->route('safety.instructions.index')
            ->with('success', __('safety.register.flash.instruction_deleted'));
    }

    /** Bestätigungs-Klick der Teilnehmerin (nur die eigene Zeile, s. Policy). */
    public function sign(Request $request, SafetyInstruction $instruction, SafetyInstructionParticipant $participant): RedirectResponse {
        abort_unless((int) $participant->safety_instruction_id === (int) $instruction->id, 404);
        Gate::authorize('sign', $participant);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->sign($participant, $actor, ip: $request->ip());

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.participation_signed'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function validateInstruction(Request $request): array {
        // Sqid-Inputs vor der Validierung dekodieren.
        if ($request->filled('instructor_user_id')) {
            $request->merge(['instructor_user_id' => Sqid::decodeOrNumeric(User::class, $request->input('instructor_user_id'))]);
        }
        if ($request->filled('hazard_assessment_id')) {
            $request->merge(['hazard_assessment_id' => Sqid::decodeOrNumeric(HazardAssessment::class, $request->input('hazard_assessment_id'))]);
        }
        if ($request->filled('training_course_id')) {
            $request->merge(['training_course_id' => Sqid::decodeOrNumeric(TrainingCourse::class, $request->input('training_course_id'))]);
        }
        if ($request->filled('training_course_version_id')) {
            $request->merge(['training_course_version_id' => Sqid::decodeOrNumeric(TrainingCourseVersion::class, $request->input('training_course_version_id'))]);
        }
        $participantInput = $request->input('participant_ids');
        if (is_array($participantInput)) {
            $request->merge(['participant_ids' => array_values(array_filter(array_map(
                static fn($v) => $v === null || $v === '' ? null : Sqid::decodeOrNumeric(User::class, $v),
                $participantInput,
            )))]);
        }

        $data = $request->validate([
            'topic' => ['required', 'string', 'min:2', 'max:180'],
            'hazard_assessment_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('hazard_assessments')],
            // Feature 145: Kursbezug macht die Teilnahme zum Trainings-Nachweis.
            'training_course_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('training_courses')],
            'training_course_version_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('training_course_versions')],
            'held_on' => ['required', 'date'],
            'instructor_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'repeat_interval_months' => ['nullable', 'integer', 'between:1,120'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', new ExistsInCurrentOrganization('users')],
        ]);

        /** @var list<int> $participantIds */
        $participantIds = array_map('intval', $data['participant_ids']);
        unset($data['participant_ids']);

        return [$data, $participantIds];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function userOptions() {
        return User::query()->inCurrentOrganization()->orderBy('name')->get(['id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int, TrainingCourse> */
    private function courseOptions() {
        return TrainingCourse::query()
            ->active()
            ->with(['versions' => fn($q) => $q->orderByDesc('version')])
            ->orderBy('title')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, HazardAssessment> */
    private function assessmentOptions() {
        return HazardAssessment::query()
            ->where('status', '!=', HazardAssessmentStatus::Archived->value)
            ->orderBy('assessment_no')
            ->orderByDesc('version')
            ->get(['id', 'assessment_no', 'version', 'area']);
    }
}
