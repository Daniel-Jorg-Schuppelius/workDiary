<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AvailabilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\{SaveAvailabilityWindowRequest, SaveDesiredShiftRequest};
use App\Models\{AvailabilityWindow, DesiredShift, ShiftType, User};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Self-Service für Verfügbarkeiten & Wunschdienste (Feature 007).
 * Jeder Mitarbeiter pflegt ausschließlich seine eigenen Einträge; der Planer
 * sieht sie beim Planen über den StaffingSuggester / die Planungsansicht.
 */
class AvailabilityController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', AvailabilityWindow::class);
        /** @var User $auth */
        $auth = Auth::user();

        $windows = AvailabilityWindow::query()
            ->forUser($auth->id)
            ->orderByRaw('specific_date is null')
            ->orderBy('weekday')
            ->orderBy('specific_date')
            ->get();

        $desired = DesiredShift::query()
            ->forUser($auth->id)
            ->with('shiftType')
            ->orderBy('date')
            ->get();

        return view('availability.index', [
            'windows' => $windows,
            'desired' => $desired,
            'shiftTypes' => ShiftType::active()->orderBy('name')->get(),
        ]);
    }

    public function storeWindow(SaveAvailabilityWindowRequest $request): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validated();
        $data['user_id'] = $auth->id;

        AvailabilityWindow::create($data);

        return redirect()->route('schedule.availability.index')
            ->with('success', __('schedule.availability.window_saved'));
    }

    public function updateWindow(SaveAvailabilityWindowRequest $request, AvailabilityWindow $window): RedirectResponse {
        Gate::authorize('update', $window);
        $window->update($request->validated());

        return redirect()->route('schedule.availability.index')
            ->with('success', __('schedule.availability.window_saved'));
    }

    public function destroyWindow(AvailabilityWindow $window): RedirectResponse {
        Gate::authorize('delete', $window);
        $window->delete();

        return redirect()->route('schedule.availability.index')
            ->with('success', __('schedule.availability.window_deleted'));
    }

    public function storeDesired(SaveDesiredShiftRequest $request): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validated();
        $data['user_id'] = $auth->id;

        DesiredShift::create($data);

        return redirect()->route('schedule.availability.index')
            ->with('success', __('schedule.availability.desired_saved'));
    }

    public function updateDesired(SaveDesiredShiftRequest $request, DesiredShift $desired): RedirectResponse {
        Gate::authorize('update', $desired);
        $desired->update($request->validated());

        return redirect()->route('schedule.availability.index')
            ->with('success', __('schedule.availability.desired_saved'));
    }

    public function destroyDesired(DesiredShift $desired): RedirectResponse {
        Gate::authorize('delete', $desired);
        $desired->delete();

        return redirect()->route('schedule.availability.index')
            ->with('success', __('schedule.availability.desired_deleted'));
    }
}
