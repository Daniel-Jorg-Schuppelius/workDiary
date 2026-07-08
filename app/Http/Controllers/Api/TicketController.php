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

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Http\{JsonResponse, Request};

/**
 * API-Ticketeingang (Feature 065, MVP-152): minimaler org-gebundener
 * Endpunkt (Sanctum, ability tickets:write) — Kanal 'api', Queue folgt
 * dem Payload oder der Default-Queue.
 */
class TicketController extends Controller {
    public function store(Request $request, ServiceTicketService $tickets): JsonResponse {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'kind' => ['nullable', 'in:incident,service_request,question'],
            'priority' => ['nullable', 'string', 'max:20'],
            'customer_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'queue_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('service_queues')],
        ]);

        /** @var User $user */
        $user = $request->user();

        $ticket = $tickets->create($user->organization()->firstOrFail(), $user, [
            ...$data,
            'source' => 'api',
        ]);

        return response()->json([
            'id' => $ticket->sqid,
            'ticket_no' => $ticket->ticket_no,
            'status' => $ticket->status->value,
            'queue_id' => $ticket->queue_id,
        ], 201);
    }
}
