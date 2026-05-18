<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkScheduleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveWorkScheduleRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Flextime\WorkScheduleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkScheduleController extends Controller
{
    public function edit(User $user, WorkScheduleResolver $resolver): View
    {
        Gate::authorize('create', WorkSchedule::class); // Admin via before-Hook

        $schedule = $user->workSchedule() ?? new WorkSchedule(WorkScheduleResolver::defaults() + [
            'user_id' => $user->id,
            'valid_from' => now()->startOfMonth(),
        ]);

        return view('work-schedules._form_dialog', compact('user', 'schedule'));
    }

    public function update(User $user, SaveWorkScheduleRequest $request): RedirectResponse
    {
        Gate::authorize('create', WorkSchedule::class);

        $data = $request->validated();
        $existing = WorkSchedule::query()
            ->where('user_id', $user->id)
            ->where('valid_from', $data['valid_from'])
            ->first();

        if ($existing) {
            $existing->update($data);
        } else {
            $data['user_id'] = $user->id;
            $data['organization_id'] = $user->organization_id;
            WorkSchedule::create($data);
        }

        return redirect()->route('users.work-schedule.edit', $user)
            ->with('success', __('Arbeitszeit-Modell gespeichert.'));
    }

    public function self(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('work-schedules.show', [
            'user' => $user,
            'schedule' => $user->workSchedule(),
            'defaults' => WorkScheduleResolver::defaults(),
        ]);
    }
}
