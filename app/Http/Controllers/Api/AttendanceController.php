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
use App\Models\{Attendance, User};
use App\Services\Attendance\AttendanceClockService;
use App\Support\Setting;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;
use RuntimeException;

class AttendanceController extends Controller {
    public function __construct(protected AttendanceClockService $clock) {}

    #[OA\Get(
        path: '/attendance/current',
        summary: 'Aktuelle Anwesenheit',
        tags: ['Attendance'],
        security: [['bearerAuth' => ['attendance:read']]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function current(): JsonResponse {
        $user = Auth::user();
        $a = $user ? $this->clock->current($user) : null;

        return response()->json([
            'open' => $a !== null,
            'attendance' => $a ? $this->serialize($a) : null,
        ]);
    }

    #[OA\Post(
        path: '/attendance/clock-in',
        summary: 'Einstempeln',
        tags: ['Attendance'],
        security: [['bearerAuth' => ['attendance:write']]],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'lat', type: 'number', format: 'float', nullable: true),
            new OA\Property(property: 'lng', type: 'number', format: 'float', nullable: true),
            new OA\Property(property: 'device', type: 'string', maxLength: 64, nullable: true),
            new OA\Property(property: 'note', type: 'string', maxLength: 1000, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 409, description: 'Bereits eingestempelt'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function clockIn(Request $request): JsonResponse {
        Gate::authorize('create', Attendance::class);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'device' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.device_max', 64)],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $a = $this->clock->clockIn($user, $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($this->serialize($a), 201);
    }

    #[OA\Post(
        path: '/attendance/clock-out',
        summary: 'Ausstempeln',
        tags: ['Attendance'],
        security: [['bearerAuth' => ['attendance:write']]],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'lat', type: 'number', format: 'float', nullable: true),
            new OA\Property(property: 'lng', type: 'number', format: 'float', nullable: true),
            new OA\Property(property: 'device', type: 'string', maxLength: 64, nullable: true),
            new OA\Property(property: 'note', type: 'string', maxLength: 1000, nullable: true),
            new OA\Property(property: 'break_minutes', type: 'integer', minimum: 0, maximum: 600, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Keine offene Anwesenheit'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function clockOut(Request $request): JsonResponse {
        Gate::authorize('create', Attendance::class);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'device' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.device_max', 64)],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:' . (int) Setting::get('validation.attendance.break_minutes_max', 600)],
        ]);

        /** @var User $user */
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
    private function serialize(Attendance $a): array {
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
