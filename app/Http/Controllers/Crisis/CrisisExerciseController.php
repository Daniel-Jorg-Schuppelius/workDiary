<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisExerciseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Crisis\{CrisisCase, CrisisExercise};
use App\Models\{ProcedureTemplate, User};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Übungen & Tests (Feature 070, MVP-220): eigenständige Register —
 * verfälschen nie echte Krisenakten; Beobachtungen verbessern Playbooks.
 */
class CrisisExerciseController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', CrisisCase::class);

        return view('crisis.exercises', [
            'exercises' => CrisisExercise::query()->with('playbookTemplate')->orderByDesc('id')->paginate(25),
            'canManage' => Gate::allows('create', CrisisCase::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CrisisCase::class);

        return view('crisis._exercise_form_dialog', [
            'templates' => ProcedureTemplate::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $request->merge(['playbook_template_id' => \App\Support\Sqid::decodeOrNumeric(ProcedureTemplate::class, $request->input('playbook_template_id'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'scenario' => ['required', 'string', 'max:10000'],
            'playbook_template_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('procedure_templates')],
            'next_due_on' => ['nullable', 'date'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        CrisisExercise::query()->create([
            ...$data,
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $actor->id,
        ]);

        return back()->with('status', __('Übung geplant.'));
    }

    public function documentForm(CrisisExercise $exercise): View {
        Gate::authorize('create', CrisisCase::class);

        return view('crisis._exercise_document_dialog', ['exercise' => $exercise]);
    }

    /** Durchführung dokumentieren: Beobachtungen, Abweichungen, Wirksamkeit. */
    public function document(Request $request, CrisisExercise $exercise): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $data = $request->validate([
            'participants' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'deviations' => ['nullable', 'string', 'max:10000'],
            'effectiveness' => ['required', 'in:effective,partly,ineffective'],
            'follow_up' => ['nullable', 'string', 'max:10000'],
            'next_due_on' => ['nullable', 'date'],
        ]);

        $exercise->update([...$data, 'exercised_at' => now()]);
        $exercise->audit('crisis.exercise_documented', ['effectiveness' => $data['effectiveness']]);

        return back()->with('status', __('Übung dokumentiert.'));
    }
}
