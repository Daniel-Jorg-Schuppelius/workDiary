<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmergencyAssignmentController extends Controller {
    public function create(Request $request): View {
        $isDialog = $request->boolean('dialog');
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view($isDialog ? 'assignments._form_dialog' : 'assignments.form', [
            'assignment' => null,
            'isEdit' => false,
            'isDialog' => $isDialog,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $canAssignOthers ? User::orderBy('name')->get(['id', 'name']) : collect([$auth->only(['id', 'name'])]),
            'shiftOptions' => OnCallShift::query()->with('user:id,name')->orderByDesc('start_at')->limit(50)->get(),
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

        return $this->redirectAfter($request, __('Notdienst gespeichert.'));
    }

    public function edit(Request $request, EmergencyAssignment $assignment): View {
        $this->authorizeManage();
        $isDialog = $request->boolean('dialog');
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view($isDialog ? 'assignments._form_dialog' : 'assignments.form', [
            'assignment' => $assignment,
            'isEdit' => true,
            'isDialog' => $isDialog,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $canAssignOthers ? User::orderBy('name')->get(['id', 'name']) : collect([$auth->only(['id', 'name'])]),
            'shiftOptions' => OnCallShift::query()->with('user:id,name')->orderByDesc('start_at')->limit(50)->get(),
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

        return $this->redirectAfter($request, __('Notdienst aktualisiert.'));
    }

    public function destroy(Request $request, EmergencyAssignment $assignment): RedirectResponse {
        $this->authorizeManage();
        $assignment->delete();

        return $this->redirectAfter($request, __('Notdienst gelöscht.'));
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

    private function authorizeManage(): void {
        /** @var User $auth */
        $auth = Auth::user();
        abort_unless($auth->canCreateEntriesForOthers(), 403);
    }

    private function parseDateTime(?string $value): ?string {
        if (! $value) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->format('Y-m-d\TH:i');
        } catch (\Exception) {
            return null;
        }
    }

    private function redirectAfter(Request $request, string $message): RedirectResponse {
        $back = $request->input('_back') ?: route('duties.index') . '?tab=notdienst';

        return redirect($back)->with('success', $message);
    }
}
