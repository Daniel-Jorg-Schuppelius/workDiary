<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesShiftLike;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'on_call_shift_id' => ['nullable', 'integer', 'exists:on_call_shifts,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, OnCallShift> */
    private function shiftOptions(): \Illuminate\Support\Collection {
        return OnCallShift::query()->with('user:id,name')->orderByDesc('start_at')->limit(50)->get();
    }
}
