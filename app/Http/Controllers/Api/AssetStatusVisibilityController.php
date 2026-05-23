<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatusVisibilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Asset\AssetStatusVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AssetStatusVisibilityController extends Controller {
    public function __invoke(Asset $asset, AssetStatusVisibilityService $visibility): JsonResponse {
        Gate::authorize('view', $asset);

        return response()->json([
            'data' => $visibility->summarize($asset),
        ]);
    }
}
