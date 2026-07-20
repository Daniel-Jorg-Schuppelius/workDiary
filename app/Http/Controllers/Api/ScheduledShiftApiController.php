<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{ScheduledShift, User};
use Illuminate\Http\{JsonResponse, Request};
use OpenApi\Attributes as OA;

/**
 * REST-API Schichtplan (Feature 008 MVP „Kernobjekte"; Vollaudit 2026-07,
 * M3): read-first — ohne dutyPlan.viewAny nur die EIGENEN Schichten.
 */
class ScheduledShiftApiController extends Controller {
    #[OA\Get(
        path: '/scheduled-shifts',
        summary: 'Geplante Schichten auflisten',
        tags: ['Shifts'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $query = ScheduledShift::query()
            ->when(! ($user->isAdmin() || $user->can(Permission::DutyPlanViewAny->value)), fn($q) => $q->where('user_id', $user->id))
            ->when($request->filled('from'), fn($q) => $q->whereDate('date', '>=', (string) $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('date', '<=', (string) $request->query('to')))
            ->with(['user:id,name', 'shiftType:id,name'])
            ->orderBy('date')
            ->orderBy('start_time');

        $page = $query->paginate(min(200, (int) $request->input('per_page', 50)));

        return response()->json([
            'data' => collect($page->items())->map(fn(ScheduledShift $shift): array => [
                'id' => $shift->id,
                'date' => $shift->date->toDateString(),
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'user' => ['id' => $shift->user_id, 'name' => $shift->user->name ?? null],
                'shift_type' => $shift->shiftType->name ?? null,
                'status' => $shift->status->value,
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
