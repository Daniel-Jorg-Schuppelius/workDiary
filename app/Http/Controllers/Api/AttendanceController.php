<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\Attendance\AttendanceClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceClockService $clock) {}

    public function current(): JsonResponse
    {
        $user = Auth::user();
        $a = $user ? $this->clock->current($user) : null;

        return response()->json([
            'open' => $a !== null,
            'attendance' => $a ? $this->serialize($a) : null,
        ]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        Gate::authorize('create', Attendance::class);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'device' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        try {
            $a = $this->clock->clockIn($user, $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($this->serialize($a), 201);
    }

    public function clockOut(Request $request): JsonResponse
    {
        Gate::authorize('create', Attendance::class);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'device' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $a = $this->clock->clockOut($user, $data);
        if (! $a) {
            return response()->json(['message' => 'No open attendance.'], 404);
        }

        return response()->json($this->serialize($a));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'started_at' => $a->started_at?->toIso8601String(),
            'ended_at' => $a->ended_at?->toIso8601String(),
            'date' => $a->date?->toDateString(),
            'duration_minutes' => $a->duration_minutes,
            'break_minutes_manual' => $a->break_minutes_manual,
            'break_minutes_auto' => $a->break_minutes_auto,
            'status' => $a->status,
            'source' => $a->source,
            'note' => $a->note,
        ];
    }
}
