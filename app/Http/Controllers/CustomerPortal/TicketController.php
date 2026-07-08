<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\ServiceTicket\{ServiceTicketSource, ServiceTicketStatus, TicketMessageKind};
use App\Http\Controllers\Controller;
use App\Models\{ServiceQueue, ServiceTicket, ServiceTicketMessage, User};
use App\Services\ServiceTicket\{ServiceTicketService, TicketConversationService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;

/**
 * Portal-Tickets (Feature 065, MVP-160): nur EIGENE Tickets des Kunden,
 * nur PUBLIC-Inhalte (kind=public_reply/system_event — interne Notizen
 * erreichen das Portal strukturell nie); Anlage in Portal-Queues, Antwort,
 * Lösung bestätigen/wiedereröffnen, Zufriedenheits-Kurzbewertung.
 */
class TicketController extends Controller {
    public function index(): View {
        $user = $this->portalUser();

        return view('customer.tickets.index', [
            'tickets' => ServiceTicket::query()
                ->where('customer_id', $user->customer_id)
                ->orderByDesc('reported_at')
                ->paginate(25),
        ]);
    }

    public function show(ServiceTicket $ticket): View {
        $user = $this->portalUser();
        abort_unless((int) $ticket->customer_id === (int) $user->customer_id, 404);

        return view('customer.tickets.show', [
            'ticket' => $ticket,
            // Nur kundensichtbare Inhalte — Typfrage, keine Filter-Flags.
            'messages' => ServiceTicketMessage::query()
                ->where('service_ticket_id', $ticket->id)
                ->whereIn('kind', [TicketMessageKind::PublicReply->value, TicketMessageKind::SystemEvent->value])
                ->orderBy('created_at')
                ->get(),
            'rated' => DB::table('ticket_satisfaction')->where('service_ticket_id', $ticket->id)->exists(),
        ]);
    }

    public function store(Request $request, ServiceTicketService $tickets): RedirectResponse {
        $user = $this->portalUser();

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        $queueId = ServiceQueue::query()
            ->where('organization_id', $user->organization_id)
            ->where('visibility', 'portal')
            ->value('id');

        $ticket = $tickets->create($user->organization()->firstOrFail(), null, [
            ...$data,
            'customer_id' => $user->customer_id,
            'queue_id' => $queueId,
            'source' => ServiceTicketSource::CustomerPortal->value,
        ]);
        $ticket->forceFill(['requester_type' => $user->getMorphClass(), 'requester_id' => $user->id])->save();

        return redirect()->route('customer.tickets.show', $ticket)
            ->with('success', __('Ticket angelegt.'));
    }

    public function reply(Request $request, ServiceTicket $ticket, TicketConversationService $conversation): RedirectResponse {
        $user = $this->portalUser();
        abort_unless((int) $ticket->customer_id === (int) $user->customer_id, 404);

        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:10000']]);

        $conversation->inbound($ticket, $data['body'], 'portal', author: $user);

        return redirect()->route('customer.tickets.show', $ticket)
            ->with('success', __('Antwort gesendet.'));
    }

    /** Lösung bestätigen: done → accepted. */
    public function accept(ServiceTicket $ticket): RedirectResponse {
        $user = $this->portalUser();
        abort_unless((int) $ticket->customer_id === (int) $user->customer_id, 404);
        abort_unless($ticket->status === ServiceTicketStatus::Done, 422);

        $ticket->forceFill(['status' => ServiceTicketStatus::Accepted->value, 'accepted_at' => now()])->save();
        $ticket->audit('service_ticket.accepted_by_customer', ['portal_user' => $user->id]);

        return redirect()->route('customer.tickets.show', $ticket)
            ->with('success', __('Lösung bestätigt — vielen Dank!'));
    }

    /** Wiedereröffnen mit Pflichtgrund (done → in_progress). */
    public function reopen(Request $request, ServiceTicket $ticket, TicketConversationService $conversation): RedirectResponse {
        $user = $this->portalUser();
        abort_unless((int) $ticket->customer_id === (int) $user->customer_id, 404);
        abort_unless(in_array($ticket->status, [ServiceTicketStatus::Done, ServiceTicketStatus::Accepted], true), 422);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        // Kundennachricht + Statuswechsel (SLA-Historie bleibt unangetastet).
        $conversation->inbound($ticket, $data['reason'], 'portal', author: $user);
        $ticket->forceFill(['status' => ServiceTicketStatus::InProgress->value])->save();
        $ticket->audit('service_ticket.reopened', ['reason' => $data['reason'], 'portal_user' => $user->id]);

        return redirect()->route('customer.tickets.show', $ticket)
            ->with('success', __('Ticket wiedereröffnet.'));
    }

    /** Zufriedenheits-Kurzbewertung — eine Antwort je Ticket, nur nach Abschluss. */
    public function rate(Request $request, ServiceTicket $ticket): RedirectResponse {
        $user = $this->portalUser();
        abort_unless((int) $ticket->customer_id === (int) $user->customer_id, 404);
        abort_unless($ticket->status->isResolved(), 422);

        $data = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = DB::table('ticket_satisfaction')->where('service_ticket_id', $ticket->id)->exists();
        if (! $exists) {
            DB::table('ticket_satisfaction')->insert([
                'organization_id' => $ticket->organization_id,
                'service_ticket_id' => $ticket->id,
                'portal_user_id' => $user->id,
                'score' => (int) $data['score'],
                'comment' => $data['comment'] ?? null,
                'answered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('customer.tickets.show', $ticket)
            ->with('success', __('Danke für die Bewertung!'));
    }

    private function portalUser(): User {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        abort_if($user->customer_id === null, 403);

        return $user;
    }
}
