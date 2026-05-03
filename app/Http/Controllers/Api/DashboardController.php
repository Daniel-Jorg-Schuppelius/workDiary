<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller {
    public function __invoke(Request $request, DashboardService $service): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();
        return response()->json(['data' => $service->summarize($user)]);
    }
}
