<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProgramController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Enums\Investments\InvestmentProgramStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\Investments\InvestmentProgram;
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Investments\InvestmentProgramService;
use App\Support\Sqid;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Investitionsprogramme (MVP-927): Liste, Portfolio, Jahresbudgets. */
class InvestmentProgramController extends Controller {
    public function __construct(private readonly InvestmentProgramService $programs) {}

    public function index(): View {
        Gate::authorize(P::InvestmentViewAny->value);

        return view('investments.programs.index', [
            'programs' => InvestmentProgram::query()->withCount('cases')->with('budgets')->orderByDesc('starts_year')->orderBy('name')->get(),
        ]);
    }

    public function create(): View {
        Gate::authorize(P::InvestmentManage->value);

        return view('investments.programs._form_dialog', ['program' => null, 'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        $actor = $request->user() ?? abort(401);
        $program = InvestmentProgram::query()->create($this->validated($request) + [
            'status' => InvestmentProgramStatus::Planning->value,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return redirect()->route('investments.programs.show', $program)->with('success', __('investment.program.flash.created'));
    }

    public function show(InvestmentProgram $program): View {
        Gate::authorize(P::InvestmentView->value);

        return view('investments.programs.show', ['program' => $program->load('responsible'), 'portfolio' => $this->programs->portfolio($program)]);
    }

    public function edit(InvestmentProgram $program): View {
        Gate::authorize(P::InvestmentManage->value);

        return view('investments.programs._form_dialog', ['program' => $program, 'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, InvestmentProgram $program): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        $program->update($this->validated($request) + ['updated_by' => $request->user()?->id]);

        return redirect()->route('investments.programs.show', $program)->with('success', __('investment.program.flash.saved'));
    }

    public function budgets(Request $request, InvestmentProgram $program): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        $data = $request->validate(['budget' => ['array'], 'budget.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999']]);
        $this->programs->saveBudgets($program, (array) ($data['budget'] ?? []));

        return back()->with('success', __('investment.program.flash.budgets'));
    }

    public function status(Request $request, InvestmentProgram $program): RedirectResponse {
        Gate::authorize(P::InvestmentManage->value);
        $data = $request->validate(['status' => ['required', Rule::enum(InvestmentProgramStatus::class)]]);
        $to = InvestmentProgramStatus::from((string) $data['status']);
        abort_unless($program->status->canTransitionTo($to), 422);
        $program->update(['status' => $to->value, 'updated_by' => $request->user()?->id]);

        return back()->with('success', __('investment.program.flash.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        if ($request->filled('responsible_user_id')) {
            $request->merge(['responsible_user_id' => Sqid::decodeOrNumeric(User::class, $request->string('responsible_user_id')->toString())]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'ends_year' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:starts_year', 'lte:' . ((int) $request->input('starts_year') + 15)],
            'currency' => ['required', Rule::enum(CurrencyCode::class)],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
        ]);
    }
}
