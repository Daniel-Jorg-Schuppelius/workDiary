<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShiftController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesShiftLike;
use App\Models\{OnCallShift, User};
use App\Support\Tz;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnCallShiftController extends Controller {
    use ManagesShiftLike;

    public function create(Request $request): View {
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view('shifts._form_dialog', [
            'shift' => null,
            'isEdit' => false,
            'isDialog' => true,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $this->assignableUsers(),
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

        return $this->redirectAfter($request, __('Bereitschaft gespeichert.'), route('duties.index'));
    }

    public function edit(Request $request, OnCallShift $shift): View {
        $this->authorizeManage();
        /** @var User $auth */
        $auth = Auth::user();
        $canAssignOthers = $auth->canCreateEntriesForOthers();

        return view('shifts._form_dialog', [
            'shift' => $shift,
            'isEdit' => true,
            'isDialog' => true,
            'canAssignOthers' => $canAssignOthers,
            'assignableUsers' => $this->assignableUsers(),
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

        return $this->redirectAfter($request, __('Bereitschaft aktualisiert.'), route('duties.index'));
    }

    public function destroy(Request $request, OnCallShift $shift): RedirectResponse {
        $this->authorizeManage();
        $shift->delete();

        return $this->redirectAfter($request, __('Bereitschaft gelöscht.'), route('duties.index'));
    }

    /** @return array<string, mixed> */
    private function validateShift(Request $request): array {
        // datetime-local (Wanduhrzeit) in aktiver Anzeige-Zeitzone → UTC.
        $request->merge([
            'start_at' => Tz::toUtcString($request->input('start_at')),
            'end_at' => Tz::toUtcString($request->input('end_at')),
        ]);

        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
