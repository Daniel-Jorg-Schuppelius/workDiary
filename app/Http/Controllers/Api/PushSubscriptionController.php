<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PushSubscriptionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{PushSubscription, User};
use App\Rules\SafePushEndpoint;
use Illuminate\Http\{JsonResponse, Request};
use OpenApi\Attributes as OA;

class PushSubscriptionController extends Controller {
    #[OA\Get(
        path: '/push/vapid',
        summary: 'VAPID Public Key',
        tags: ['Push'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function vapid(): JsonResponse {
        return response()->json(['data' => ['publicKey' => config('webpush.public_key')]]);
    }

    #[OA\Post(
        path: '/push/subscribe',
        summary: 'Push-Subscription registrieren',
        tags: ['Push'],
        security: [['bearerAuth' => ['push:write']]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['endpoint', 'keys'], properties: [
            new OA\Property(property: 'endpoint', type: 'string', maxLength: 500),
            new OA\Property(property: 'keys', type: 'object', required: ['p256dh', 'auth'], properties: [new OA\Property(property: 'p256dh', type: 'string'), new OA\Property(property: 'auth', type: 'string')]),
            new OA\Property(property: 'contentEncoding', type: 'string', maxLength: 32, nullable: true, example: 'aesgcm'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            // Der Server ruft diese Adresse später selbst auf — ohne Prüfung
            // wäre das eine blinde SSRF (Sicherheitsscan 2026-08-23, S-48).
            'endpoint' => ['required', 'string', 'max:500', new SafePushEndpoint()],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return response()->json(['data' => ['id' => $sub->id, 'status' => 'subscribed']], 201);
    }

    #[OA\Delete(
        path: '/push/unsubscribe',
        summary: 'Push-Subscription entfernen',
        tags: ['Push'],
        security: [['bearerAuth' => ['push:write']]],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'endpoint', type: 'string', maxLength: 500),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function destroy(Request $request): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('endpoint', $endpoint)
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()->json(['data' => ['status' => 'unsubscribed']]);
    }
}
