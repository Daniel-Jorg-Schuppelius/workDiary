<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller {
    public function vapid(): JsonResponse {
        return response()->json(['data' => ['publicKey' => config('webpush.public_key')]]);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);
        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );
        return response()->json(['data' => ['id' => $sub->id, 'status' => 'subscribed']], 201);
    }

    public function destroy(Request $request): JsonResponse {
        $endpoint = (string) $request->input('endpoint');
        if ($endpoint !== '') {
            PushSubscription::where('endpoint', $endpoint)
                ->where('user_id', $request->user()->id)
                ->delete();
        }
        return response()->json(['data' => ['status' => 'unsubscribed']]);
    }
}
