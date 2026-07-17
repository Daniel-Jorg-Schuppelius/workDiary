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
    private const ALLOWED_SORTS = ['ticket_no', 'title', 'priority', 'status', 'resolution_due_at', 'reported_at'];

    public function __construct(private readonly ServiceTicketService $tickets) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ServiceTicket::class);

        $q = trim($request->string('q')->toString());
        $statusFilter = $request->string('status')->toString();
        $priorityFilter = $request->string('priority')->toString();
        $assigneeFilter = $request->string('assignee')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'reported_at';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $query = ServiceTicket::query()
            ->with(['customer:id,name', 'asset:id,name,asset_no', 'assignedTo:id,name'])
            ->orderBy($sort, $dir);

        if ($q !== '') {
            $query->search($q);
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
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function show(Request $request, ServiceTicket $ticket): View {
        Gate::authorize('view', $ticket);
        $ticket->load([
            'customer:id,name', 'asset:id,name,asset_no', 'assignedTo:id,name', 'reportedBy:id,name', 'slaContract',
            // Detail-Widgets (MVP-160): SLA-Uhr, Beobachter, Verknüpfungen, Major Incident.
            'slaClockSegments', 'watchers.user:id,name', 'links.linked', 'waitOwner:id,name', 'incidentLead:id,name',
            // Zugeordnete Probleme (MVP-156) + Changes (MVP-157) im Verknüpfungs-Widget.
            'problems', 'changes',
        ]);

        // Timeline (MVP-152): Konversation + Status-Audits + SLA + Anhänge
        // gemischt; Typ-Filter-Chips + „mehr laden" über GET-Parameter.
        $timelineType = (string) $request->query('timeline_type', '');
        $timelineLimit = max(1, min(500, (int) $request->query('timeline_limit', 50)));
        $timeline = app(\App\Services\Timeline\ServiceTicketTimelineService::class)->forTicket(
            $ticket,
            $timelineType !== '' ? [$timelineType] : null,
            $timelineLimit,
        );

        return view('service-tickets.show', [
            'ticket' => $ticket,
            'timelineItems' => $timeline['items'],
            'timelineHasMore' => $timeline['hasMore'],
            'timelineType' => $timelineType,
            'timelineLimit' => $timelineLimit,
            'canNote' => \Illuminate\Support\Facades\Gate::allows(\App\Enums\User\Permission::HelpdeskTicketInternalNote->value),
            'statusOptions' => $this->statusOptions(),
            'priorityOptions' => $this->priorityOptions(),
            'canUpdate' => Gate::allows('update', $ticket),
            'canAssign' => Gate::allows('assign', $ticket),
            'canClose' => Gate::allows('close', $ticket),
            'orgUsers' => User::query()
                ->where('organization_id', $ticket->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ServiceTicket::class);

        return view('service-tickets._form_dialog', [
            'ticket' => null,
            'isDialog' => true,
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
            'assignee_user_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        // MVP-160: das Formular sendet eine User-Sqid — strikt mit der
        // Zielklasse dekodieren und org-gescopt auflösen (nie Cross-Tenant).
        $assigneeId = null;
        $raw = (string) ($data['assignee_user_id'] ?? '');
        if ($raw !== '') {
            $assigneeId = \App\Support\Sqid::decode(User::class, $raw);
            $exists = $assigneeId !== null && User::query()
                ->whereKey($assigneeId)
                ->where('organization_id', $ticket->organization_id)
                ->exists();
            if (! $exists) {
                return back()->withErrors(['assignee_user_id' => __('Bearbeiter nicht gefunden.')]);
            }
        }

        $this->tickets->assign($ticket, $user, $assigneeId);

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
