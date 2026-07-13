<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceQueueController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\{ServiceQueue, SlaContract, Team};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\View\View;

/**
 * Queue-Verwaltung (Feature 065, MVP-150): CRUD als Modal-Dialoge.
 * Genau EINE Default-Queue je Org (Wechsel atomar); Löschen nur, wenn
 * keine Tickets mehr zugeordnet sind (kein stilles Umhängen).
 */
class ServiceQueueController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', ServiceQueue::class);

        return view('helpdesk.queues.index', [
            'queues' => ServiceQueue::query()
                ->withCount('tickets')
                ->with('team:id,name')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'slaContracts' => SlaContract::query()->orderBy('label')->get(['id', 'label']),
            'canManage' => Gate::allows('create', ServiceQueue::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ServiceQueue::class);

        return view('helpdesk.queues._form_dialog', [
            'queue' => new ServiceQueue(['visibility' => 'internal']),
            'isEdit' => false,
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'slaContracts' => SlaContract::query()->orderBy('label')->get(['id', 'label']),
        ]);
    }

    public function edit(ServiceQueue $queue): View {
        Gate::authorize('update', $queue);

        return view('helpdesk.queues._form_dialog', [
            'queue' => $queue,
            'isEdit' => true,
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'slaContracts' => SlaContract::query()->orderBy('label')->get(['id', 'label']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ServiceQueue::class);

        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $queue = ServiceQueue::query()->create($data);
            if ($data['is_default']) {
                $this->makeDefault($queue);
            }
        });

        return redirect()->route('helpdesk.queues.index')
            ->with('success', __('Queue angelegt.'));
    }

    public function update(Request $request, ServiceQueue $queue): RedirectResponse {
        Gate::authorize('update', $queue);

        $data = $this->validated($request, $queue);

        DB::transaction(function () use ($queue, $data): void {
            $queue->update($data);
            if ($data['is_default']) {
                $this->makeDefault($queue);
            }
        });

        return redirect()->route('helpdesk.queues.index')
            ->with('success', __('Queue gespeichert.'));
    }

    public function destroy(ServiceQueue $queue): RedirectResponse {
        Gate::authorize('delete', $queue);

        if ($queue->tickets()->exists()) {
            return back()->with('error', __('Queue enthält Tickets und kann nicht gelöscht werden.'));
        }
        if ($queue->is_default) {
            return back()->with('error', __('Die Standard-Queue kann nicht gelöscht werden.'));
        }

        $queue->delete();

        return redirect()->route('helpdesk.queues.index')
            ->with('success', __('Queue gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ServiceQueue $queue = null): array {
        foreach (['team_id' => Team::class, 'default_sla_contract_id' => SlaContract::class] as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => \App\Support\Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'team_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('teams')],
            'default_sla_contract_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('sla_contracts')],
            'visibility' => ['required', 'in:internal,portal'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['team_id'] = $data['team_id'] ?? null;
        $data['default_sla_contract_id'] = $data['default_sla_contract_id'] ?? null;
        if ($queue === null) {
            /** @var \App\Models\User $user */
            $user = \Illuminate\Support\Facades\Auth::user();
            $data['organization_id'] = (int) $user->organization_id;
        }

        return $data;
    }

    /** Genau eine Default-Queue je Org. */
    private function makeDefault(ServiceQueue $queue): void {
        ServiceQueue::query()
            ->whereKeyNot($queue->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
        if (! $queue->is_default) {
            $queue->update(['is_default' => true]);
        }
    }
}
