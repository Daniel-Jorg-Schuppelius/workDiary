<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QueueBoardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketPriority};
use App\Http\Controllers\Controller;
use App\Models\{ServiceQueue, ServiceTicket, User};
use App\Services\ServiceTicket\{ServiceTicketService, TicketStatusMachine};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Queue-Board (Feature 065, MVP-160): Tickets als Spalten je Status
 * (Reihenfolge = Zustandsmaschine) mit Massenzuweisung und Queue-Wechsel.
 * Bewusst OHNE Drag&Drop — Statuswechsel bleiben auf der Detailseite.
 * Bulk-Aktionen prüfen das Gate JE Ticket und zählen Übersprungene
 * (Muster ExpenseApprovalController::bulkApprove).
 */
class QueueBoardController extends Controller {
    /** Obergrenze der geladenen Karten (Board bleibt bedienbar). */
    private const MAX_TICKETS = 300;

    public function __construct(private readonly ServiceTicketService $tickets) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ServiceTicket::class);

        /** @var User $user */
        $user = Auth::user();

        $q = trim($request->string('q')->toString());
        $queueFilter = $request->string('queue')->toString();
        $assigneeFilter = $request->string('assignee')->toString();
        $priorityFilter = $request->string('priority')->toString();
        $kindFilter = $request->string('kind')->toString();

        $queueId = Sqid::decode(ServiceQueue::class, $queueFilter !== '' ? $queueFilter : null);

        $query = ServiceTicket::query()
            ->with(['assignedTo:id,name', 'queue:id,name'])
            ->when($user->organization_id !== null, fn ($builder) => $builder->where('organization_id', $user->organization_id))
            ->orderBy('reported_at')
            ->orderBy('id');

        if ($q !== '') {
            $query->search($q);
        }
        if ($queueId !== null) {
            $query->where('queue_id', $queueId);
        }
        if ($assigneeFilter === 'me') {
            $query->where('assigned_to_user_id', $user->id);
        } elseif ($assigneeFilter === 'unassigned') {
            $query->whereNull('assigned_to_user_id');
        }
        if ($priorityFilter !== '' && ServiceTicketPriority::tryFrom($priorityFilter) !== null) {
            $query->where('priority', $priorityFilter);
        }
        if ($kindFilter !== '' && ServiceTicketKind::tryFrom($kindFilter) !== null) {
            $query->where('kind', $kindFilter);
        }

        $board = $query->limit(self::MAX_TICKETS + 1)->get();
        $isLimited = $board->count() > self::MAX_TICKETS;
        $board = $board->take(self::MAX_TICKETS);

        return view('helpdesk.board.index', [
            'columns' => app(TicketStatusMachine::class)->statusOrder(),
            'byStatus' => $board->groupBy(fn (ServiceTicket $ticket): string => $ticket->status->value),
            'isLimited' => $isLimited,
            'maxTickets' => self::MAX_TICKETS,
            'queues' => ServiceQueue::query()->orderBy('name')->get(['id', 'name']),
            'orgUsers' => User::query()
                ->where('organization_id', $user->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'priorityOptions' => ServiceTicketPriority::cases(),
            'kindOptions' => ServiceTicketKind::cases(),
            'filters' => [
                'q' => $q,
                'queue' => $queueFilter,
                'assignee' => $assigneeFilter,
                'priority' => $priorityFilter,
                'kind' => $kindFilter,
            ],
            'canBulk' => Gate::allows('assign', new ServiceTicket) || Gate::allows('update', new ServiceTicket),
        ]);
    }

    /**
     * Massenzuweisung: `ids[]` sind Ticket-Sqids, `assignee` eine User-Sqid.
     * Je Ticket wird das assign-Gate geprüft; Übersprungene werden gezählt.
     */
    public function bulkAssign(Request $request): RedirectResponse {
        Gate::authorize('viewAny', ServiceTicket::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
            'assignee' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $assigneeId = Sqid::decode(User::class, $data['assignee']);
        $assignee = $assigneeId !== null
            ? User::query()
                ->whereKey($assigneeId)
                ->where('organization_id', $user->organization_id)
                ->first()
            : null;
        if ($assignee === null) {
            return back()->withErrors(['assignee' => __('Bearbeiter nicht gefunden.')]);
        }

        [$tickets, $skipped] = $this->resolveTickets($data['ids'], $user);
        $assigned = 0;
        foreach ($tickets as $ticket) {
            if (! Gate::allows('assign', $ticket)) {
                $skipped++;

                continue;
            }
            $this->tickets->assign($ticket, $user, (int) $assignee->id);
            $assigned++;
        }

        return redirect()->route('helpdesk.board.index')
            ->with('success', $this->bulkMessage(__(':n Tickets zugewiesen.', ['n' => $assigned]), $skipped));
    }

    /**
     * Queue-Wechsel in Masse: `queue` ist eine ServiceQueue-Sqid. Der Wechsel
     * läuft über {@see ServiceTicketService::moveToQueue()} und auditiert
     * `service_ticket.requeued` (Datenbasis für MVP-159).
     */
    public function bulkMove(Request $request): RedirectResponse {
        Gate::authorize('viewAny', ServiceTicket::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
            'queue' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $queueId = Sqid::decode(ServiceQueue::class, $data['queue']);
        $queue = $queueId !== null
            ? ServiceQueue::query()
                ->whereKey($queueId)
                ->when($user->organization_id !== null, fn ($builder) => $builder->where('organization_id', $user->organization_id))
                ->first()
            : null;
        if ($queue === null) {
            return back()->withErrors(['queue' => __('Ziel-Queue nicht gefunden.')]);
        }

        [$tickets, $skipped] = $this->resolveTickets($data['ids'], $user);
        $moved = 0;
        foreach ($tickets as $ticket) {
            if (! Gate::allows('update', $ticket)) {
                $skipped++;

                continue;
            }
            $this->tickets->moveToQueue($ticket, $user, $queue);
            $moved++;
        }

        return redirect()->route('helpdesk.board.index')
            ->with('success', $this->bulkMessage(__(':n Tickets verschoben.', ['n' => $moved]), $skipped));
    }

    /**
     * Dekodiert die Ticket-Sqids STRIKT mit der Zielklasse und lädt nur
     * Tickets der eigenen Organisation; ungültige/fremde IDs zählen als
     * übersprungen.
     *
     * @param  list<string>  $sqids
     * @return array{0: \Illuminate\Database\Eloquent\Collection<int, ServiceTicket>, 1: int}
     */
    private function resolveTickets(array $sqids, User $user): array {
        $ids = [];
        $skipped = 0;
        foreach ($sqids as $sqid) {
            $id = Sqid::decode(ServiceTicket::class, $sqid);
            if ($id === null) {
                $skipped++;

                continue;
            }
            $ids[] = $id;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, ServiceTicket> $tickets */
        $tickets = ServiceTicket::query()
            ->whereIn('id', $ids)
            ->when($user->organization_id !== null, fn ($builder) => $builder->where('organization_id', $user->organization_id))
            ->get();

        $skipped += count($ids) - $tickets->count();

        return [$tickets, $skipped];
    }

    private function bulkMessage(string $message, int $skipped): string {
        if ($skipped > 0) {
            $message .= ' ' . __(':n übersprungen (Status oder Berechtigung).', ['n' => $skipped]);
        }

        return $message;
    }
}
