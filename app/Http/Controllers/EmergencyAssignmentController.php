<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAssignmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesShiftLike;
use App\Models\{EmergencyAssignment, OnCallShift, User};
use App\Support\Tz;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmergencyAssignmentController extends Controller {
    use ManagesShiftLike;

    public function create(Request $request): View {
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view('assignments._form_dialog', [
            'assignment' => null,
            'isEdit' => false,
            'isDialog' => true,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $this->assignableUsers(),
            'shiftOptions' => $this->shiftOptions(),
            'prefillStartAt' => $this->parseDateTime($request->query('start_at') ?? $request->query('date')),
            'prefillEndAt' => $this->parseDateTime($request->query('end_at')),
            'prefillUserId' => (int) $request->query('user_id', 0),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $this->validateAssignment($request);
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->canCreateEntriesForOthers() || empty($data['user_id'])) {
            $data['user_id'] = $auth->id;
        }
        EmergencyAssignment::create($data);

        return $this->redirectAfter($request, __('Notdienst gespeichert.'), route('duties.index') . '?tab=notdienst');
    }

    public function edit(Request $request, EmergencyAssignment $assignment): View {
        $this->authorizeManage();
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view('assignments._form_dialog', [
            'assignment' => $assignment,
            'isEdit' => true,
            'isDialog' => true,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $this->assignableUsers(),
            'shiftOptions' => $this->shiftOptions(),
            'prefillStartAt' => null,
            'prefillEndAt' => null,
            'prefillUserId' => $assignment->user_id,
        ]);
    }

    public function update(Request $request, EmergencyAssignment $assignment): RedirectResponse {
        $this->authorizeManage();
        $data = $this->validateAssignment($request);
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->canCreateEntriesForOthers()) {
            $data['user_id'] = $assignment->user_id;
        }
        $assignment->update($data);

        return $this->redirectAfter($request, __('Notdienst aktualisiert.'), route('duties.index') . '?tab=notdienst');
    }

    public function destroy(Request $request, EmergencyAssignment $assignment): RedirectResponse {
        $this->authorizeManage();
        $assignment->delete();

        return $this->redirectAfter($request, __('Notdienst gelöscht.'), route('duties.index') . '?tab=notdienst');
    }

    /** @return array<string, mixed> */
    private function validateAssignment(Request $request): array {
        $rawUserId = $request->input('user_id');
        $userId = \App\Support\Sqid::decodeOrNumeric(\App\Models\User::class, $rawUserId);

        $rawShiftId = $request->input('on_call_shift_id');
        $onCallShiftId = \App\Support\Sqid::decodeOrNumeric(\App\Models\OnCallShift::class, $rawShiftId);

        $request->merge([
            'user_id' => $userId,
            'on_call_shift_id' => $onCallShiftId,
            // datetime-local (Wanduhrzeit) in aktiver Anzeige-Zeitzone → UTC.
            'start_at' => Tz::toUtcString($request->input('start_at')),
            'end_at' => Tz::toUtcString($request->input('end_at')),
        ]);

        return $request->validate([
            'user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'on_call_shift_id' => ['nullable', 'integer', 'exists:on_call_shifts,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** @return Collection<int, OnCallShift> */
    private function shiftOptions(): Collection {
        return OnCallShift::query()->with('user:id,name')->orderByDesc('start_at')->limit(50)->get();
    }
}
