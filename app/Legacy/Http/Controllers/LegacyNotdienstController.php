<?php

namespace App\Legacy\Http\Controllers;
use App\Http\Controllers\Controller;

use App\Legacy\Http\Concerns\ManagesLegacyDutyCrud;
use App\Legacy\Http\Concerns\RequiresLegacyAdmin;
use App\Legacy\Http\Requests\SaveLegacyDutyRequest;
use App\Models\EmergencyAssignment;
use App\Legacy\Models\LegacyNotdienst;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyNotdienstController extends Controller
{
    use ManagesLegacyDutyCrud;
    use RequiresLegacyAdmin;

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst']);
    }

    public function create(Request $request): View
    {
        $this->ensureAdmin();

        return view('legacy.notdienst._form_dialog', [
            ...$this->legacyDutyFormData(null, false),
            'isDialog' => true,
        ]);
    }

    public function store(SaveLegacyDutyRequest $request): RedirectResponse
    {
        $this->ensureAdmin();

        LegacyNotdienst::query()->create($request->validated());

        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst'])->with('success', 'Notdienst angelegt.');
    }

    public function edit(LegacyNotdienst $notdienst): View|RedirectResponse
    {
        $this->ensureAdmin();

        if ($redirect = $this->redirectToWeekIfMigrated(EmergencyAssignment::class, (int) $notdienst->id)) {
            return $redirect;
        }

        return view('legacy.notdienst._form_dialog', [
            ...$this->legacyDutyFormData($notdienst, true),
            'isDialog' => true,
        ]);
    }

    public function update(SaveLegacyDutyRequest $request, LegacyNotdienst $notdienst): RedirectResponse
    {
        $this->ensureAdmin();

        $notdienst->update($request->validated());

        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst'])->with('success', 'Notdienst aktualisiert.');
    }

    public function destroy(LegacyNotdienst $notdienst): RedirectResponse
    {
        $this->ensureAdmin();

        $notdienst->delete();

        return redirect()->route('legacy.diary.index', ['tab' => 'notdienst'])->with('success', 'Notdienst geloescht.');
    }
}
