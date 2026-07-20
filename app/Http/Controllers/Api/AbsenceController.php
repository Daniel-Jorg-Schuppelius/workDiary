<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbsenceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{SickLeave, User, Vacation};
use Illuminate\Http\{JsonResponse, Request};
use OpenApi\Attributes as OA;

/**
 * REST-API Abwesenheiten (Feature 008 MVP „Kernobjekte"; Vollaudit 2026-07,
 * M3): read-first — Urlaub und Krankmeldungen, org-gescopt; ohne
 * vacation.viewAny/sickLeave.viewAny nur die EIGENEN Datensätze (Sichtbarkeit
 * wie die Web-Indizes). Krankmeldungen liefern bewusst KEINE Diagnose-/
 * Arztfelder — nur Zeitraum und Art.
 */
class AbsenceController extends Controller {
    #[OA\Get(
        path: '/absences',
        summary: 'Abwesenheiten auflisten (Urlaub + Krankmeldungen)',
        tags: ['Absences'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['vacation', 'sick'])),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $type = (string) $request->query('type', '');

        $out = [];

        if ($type === '' || $type === 'vacation') {
            $vacations = Vacation::query()
                ->when(! ($user->isAdmin() || $user->can(Permission::VacationViewAny->value)), fn($q) => $q->where('user_id', $user->id))
                ->when($request->filled('from'), fn($q) => $q->whereDate('end_date', '>=', (string) $request->query('from')))
                ->when($request->filled('to'), fn($q) => $q->whereDate('start_date', '<=', (string) $request->query('to')))
                ->with('user:id,name')
                ->orderByDesc('start_date')
                ->limit(500)
                ->get();
            foreach ($vacations as $vacation) {
                $out[] = [
                    'kind' => 'vacation',
                    'id' => $vacation->id,
                    'user' => ['id' => $vacation->user_id, 'name' => $vacation->user->name ?? null],
                    'start_date' => $vacation->start_date->toDateString(),
                    'end_date' => $vacation->end_date->toDateString(),
                    'type' => $vacation->type->value,
                    'status' => $vacation->status->value,
                ];
            }
        }

        if ($type === '' || $type === 'sick') {
            $sickLeaves = SickLeave::query()
                ->when(! ($user->isAdmin() || $user->can(Permission::SickLeaveViewAny->value)), fn($q) => $q->where('user_id', $user->id))
                ->when($request->filled('from'), fn($q) => $q->whereDate('end_date', '>=', (string) $request->query('from')))
                ->when($request->filled('to'), fn($q) => $q->whereDate('start_date', '<=', (string) $request->query('to')))
                ->with('user:id,name')
                ->orderByDesc('start_date')
                ->limit(500)
                ->get();
            foreach ($sickLeaves as $sickLeave) {
                $out[] = [
                    'kind' => 'sick',
                    'id' => $sickLeave->id,
                    'user' => ['id' => $sickLeave->user_id, 'name' => $sickLeave->user->name ?? null],
                    'start_date' => $sickLeave->start_date->toDateString(),
                    'end_date' => $sickLeave->end_date->toDateString(),
                    'type' => $sickLeave->kind->value,
                    'status' => null,
                ];
            }
        }

        return response()->json(['data' => $out]);
    }
}
