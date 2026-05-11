<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesLegacyDutyCrud;
use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Http\Requests\SaveLegacyDutyRequest;
use App\Models\EmergencyAssignment;
use App\Models\Legacy\LegacyNotdienst;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyNotdienstController extends Controller {
    use RequiresLegacyAdmin;
    use ManagesLegacyDutyCrud;

    public function index(Request $request): View {
        /** @var \Illuminate\Database\Eloquent\Builder<LegacyNotdienst> $query */
        $query = LegacyNotdienst::query()->with('user:id,uname')->orderBy('von')->orderBy('user');

        return view('legacy.notdienst.index', $this->legacyDutyIndexData($request, $query));
    }

    public function create(Request $request): View {
        $this->ensureAdmin();

        return view('legacy.notdienst._form_dialog', [
            ...$this->legacyDutyFormData(null, false),
            'isDialog' => true,
        ]);
    }

    public function store(SaveLegacyDutyRequest $request): RedirectResponse {
        $this->ensureAdmin();

        LegacyNotdienst::query()->create($request->validated());

        return redirect()->route('legacy.notdienst.index')->with('success', 'Notdienst angelegt.');
    }

    public function edit(LegacyNotdienst $notdienst): View|RedirectResponse {
        $this->ensureAdmin();

        if ($redirect = $this->redirectToWeekIfMigrated(EmergencyAssignment::class, (int) $notdienst->id)) {
            return $redirect;
        }

        return view('legacy.notdienst._form_dialog', [
            ...$this->legacyDutyFormData($notdienst, true),
            'isDialog' => true,
        ]);
    }

    public function update(SaveLegacyDutyRequest $request, LegacyNotdienst $notdienst): RedirectResponse {
        $this->ensureAdmin();

        $notdienst->update($request->validated());

        return redirect()->route('legacy.notdienst.index')->with('success', 'Notdienst aktualisiert.');
    }

    public function destroy(LegacyNotdienst $notdienst): RedirectResponse {
        $this->ensureAdmin();

        $notdienst->delete();

        return redirect()->route('legacy.notdienst.index')->with('success', 'Notdienst geloescht.');
    }
}
