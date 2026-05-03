<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DutyController extends Controller {
    public function index(Request $request): View {
        $tab = $request->query('tab') === 'notdienst' ? 'notdienst' : 'bereitschaft';

        $shifts = OnCallShift::query()
            ->with('user:id,name')
            ->orderByDesc('start_at')
            ->paginate(15, ['*'], 'shifts_page')
            ->withQueryString();

        $assignments = EmergencyAssignment::query()
            ->with(['user:id,name', 'shift:id,start_at,end_at,user_id'])
            ->orderByDesc('start_at')
            ->paginate(15, ['*'], 'assignments_page')
            ->withQueryString();

        return view('duties.index', compact('tab', 'shifts', 'assignments'));
    }
}
