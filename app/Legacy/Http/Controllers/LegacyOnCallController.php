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
use App\Legacy\Support\LegacyView;
use App\Models\Diary\OnCallShift;
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

        return view(LegacyView::pick($request, 'legacy.oncall._form_dialog', 'legacy.classic.diary.duty-form'), [
            ...$this->legacyDutyFormData(null, false),
            'kind' => 'oncall',
            'isDialog' => true,
        ]);
    }

    public function store(SaveLegacyDutyRequest $request): RedirectResponse {
        $this->ensureAdmin();

        LegacyOnCall::query()->create($request->validated());

        return redirect()->route('legacy.diary.index', ['tab' => 'bereitschaft'])->with('success', 'Bereitschaft angelegt.');
    }

    public function edit(LegacyOnCall $oncall): View|RedirectResponse {
        $request = request();
        $this->ensureAdmin();

        if ($redirect = $this->redirectToWeekIfMigrated(OnCallShift::class, (int) $oncall->id)) {
            return $redirect;
        }

        // Löschen fragt im Altportal auf einer eigenen Seite nach.
        if (LegacyView::isClassic($request) && $request->boolean('delete')) {
            return view('legacy.classic.diary.duty-delete', ['item' => $oncall->load('mitarbeiter:id,uname'), 'kind' => 'oncall']);
        }

        return view(LegacyView::pick($request, 'legacy.oncall._form_dialog', 'legacy.classic.diary.duty-form'), [
            ...$this->legacyDutyFormData($oncall, true),
            'kind' => 'oncall',
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
