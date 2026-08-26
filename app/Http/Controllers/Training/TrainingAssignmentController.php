<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingAssignmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Training;

use App\Enums\Training\TrainingAssignmentState;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Training\{TrainingAssignment, TrainingCourse};
use App\Models\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Training\TrainingAssignmentService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Soll-Liste (Feature 145): wer schuldet welche Schulung bis wann und
 * womit ist sie nachgewiesen. Der Nachweis-Link zeigt in das
 * Arbeitsschutz-Register (Feature 132) — hier wird nichts signiert.
 */
class TrainingAssignmentController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request): View {
        Gate::authorize('viewAny', TrainingAssignment::class);

        $state = (string) $request->query('state', '');
        $today = Carbon::today();

        $query = TrainingAssignment::query()
            ->with(['user:id,name', 'course:id,title,validity_months', 'instruction:id,instruction_no,topic,held_on'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id');

        // Zustandsfilter direkt auf den Datumsspalten — kein Nachladen aller Zeilen.
        match ($state) {
            TrainingAssignmentState::Overdue->value => $query->whereNotNull('due_at')->where('due_at', '<', $today->toDateString()),
            TrainingAssignmentState::Due->value => $query->whereNotNull('due_at')
                ->where('due_at', '>=', $today->toDateString())
                ->whereNotNull('notify_from')
                ->where('notify_from', '<=', $today->toDateString()),
            TrainingAssignmentState::Fulfilled->value => $query->whereNotNull('fulfilled_at'),
            default => null,
        };

        return view('training.assignments.index', [
            'assignments' => $query->paginate(30)->withQueryString(),
            'state' => $state,
            'overdueCount' => TrainingAssignment::query()->overdue()->count(),
            'canManage' => Gate::allows('create', TrainingAssignment::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', TrainingAssignment::class);

        return view('training.assignments._form_dialog', [
            'users' => User::query()->inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'courses' => TrainingCourse::query()->active()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request, TrainingAssignmentService $service): RedirectResponse {
        Gate::authorize('create', TrainingAssignment::class);

        if ($request->filled('user_id')) {
            $request->merge(['user_id' => Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);
        }
        if ($request->filled('training_course_id')) {
            $request->merge(['training_course_id' => Sqid::decodeOrNumeric(TrainingCourse::class, $request->input('training_course_id'))]);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', new ExistsInCurrentOrganization('users')],
            'training_course_id' => ['required', 'integer', new ExistsInCurrentOrganization('training_courses')],
            'due_at' => ['nullable', 'date'],
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($data['user_id']);
        /** @var TrainingCourse $course */
        $course = TrainingCourse::query()->findOrFail($data['training_course_id']);

        $service->assignManually($this->currentOrganization(), $user, $course, $data['due_at'] ?? null);

        return redirect()
            ->route('training.assignments.index')
            ->with('success', __('training.flash.assignment_created'));
    }

    public function destroy(TrainingAssignment $assignment): RedirectResponse {
        Gate::authorize('delete', $assignment);

        $assignment->delete();

        return redirect()
            ->route('training.assignments.index')
            ->with('success', __('training.flash.assignment_deleted'));
    }
}
