<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller {
    public function vapid(): JsonResponse {
        return response()->json([
            'publicKey' => config('webpush.public_key'),
        ]);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        /** @var \App\Models\User $user */
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

        return response()->json(['id' => $sub->id, 'status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('endpoint', $endpoint)
                ->where('user_id', $user->id)
                ->delete();
        }

        return response()->json(['status' => 'unsubscribed']);
    }
}
