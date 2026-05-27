<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketStatus};
use App\Exceptions\ServiceTicketException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveServiceTicketRequest;
use App\Models\{Organization, ServiceTicket, User};
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceTicketController extends Controller {
    public function __construct(private readonly ServiceTicketService $tickets) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ServiceTicket::class);

        $q = trim($request->string('q')->toString());
        $statusFilter = $request->string('status')->toString();
        $priorityFilter = $request->string('priority')->toString();
        $assigneeFilter = $request->string('assignee')->toString();

        $query = ServiceTicket::query()
            ->with(['customer:id,name', 'asset:id,name,asset_no', 'assignedTo:id,name'])
            ->latest('reported_at');

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('ticket_no', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            });
        }
        if ($statusFilter !== '' && ServiceTicketStatus::tryFrom($statusFilter) !== null) {
            $query->where('status', $statusFilter);
        }
        if ($priorityFilter !== '' && ServiceTicketPriority::tryFrom($priorityFilter) !== null) {
            $query->where('priority', $priorityFilter);
        }
        if ($assigneeFilter === 'me' && $request->user() !== null) {
            $query->where('assigned_to_user_id', $request->user()->getAuthIdentifier());
        } elseif ($assigneeFilter === 'unassigned') {
            $query->whereNull('assigned_to_user_id');
        }

        $tickets = $query->paginate(25)->withQueryString();

        return view('service-tickets.index', [
            'tickets' => $tickets,
            'statusOptions' => $this->statusOptions(),
            'priorityOptions' => $this->priorityOptions(),
            'filters' => [
                'q' => $q,
                'status' => $statusFilter,
                'priority' => $priorityFilter,
                'assignee' => $assigneeFilter,
            ],
            'canCreate' => Gate::allows('create', ServiceTicket::class),
        ]);
    }

    public function show(ServiceTicket $ticket): View {
        Gate::authorize('view', $ticket);
        $ticket->load(['customer:id,name', 'asset:id,name,asset_no', 'assignedTo:id,name', 'reportedBy:id,name', 'slaContract']);

        return view('service-tickets.show', [
            'ticket' => $ticket,
            'statusOptions' => $this->statusOptions(),
            'priorityOptions' => $this->priorityOptions(),
            'canUpdate' => Gate::allows('update', $ticket),
            'canAssign' => Gate::allows('assign', $ticket),
            'canClose' => Gate::allows('close', $ticket),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ServiceTicket::class);

        return view('service-tickets.create', [
            'priorityOptions' => $this->priorityOptions(),
        ]);
    }

    public function store(SaveServiceTicketRequest $request): RedirectResponse {
        Gate::authorize('create', ServiceTicket::class);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }
        $org = $this->resolveOrganization($user);

        $ticket = $this->tickets->create($org, $user, $request->validated());

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Ticket angelegt: :no', ['no' => $ticket->ticket_no]));
    }

    public function transition(Request $request, ServiceTicket $ticket): RedirectResponse {
        Gate::authorize('transition', $ticket);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_column(ServiceTicketStatus::cases(), 'value'))],
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }
        $target = ServiceTicketStatus::from($data['status']);
        if ($target === ServiceTicketStatus::Closed) {
            Gate::authorize('close', $ticket);
        }

        try {
            $this->tickets->transition($ticket, $user, $target);
        } catch (ServiceTicketException $e) {
            return back()->withErrors(['status' => __($e->getMessage())]);
        }

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Ticket-Status aktualisiert.'));
    }

    public function assign(Request $request, ServiceTicket $ticket): RedirectResponse {
        Gate::authorize('assign', $ticket);

        $data = $request->validate([
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $this->tickets->assign($ticket, $user, $data['assignee_user_id'] ?? null);

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Zuweisung aktualisiert.'));
    }

    public function destroy(ServiceTicket $ticket): RedirectResponse {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return redirect()
            ->route('service-tickets.index')
            ->with('success', __('Ticket gelöscht.'));
    }

    private function resolveOrganization(User $user): Organization {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                return $org;
            }
        }

        $org = $user->organization()->firstOrFail();

        return $org;
    }

    /** @return array<string, string> */
    private function statusOptions(): array {
        $opts = [];
        foreach (ServiceTicketStatus::cases() as $case) {
            $opts[$case->value] = $case->label();
        }

        return $opts;
    }

    /** @return array<string, string> */
    private function priorityOptions(): array {
        $opts = [];
        foreach (ServiceTicketPriority::cases() as $case) {
            $opts[$case->value] = $case->label();
        }

        return $opts;
    }
}
