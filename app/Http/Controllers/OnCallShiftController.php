<?php

namespace App\Http\Controllers;

use App\Models\OnCallShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnCallShiftController extends Controller {
    public function create(Request $request): View {
        $isDialog = $request->boolean('dialog');
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view($isDialog ? 'shifts._form_dialog' : 'shifts.form', [
            'shift' => null,
            'isEdit' => false,
            'isDialog' => $isDialog,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $canAssignOthers ? User::orderBy('name')->get(['id', 'name']) : collect([$auth->only(['id', 'name'])]),
            'prefillStartAt' => $this->parseDateTime($request->query('start_at') ?? $request->query('date')),
            'prefillEndAt' => $this->parseDateTime($request->query('end_at')),
            'prefillUserId' => (int) $request->query('user_id', 0),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $this->validateShift($request);
        /** @var User $auth */
        $auth = Auth::user();

        if (! $auth->canCreateEntriesForOthers() || empty($data['user_id'])) {
            $data['user_id'] = $auth->id;
        }

        OnCallShift::create($data);

        return $this->redirectAfter($request, __('Bereitschaft gespeichert.'));
    }

    public function edit(Request $request, OnCallShift $shift): View {
        $this->authorizeManage();
        $isDialog = $request->boolean('dialog');
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view($isDialog ? 'shifts._form_dialog' : 'shifts.form', [
            'shift' => $shift,
            'isEdit' => true,
            'isDialog' => $isDialog,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $canAssignOthers ? User::orderBy('name')->get(['id', 'name']) : collect([$auth->only(['id', 'name'])]),
            'prefillStartAt' => null,
            'prefillEndAt' => null,
            'prefillUserId' => $shift->user_id,
        ]);
    }

    public function update(Request $request, OnCallShift $shift): RedirectResponse {
        $this->authorizeManage();
        $data = $this->validateShift($request);
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->canCreateEntriesForOthers()) {
            $data['user_id'] = $shift->user_id;
        }
        $shift->update($data);

        return $this->redirectAfter($request, __('Bereitschaft aktualisiert.'));
    }

    public function destroy(Request $request, OnCallShift $shift): RedirectResponse {
        $this->authorizeManage();
        $shift->delete();

        return $this->redirectAfter($request, __('Bereitschaft gelöscht.'));
    }

    /** @return array<string, mixed> */
    private function validateShift(Request $request): array {
        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'note' => ['nullable', 'string', 'max:1000'],
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
        $back = $request->input('_back') ?: route('duties.index');

        return redirect($back)->with('success', $message);
    }
}
