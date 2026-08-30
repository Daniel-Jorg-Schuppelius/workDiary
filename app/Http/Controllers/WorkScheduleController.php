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

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SaveWorkScheduleRequest;
use App\Models\{User, WorkSchedule};
use App\Services\Flextime\WorkScheduleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class WorkScheduleController extends Controller {
    use ResolvesCurrentOrganization;

    public function edit(User $user, WorkScheduleResolver $resolver): View {
        Gate::authorize('create', WorkSchedule::class); // Admin via before-Hook
        $this->ensureSameOrg($user);

        $schedule = $user->workSchedule() ?? new WorkSchedule(WorkScheduleResolver::defaultsFor($user) + [
            'user_id' => $user->id,
            'valid_from' => now()->startOfMonth(),
        ]);

        return view('work-schedules._form_dialog', compact('user', 'schedule'));
    }

    public function update(User $user, SaveWorkScheduleRequest $request): RedirectResponse {
        Gate::authorize('create', WorkSchedule::class);
        $this->ensureSameOrg($user);

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

    public function self(): View {
        /** @var User $user */
        $user = Auth::user();
        $defaults = WorkScheduleResolver::defaultsFor($user);
        $schedule = $user->workSchedule() ?? new WorkSchedule($defaults + [
            'user_id' => $user->id,
            'valid_from' => now()->startOfMonth(),
        ]);

        return view('work-schedules._self_dialog', [
            'user' => $user,
            'schedule' => $schedule,
            'defaults' => $defaults,
        ]);
    }

    /**
     * Gehört der Mitarbeiter zur eigenen Organisation?
     *
     * `User` trägt bewusst keinen OrganizationScope, und die Route bindet ihn
     * über die Sqid — die ist Verschleierung, kein Zugriffsschutz. Ohne diese
     * Prüfung konnte jeder mit `work-schedule.manage` das Arbeitszeit-Modell
     * eines fremden Mitarbeiters lesen und **setzen**: `update()` legte den
     * Datensatz mit dessen `organization_id` an, also direkt in der fremden
     * Organisation, wo er Gleitzeit-, Überstunden- und Lohnrechnung steuert
     * (Sicherheitsscan 2026-08-23, S-06). Muster wie
     * {@see FlexEligibilityController::ensureSameOrg()}.
     */
    private function ensureSameOrg(User $member): void {
        abort_unless((int) $member->organization_id === (int) $this->currentOrganization()->id, 403);
    }

}
