<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketWatcherController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Models\{ServiceTicket, ServiceTicketWatcher, User};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Ticket-Beobachter (Feature 065, MVP-160): zusätzliche Empfänger neben dem
 * Bearbeiter. Hinzufügen ist idempotent (Unique ticket+user, firstOrCreate);
 * der Ziel-User wird org-gescopt aufgelöst — fremde Organisationen enden 404.
 */
class TicketWatcherController extends Controller {
    public function store(Request $request, ServiceTicket $ticket): RedirectResponse {
        Gate::authorize('update', $ticket);

        $data = $request->validate([
            'user' => ['required', 'string'],
        ]);

        $userId = Sqid::decode(User::class, $data['user']);
        $watcher = $userId !== null
            ? User::query()
                ->whereKey($userId)
                ->where('organization_id', $ticket->organization_id)
                ->first()
            : null;
        if ($watcher === null) {
            abort(404);
        }

        ServiceTicketWatcher::query()->firstOrCreate([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'user_id' => $watcher->id,
        ]);

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Beobachter hinzugefügt.'));
    }

    public function destroy(ServiceTicket $ticket, User $user): RedirectResponse {
        Gate::authorize('update', $ticket);

        if ((int) $user->organization_id !== (int) $ticket->organization_id) {
            abort(404);
        }

        $ticket->watchers()->where('user_id', $user->id)->delete();

        return redirect()
            ->route('service-tickets.show', $ticket)
            ->with('success', __('Beobachter entfernt.'));
    }
}
