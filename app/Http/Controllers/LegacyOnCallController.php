<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesLegacyDutyCrud;
use App\Http\Controllers\Concerns\RequiresLegacyAdmin;
use App\Http\Requests\SaveLegacyDutyRequest;
use App\Models\Legacy\LegacyOnCall;
use App\Models\OnCallShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyOnCallController extends Controller {
    use RequiresLegacyAdmin;
    use ManagesLegacyDutyCrud;

    public function index(Request $request): View {
        /** @var \Illuminate\Database\Eloquent\Builder<LegacyOnCall> $query */
        $query = LegacyOnCall::query()->with('user:id,uname')->orderBy('von')->orderBy('user');

        return view('legacy.oncall.index', $this->legacyDutyIndexData($request, $query));
    }

    public function create(): View {
        $this->ensureAdmin();

        return view('legacy.oncall.form', $this->legacyDutyFormData(null, false));
    }

    public function store(SaveLegacyDutyRequest $request): RedirectResponse {
        $this->ensureAdmin();

        LegacyOnCall::query()->create($request->validated());

        return redirect()->route('legacy.oncall.index')->with('success', 'Bereitschaft angelegt.');
    }

    public function edit(LegacyOnCall $oncall): View|RedirectResponse {
        $this->ensureAdmin();

        if ($redirect = $this->redirectToWeekIfMigrated(OnCallShift::class, (int) $oncall->id)) {
            return $redirect;
        }

        return view('legacy.oncall.form', $this->legacyDutyFormData($oncall, true));
    }

    public function update(SaveLegacyDutyRequest $request, LegacyOnCall $oncall): RedirectResponse {
        $this->ensureAdmin();

        $oncall->update($request->validated());

        return redirect()->route('legacy.oncall.index')->with('success', 'Bereitschaft aktualisiert.');
    }

    public function destroy(LegacyOnCall $oncall): RedirectResponse {
        $this->ensureAdmin();

        $oncall->delete();

        return redirect()->route('legacy.oncall.index')->with('success', 'Bereitschaft geloescht.');
    }
}
