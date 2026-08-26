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
use OpenApi\Attributes as OA;

/**
 * API-Ticketeingang (Feature 065, MVP-152): minimaler org-gebundener
 * Endpunkt (Sanctum, ability tickets:write) — Kanal 'api', Queue folgt
 * dem Payload oder der Default-Queue.
 */
class TicketController extends Controller {
    #[OA\Post(
        path: '/tickets',
        summary: 'Ticket anlegen',
        tags: ['Tickets'],
        security: [['bearerAuth' => ['tickets:write']]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title'], properties: [
            new OA\Property(property: 'title', type: 'string', minLength: 2, maxLength: 255),
            new OA\Property(property: 'description', type: 'string', maxLength: 10000, nullable: true),
            new OA\Property(property: 'kind', type: 'string', enum: ['incident', 'service_request', 'question'], nullable: true),
            new OA\Property(property: 'priority', type: 'string', maxLength: 20, nullable: true),
            new OA\Property(property: 'customer_id', type: 'integer', nullable: true),
            new OA\Property(property: 'queue_id', type: 'integer', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
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
