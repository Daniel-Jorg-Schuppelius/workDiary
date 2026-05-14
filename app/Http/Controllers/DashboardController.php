<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $service): View
    {
        /** @var User $user */
        $user = $request->user();
        $data = $service->summarize($user);

        return view('dashboard.index', $data);
    }
}
