<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetTimelineController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Asset\AssetTimelineService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Gate;

class AssetTimelineController extends Controller {
    public function __invoke(Request $request, Asset $asset, AssetTimelineService $timeline): JsonResponse {
        Gate::authorize('view', $asset);

        $limit = max(1, min(300, (int) $request->integer('limit', 120)));

        return response()->json([
            'data' => $timeline->build($asset, $limit),
        ]);
    }
}
