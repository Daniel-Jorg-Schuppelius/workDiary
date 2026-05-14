<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FlexController extends Controller
{
    public function __construct(protected FlexCalculator $calc) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $year = (int) $request->input('year', CarbonImmutable::now()->year);
        $month = (int) $request->input('month', CarbonImmutable::now()->month);

        return view('flex.index', [
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'summary' => $this->calc->monthlyBalance($user, $year, $month),
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function admin(Request $request): View
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $userId = (int) ($request->input('user') ?? Auth::id());
        $user = User::findOrFail($userId);
        $year = (int) $request->input('year', CarbonImmutable::now()->year);
        $month = (int) $request->input('month', CarbonImmutable::now()->month);

        return view('flex.admin', [
            'users' => User::query()->orderBy('name')->get(),
            'user' => $user,
            'year' => $year,
            'month' => $month,
            'summary' => $this->calc->monthlyBalance($user, $year, $month),
        ]);
    }
}
