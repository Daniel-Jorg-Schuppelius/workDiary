<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\UpdateScheduledShiftRequest;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScheduledShiftController extends Controller
{
    public function show(ScheduledShift $shift): View
    {
        Gate::authorize('view', $shift);

        $shift->load(['user', 'shiftType', 'dutyPlan']);
        $users = User::query()->orderBy('name')->get(['id', 'name']);
        $types = ShiftType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']);

        return view('scheduled-shifts._form_dialog', compact('shift', 'users', 'types'));
    }

    public function edit(ScheduledShift $shift): View
    {
        Gate::authorize('update', $shift);

        $users = User::query()->orderBy('name')->get(['id', 'name']);
        $types = ShiftType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']);

        return view('scheduled-shifts._form_dialog', compact('shift', 'users', 'types'));
    }

    public function update(UpdateScheduledShiftRequest $request, ScheduledShift $shift): RedirectResponse
    {
        $shift->update($request->validated());

        return redirect()->route('schedule.index')
            ->with('success', __('Schicht aktualisiert.'));
    }

    public function destroy(ScheduledShift $shift): RedirectResponse
    {
        Gate::authorize('delete', $shift);

        $shift->delete();

        return redirect()->route('schedule.index')
            ->with('success', __('Schicht gelöscht.'));
    }
}
