<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionAgentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Sales\{CommissionAgent, CommissionRule};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Externe Vermittler als Provisionsempfänger (MVP-989); Rechte wie die Provisionsregeln. */
class CommissionAgentController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', CommissionRule::class);

        return view('sales.commission-agents.index', [
            'agents' => CommissionAgent::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'canManage' => Gate::allows('create', CommissionRule::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CommissionRule::class);

        return view('sales.commission-agents._form_dialog', ['agent' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', CommissionRule::class);
        CommissionAgent::query()->create($this->validated($request) + [
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('commission-agents.index')->with('success', __('commission.flash.agent_saved'));
    }

    public function edit(CommissionAgent $agent): View {
        Gate::authorize('create', CommissionRule::class);

        return view('sales.commission-agents._form_dialog', ['agent' => $agent]);
    }

    public function update(Request $request, CommissionAgent $agent): RedirectResponse {
        Gate::authorize('create', CommissionRule::class);
        $agent->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('commission-agents.index')->with('success', __('commission.flash.agent_saved'));
    }

    /** @return array{name: string, company: string|null, email: string|null, note: string|null} */
    private function validated(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return ['name' => (string) $data['name'], 'company' => $data['company'] ?? null, 'email' => $data['email'] ?? null, 'note' => $data['note'] ?? null];
    }
}
