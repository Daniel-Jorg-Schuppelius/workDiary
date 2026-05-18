<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdminTimeEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveAdminTimeEntryRequest;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Handles non-project (administrative) time entries that are not tied to a
 * specific project — e.g. team meetings, internal work, travel time without
 * a billable target, training, etc.
 */
class AdminTimeEntryController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('create', TimeEntry::class);

        $date = $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString();
        $categories = ActivityCategory::query()->active()->orderBy('sort_order')->orderBy('label')->get();
        $openAttendance = Attendance::query()
            ->where('user_id', Auth::id())
            ->whereDate('date', $date)
            ->orderByDesc('started_at')
            ->first();

        return view('time-entries._admin_form_dialog', [
            'entry' => null,
            'categories' => $categories,
            'date' => $date,
            'openAttendance' => $openAttendance,
        ]);
    }

    public function store(SaveAdminTimeEntryRequest $request): RedirectResponse
    {
        Gate::authorize('create', TimeEntry::class);

        /** @var User $user */
        $user = Auth::user();

        $data = $request->validated();
        $data['user_id'] = $user->id;
        $data['kind'] = TimeEntry::KIND_WORK;

        if (! empty($data['activity_category_id'])) {
            $cat = ActivityCategory::find($data['activity_category_id']);
            if ($cat) {
                $data['organization_id'] = $cat->organization_id ?? $user->organization_id;
            }
        }
        $data['organization_id'] ??= $user->organization_id;

        TimeEntry::create($data);

        return redirect()->route('today.show', ['date' => $data['date']])
            ->with('success', __('Verwaltungszeit erfasst.'));
    }

    public function edit(TimeEntry $timeEntry): View
    {
        Gate::authorize('update', $timeEntry);

        return view('time-entries._admin_form_dialog', [
            'entry' => $timeEntry,
            'categories' => ActivityCategory::query()->active()->orderBy('sort_order')->orderBy('label')->get(),
            'date' => $timeEntry->date?->toDateString(),
            'openAttendance' => null,
        ]);
    }

    public function update(SaveAdminTimeEntryRequest $request, TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('update', $timeEntry);

        $timeEntry->update($request->validated());

        return redirect()->route('today.show', ['date' => $timeEntry->date?->toDateString()])
            ->with('success', __('Verwaltungszeit aktualisiert.'));
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('delete', $timeEntry);
        $date = $timeEntry->date?->toDateString();
        $timeEntry->delete();

        return redirect()->route('today.show', ['date' => $date])
            ->with('success', __('Eintrag gelöscht.'));
    }
}
