<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisBiaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Enums\Crisis\CrisisProcessCriticality;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Crisis\{CrisisBusinessProcess, CrisisCase};
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Crisis\CrisisBiaService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** BIA-Register (MVP-943): Geschäftsprozesse pflegen, aus Registern übernehmen, in Krisenfälle übernehmen. */
class CrisisBiaController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly CrisisBiaService $bia) {}

    public function index(): View {
        Gate::authorize('viewAny', CrisisCase::class);

        return view('crisis.bia.index', [
            'processes' => CrisisBusinessProcess::query()->with('owner')->orderByRaw("CASE criticality WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->orderBy('name')->get(),
            'candidates' => Gate::allows('create', CrisisCase::class) ? $this->bia->candidates($this->currentOrganization()) : [],
            'canManage' => Gate::allows('create', CrisisCase::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CrisisCase::class);

        return view('crisis.bia._form_dialog', ['process' => null, 'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])]);
    }

    public function edit(CrisisBusinessProcess $process): View {
        Gate::authorize('create', CrisisCase::class);

        return view('crisis.bia._form_dialog', ['process' => $process, 'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        CrisisBusinessProcess::query()->create($this->validated($request) + ['organization_id' => $this->currentOrganization()->id, 'created_by' => $this->authUser()->id]);

        return redirect()->route('crisis.bia.index')->with('success', __('crisis.bia.flash.saved'));
    }

    public function update(Request $request, CrisisBusinessProcess $process): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $process->update($this->validated($request));

        return redirect()->route('crisis.bia.index')->with('success', __('crisis.bia.flash.saved'));
    }

    public function import(Request $request): RedirectResponse {
        Gate::authorize('create', CrisisCase::class);
        $data = $request->validate(['keys' => ['required', 'array', 'min:1'], 'keys.*' => ['string', 'max:100']]);
        $count = $this->bia->import($this->currentOrganization(), array_values($data['keys']), $this->authUser());

        return back()->with('success', __('crisis.bia.flash.imported', ['count' => $count]));
    }

    public function adopt(Request $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $request->merge(['process_id' => Sqid::decodeOrNumeric(CrisisBusinessProcess::class, $request->string('process_id')->toString())]);
        $data = $request->validate(['process_id' => ['required', 'integer', new ExistsInCurrentOrganization('crisis_business_processes')]]);
        $this->bia->adopt($case, CrisisBusinessProcess::query()->findOrFail((int) $data['process_id']));

        return back()->with('status', __('crisis.bia.flash.adopted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        if ($request->filled('owner_user_id')) {
            $request->merge(['owner_user_id' => Sqid::decodeOrNumeric(User::class, $request->string('owner_user_id')->toString())]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:4000'],
            'owner_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'criticality' => ['required', Rule::enum(CrisisProcessCriticality::class)],
            'rto_hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rpo_hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'mtpd_hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'dependencies' => ['nullable', 'string', 'max:4000'],
            'review_due_on' => ['nullable', 'date'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
