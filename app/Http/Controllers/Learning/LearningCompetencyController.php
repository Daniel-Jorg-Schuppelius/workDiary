<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCompetencyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\User\{Permission, UserRole};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Learning\{Competency, CompetencyRequirement};
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Learning\LearningCompetencyService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kompetenzmatrix (Feature 149, MVP-745 — Oberfläche nachgezogen in MVP-798,
 * Befund `C3-03`): Katalog, Einschätzung und Soll-Stufen je Rolle. Die
 * Schreibregeln (keine Rückstufung durch Kurswiederholung, Ablauf zählt nicht)
 * liegen im `LearningCompetencyService`; die Kompetenz sperrt nichts.
 */
class LearningCompetencyController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly LearningCompetencyService $competencies) {}

    public function index(): View {
        Gate::authorize(Permission::LearningManage->value);

        $organization = $this->currentOrganization();
        $users = User::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deactivated_at')
            ->with('roles')
            ->orderBy('name')
            ->paginate(25);

        $matrix = $this->competencies->matrixFor($organization, $users->getCollection());

        // Lücken über den Dienst, damit „abgelaufen zählt nicht" nur an einer Stelle gilt.
        $gaps = [];
        foreach ($users as $user) {
            foreach ($this->competencies->gapsFor($user, array_values($user->getRoleNames()->all())) as $gap) {
                $gaps[$user->id][$gap['competency']->id] = $gap;
            }
        }

        return view('learning.competencies.index', [
            'users' => $users,
            // Auswahl für die Einschätzung: ganze Belegschaft, nicht nur die Matrix-Seite.
            'people' => User::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'matrix' => $matrix,
            'gaps' => $gaps,
            'catalog' => Competency::query()->orderBy('name')->get(),
            'requirements' => CompetencyRequirement::query()->with('competency')->orderBy('subject_key')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $organization = $this->currentOrganization();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('competencies', 'code')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'category' => ['nullable', 'string', 'max:60'],
            'max_level' => ['required', 'integer', 'between:1,10'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        Competency::query()->create($data + [
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        return redirect()->route('learning.competencies.index')
            ->with('success', __('learning.flash.competency_created'));
    }

    public function assess(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        // Das Formular sendet Sqids; numerische IDs bleiben gültig.
        $request->merge([
            'user_id' => Sqid::decodeOrNumeric(User::class, $request->input('user_id')),
            'competency_id' => Sqid::decodeOrNumeric(Competency::class, $request->input('competency_id')),
        ]);

        $data = $request->validate([
            'user_id' => ['required', 'integer', new ExistsInCurrentOrganization('users')],
            'competency_id' => ['required', 'integer', new ExistsInCurrentOrganization('competencies')],
            'level' => ['required', 'integer', 'between:1,10'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $assessor */
        $assessor = Auth::user();
        $this->competencies->assess(
            User::query()->findOrFail((int) $data['user_id']),
            Competency::query()->findOrFail((int) $data['competency_id']),
            (int) $data['level'],
            $assessor,
            $data['note'] ?? null,
        );

        return redirect()->route('learning.competencies.index')
            ->with('success', __('learning.flash.competency_assessed'));
    }

    public function storeRequirement(Request $request): RedirectResponse {
        Gate::authorize(Permission::LearningManage->value);

        $request->merge(['competency_id' => Sqid::decodeOrNumeric(Competency::class, $request->input('competency_id'))]);

        $data = $request->validate([
            'competency_id' => ['required', 'integer', new ExistsInCurrentOrganization('competencies')],
            'role' => ['required', 'string', Rule::in(array_column(UserRole::cases(), 'value'))],
            'required_level' => ['required', 'integer', 'between:1,10'],
        ]);

        $competency = Competency::query()->findOrFail((int) $data['competency_id']);

        // Erneutes Festlegen derselben Rolle ändert die Stufe statt zu scheitern.
        CompetencyRequirement::query()->updateOrCreate(
            [
                'organization_id' => $competency->organization_id,
                'competency_id' => $competency->id,
                'subject_kind' => 'role',
                'subject_key' => $data['role'],
            ],
            [
                'required_level' => $competency->clampLevel((int) $data['required_level']),
                'is_active' => true,
            ],
        );

        return redirect()->route('learning.competencies.index')
            ->with('success', __('learning.flash.competency_requirement_saved'));
    }
}
