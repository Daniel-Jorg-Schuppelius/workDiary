<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisRoomController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Http\Controllers\Controller;
use App\Models\Crisis\{CrisisCase, CrisisMapPoint};
use App\Services\Crisis\CrisisRoomService;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Krisenraum (MVP-963): Herzschlag der Anwesenheit und eigene Lagepunkte. */
class CrisisRoomController extends Controller {
    public function heartbeat(CrisisCase $case, CrisisRoomService $room): JsonResponse {
        Gate::authorize('view', $case);

        return response()->json(['present' => $room->heartbeat($case, $this->authUser())]);
    }

    public function storePoint(Request $request, CrisisCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(CrisisMapPoint::KINDS)],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $point = CrisisMapPoint::query()->create($data + ['organization_id' => $case->organization_id, 'crisis_case_id' => $case->id, 'created_by' => $this->authUser()->id]);
        $case->audit('crisis.mapPointAdded', ['point_id' => $point->id, 'kind' => $point->kind]);

        return back()->with('status', __('crisis.room.flash.point_saved'));
    }

    public function destroyPoint(CrisisMapPoint $point): RedirectResponse {
        $case = CrisisCase::query()->findOrFail($point->crisis_case_id);
        Gate::authorize('update', $case);
        $point->delete();
        $case->audit('crisis.mapPointRemoved', ['point_id' => $point->id]);

        return back()->with('status', __('crisis.room.flash.point_deleted'));
    }
}
