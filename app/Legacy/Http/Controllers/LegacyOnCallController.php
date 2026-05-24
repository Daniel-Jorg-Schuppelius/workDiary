<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyOnCallController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\{ManagesLegacyDutyCrud, RequiresLegacyAdmin};
use App\Legacy\Http\Requests\SaveLegacyDutyRequest;
use App\Legacy\Models\LegacyOnCall;
use App\Models\OnCallShift;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class LegacyOnCallController extends Controller {
    use ManagesLegacyDutyCrud;
    use RequiresLegacyAdmin;

    public function index(Request $request): RedirectResponse {
        return redirect()->route('legacy.diary.index', ['tab' => 'bereitschaft']);
    }

    public function create(Request $request): View {
        $this->ensureAdmin();

        return view('legacy.oncall._form_dialog', [
            ...$this->legacyDutyFormData(null, false),
            'isDialog' => true,
        ]);
    }

    public function store(SaveLegacyDutyRequest $request): RedirectResponse {
        $this->ensureAdmin();

        LegacyOnCall::query()->create($request->validated());

        return redirect()->route('legacy.diary.index', ['tab' => 'bereitschaft'])->with('success', 'Bereitschaft angelegt.');
    }

    public function edit(LegacyOnCall $oncall): View|RedirectResponse {
        $this->ensureAdmin();

        if ($redirect = $this->redirectToWeekIfMigrated(OnCallShift::class, (int) $oncall->id)) {
            return $redirect;
        }

        return view('legacy.oncall._form_dialog', [
            ...$this->legacyDutyFormData($oncall, true),
            'isDialog' => true,
        ]);
    }

    public function update(SaveLegacyDutyRequest $request, LegacyOnCall $oncall): RedirectResponse {
        $this->ensureAdmin();

        $oncall->update($request->validated());

        return redirect()->route('legacy.diary.index', ['tab' => 'bereitschaft'])->with('success', 'Bereitschaft aktualisiert.');
    }

    public function destroy(LegacyOnCall $oncall): RedirectResponse {
        $this->ensureAdmin();

        $oncall->delete();

        return redirect()->route('legacy.diary.index', ['tab' => 'bereitschaft'])->with('success', 'Bereitschaft geloescht.');
    }
}
