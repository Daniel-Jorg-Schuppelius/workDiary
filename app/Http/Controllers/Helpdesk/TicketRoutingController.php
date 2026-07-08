<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketRoutingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{ServiceTicket, TicketRoutingRule};
use App\Services\ServiceTicket\TicketRoutingService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Routing-Regel-Verwaltung (Feature 065, MVP-153) inkl. Dry-Run gegen ein
 * Beispiel-Ticket (Regel-Test-Modus — protokolliert mit dry_run-Flag,
 * ändert nie etwas). Recht: helpdesk.queue.manage (Queue-Hoheit).
 */
class TicketRoutingController extends Controller {
    public function index(): View {
        Gate::authorize(Permission::HelpdeskQueueManage->value);

        return view('helpdesk.routing.index', [
            'rules' => TicketRoutingRule::query()->orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::HelpdeskQueueManage->value);
        $data = $this->validated($request);

        TicketRoutingRule::query()->create([
            ...$data,
            'organization_id' => (int) $request->user()?->organization_id,
        ]);

        return redirect()->route('helpdesk.routing.index')->with('success', __('Regel angelegt.'));
    }

    public function update(Request $request, TicketRoutingRule $rule): RedirectResponse {
        Gate::authorize(Permission::HelpdeskQueueManage->value);
        $data = $this->validated($request);

        // Versionierung: jede inhaltliche Änderung erhöht die Version —
        // das Ausführungsprotokoll bleibt eindeutig zuordenbar.
        $rule->update([...$data, 'version' => $rule->version + 1]);

        return redirect()->route('helpdesk.routing.index')->with('success', __('Regel gespeichert.'));
    }

    public function destroy(TicketRoutingRule $rule): RedirectResponse {
        Gate::authorize(Permission::HelpdeskQueueManage->value);
        $rule->delete();

        return redirect()->route('helpdesk.routing.index')->with('success', __('Regel gelöscht.'));
    }

    /** Dry-Run: Regeln gegen ein bestehendes Ticket testen (keine Änderung). */
    public function dryRun(Request $request, TicketRoutingService $routing): RedirectResponse {
        Gate::authorize(Permission::HelpdeskQueueManage->value);

        $data = $request->validate(['ticket_no' => ['required', 'string', 'max:40']]);
        $ticket = ServiceTicket::query()->where('ticket_no', $data['ticket_no'])->first();
        if ($ticket === null) {
            return back()->with('error', __('Ticket :no nicht gefunden.', ['no' => $data['ticket_no']]));
        }

        $log = $routing->apply($ticket, dryRun: true);
        $summary = $log === []
            ? __('Keine Regel trifft zu.')
            : implode('; ', array_map(
                fn(array $entry): string => $entry['rule']->name . ' → ' . json_encode($entry['actions'], JSON_UNESCAPED_UNICODE),
                $log,
            ));

        return back()->with('success', __('Dry-Run für :no: :summary', ['no' => $ticket->ticket_no, 'summary' => $summary]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
            'conditions' => ['required', 'json'],
            'actions' => ['required', 'json'],
            'active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'position' => (int) $data['position'],
            'conditions' => json_decode((string) $data['conditions'], true),
            'actions' => json_decode((string) $data['actions'], true),
            'active' => (bool) ($data['active'] ?? true),
        ];
    }
}
