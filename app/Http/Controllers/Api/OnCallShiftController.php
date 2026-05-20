<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShiftController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OnCallShiftResource;
use App\Models\OnCallShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OnCallShiftController extends Controller {
    public function index(Request $request): AnonymousResourceCollection {
        $q = OnCallShift::query()->with('user:id,name');
        if ($request->filled('from')) {
            $q->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('start_at', '<=', $request->to);
        }
        if (! $request->boolean('archived')) {
            $q->where('is_archived', false);
        }

        return OnCallShiftResource::collection($q->orderBy('start_at')->paginate(min(100, (int) $request->input('per_page', 20))));
    }

    public function show(OnCallShift $shift): OnCallShiftResource {
        return new OnCallShiftResource($shift->load('user:id,name'));
    }
}
