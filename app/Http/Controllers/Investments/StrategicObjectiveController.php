<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StrategicObjectiveController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Investments\StrategicObjective;
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Investments\StrategicObjectiveService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Strategische Ziele für Investitionen (MVP-942). */
class StrategicObjectiveController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly StrategicObjectiveService $objectives) {}

    public function index(): View {
        Gate::authorize(P::InvestmentViewAny->value);

        return view('investments.objectives.index', [
            'objectives' => StrategicObjective::query()->with(['keyResults', 'owner'])->withCount('cases')->orderByDesc('is_active')->orderBy('title')->get(),
            'canManage' => Gate::allows(P::InvestmentManage->value),
        ]);
    }

    public function create(): View {
        Gate::authorize(P::InvestmentManage->value);

        return view('investments.objectives._form_dialog', ['objective' => null, 'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        [$data, $rows] = $this->validated($request);
        $objective = $this->objectives->save($this->currentOrganization(), null, $data, $rows, $this->authUser());

        return redirect()->route('investments.objectives.show', $objective)->with('success', __('investment.objective.flash.saved'));
    }

    public function show(StrategicObjective $objective): View {
        Gate::authorize(P::InvestmentView->value);

        return view('investments.objectives.show', [
            'objective' => $objective->load(['keyResults', 'owner']),
            'portfolio' => $this->objectives->portfolio($objective),
            'canManage' => Gate::allows(P::InvestmentManage->value),
        ]);
    }

    public function edit(StrategicObjective $objective): View {
        Gate::authorize(P::InvestmentManage->value);

        return view('investments.objectives._form_dialog', ['objective' => $objective->load('keyResults'), 'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, StrategicObjective $objective): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        [$data, $rows] = $this->validated($request);
        $this->objectives->save($this->currentOrganization(), $objective, $data, $rows, $this->authUser());

        return redirect()->route('investments.objectives.show', $objective)->with('success', __('investment.objective.flash.saved'));
    }

    /** @return array{0: array{title: string, description?: ?string, owner_user_id?: ?int, valid_from?: ?string, valid_until?: ?string, is_active: bool}, 1: list<array<string, mixed>>} */
    private function validated(Request $request): array {
        if ($request->filled('owner_user_id')) {
            $request->merge(['owner_user_id' => Sqid::decodeOrNumeric(User::class, $request->string('owner_user_id')->toString())]);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'key_results' => ['nullable', 'array', 'max:10'],
            'key_results.*.label' => ['nullable', 'string', 'max:200'],
            'key_results.*.unit' => ['nullable', 'string', 'max:20'],
            'key_results.*.baseline_value' => ['nullable', 'numeric'],
            'key_results.*.target_value' => ['nullable', 'numeric', 'required_with:key_results.*.label'],
            'key_results.*.current_value' => ['nullable', 'numeric'],
        ]);
        $rows = array_values((array) ($data['key_results'] ?? []));
        unset($data['key_results']);
        $data['is_active'] = $request->boolean('is_active', true);

        return [$data, $rows];
    }
}
