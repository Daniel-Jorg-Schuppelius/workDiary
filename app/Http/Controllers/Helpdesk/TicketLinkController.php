<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketLinkController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\{ServiceTicket, User};
use App\Services\ServiceTicket\TicketIncidentService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Ticketverknüpfungen (Feature 065, MVP-160): Dialog + Anlage über
 * {@see TicketIncidentService::link()} (Tenant-Grenze + Idempotenz im Service);
 * verknüpft Tickets untereinander (related/duplicate/parent).
 */
class TicketLinkController extends Controller {
    public function __construct(private readonly TicketIncidentService $incidents) {}

    public function create(ServiceTicket $ticket): View {
        Gate::authorize('update', $ticket);

        return view('service-tickets._link_dialog', [
            'ticket' => $ticket,
            'targets' => ServiceTicket::query()
                ->whereKeyNot($ticket->id)
                ->where('organization_id', $ticket->organization_id)
                ->orderByDesc('reported_at')
                ->limit(100)
                ->get(['id', 'ticket_no', 'title']),
        ]);
    }

    public function store(Request $request, ServiceTicket $ticket): RedirectResponse {
        Gate::authorize('update', $ticket);

        $data = $request->validate([
            'kind' => ['required', 'in:related,duplicate,parent'],
            'target' => ['required', 'string'],
        ]);

        // Sqid strikt mit Zielklasse dekodieren; fremde Org → 404 (Service hält Tenant-Grenze als zweite Linie).
        $targetId = Sqid::decode(ServiceTicket::class, $data['target']);
        $target = $targetId !== null
            ? ServiceTicket::query()
                ->whereKey($targetId)
                ->where('organization_id', $ticket->organization_id)
                ->first()
            : null;
        if ($target === null) {
            abort(404);
        }

        /** @var User $user */
        $user = Auth::user();

        try {
            $this->incidents->link($ticket, $target, $data['kind'], $user);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['target' => $e->getMessage()]);
        }

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Verknüpfung gespeichert.'));
    }
}
