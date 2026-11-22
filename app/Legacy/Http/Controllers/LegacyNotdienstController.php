<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyNotdienstController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Legacy\Http\Concerns\{ManagesLegacyDutyCrud, RequiresLegacyAdmin};
use App\Legacy\Http\Requests\SaveLegacyDutyRequest;
use App\Legacy\Models\LegacyNotdienst;
use App\Legacy\Support\LegacyView;
use App\Models\Diary\EmergencyAssignment;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

class LegacyNotdienstController extends Controller {
    use ManagesLegacyDutyCrud;
    use RequiresLegacyAdmin;

    public function index(Request $request): RedirectResponse {
        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst']);
    }

    public function create(Request $request): View {
        $this->ensureAdmin();

        return view(LegacyView::pick($request, 'legacy.notdienst._form_dialog', 'legacy.classic.diary.duty-form'), [
            ...$this->legacyDutyFormData(null, false),
            'kind' => 'notdienst',
            'isDialog' => true,
        ]);
    }

    public function store(SaveLegacyDutyRequest $request): RedirectResponse {
        $this->ensureAdmin();

        LegacyNotdienst::query()->create($request->validated());

        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst'])->with('success', 'Notdienst angelegt.');
    }

    public function edit(LegacyNotdienst $notdienst): View|RedirectResponse {
        $request = request();
        $this->ensureAdmin();

        if ($redirect = $this->redirectToWeekIfMigrated(EmergencyAssignment::class, (int) $notdienst->id)) {
            return $redirect;
        }

        // Löschen fragt im Altportal auf einer eigenen Seite nach.
        if (LegacyView::isClassic($request) && $request->boolean('delete')) {
            return view('legacy.classic.diary.duty-delete', ['item' => $notdienst->load('mitarbeiter:id,uname'), 'kind' => 'notdienst']);
        }

        return view(LegacyView::pick($request, 'legacy.notdienst._form_dialog', 'legacy.classic.diary.duty-form'), [
            ...$this->legacyDutyFormData($notdienst, true),
            'kind' => 'notdienst',
            'isDialog' => true,
        ]);
    }

    public function update(SaveLegacyDutyRequest $request, LegacyNotdienst $notdienst): RedirectResponse {
        $this->ensureAdmin();

        $notdienst->update($request->validated());

        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst'])->with('success', 'Notdienst aktualisiert.');
    }

    public function destroy(LegacyNotdienst $notdienst): RedirectResponse {
        $this->ensureAdmin();

        $notdienst->delete();

        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst'])->with('success', 'Notdienst geloescht.');
    }
}
