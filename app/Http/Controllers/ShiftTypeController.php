<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftTypeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\{StoreShiftTypeRequest, UpdateShiftTypeRequest};
use App\Models\{ShiftType, User};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class ShiftTypeController extends Controller {
    // ── HTML CRUD (Verwaltungsoberfläche, Admin-only) ────────────────────────

    public function index(Request $request): View {
        Gate::authorize('viewAny', ShiftType::class);

        $search = $request->string('q')->toString();

        $types = ShiftType::query()
            ->withCount('scheduledShifts')
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('abbreviation', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

        return view('shift-types.index', compact('types', 'search'));
    }

    public function create(): View {
        Gate::authorize('create', ShiftType::class);

        return view('shift-types._form_dialog', [
            'type' => null,
            'isEdit' => false,
        ]);
    }

    public function htmlStore(StoreShiftTypeRequest $request): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validated();
        $data['created_by'] = $auth->id;
        $data['organization_id'] = $auth->organization_id;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        ShiftType::create($data);

        return redirect()->route('shift-types.index')
            ->with('success', __('Schichttyp gespeichert.'));
    }

    public function edit(ShiftType $shiftType): View {
        Gate::authorize('update', $shiftType);

        return view('shift-types._form_dialog', [
            'type' => $shiftType,
            'isEdit' => true,
        ]);
    }

    public function htmlUpdate(UpdateShiftTypeRequest $request, ShiftType $shiftType): RedirectResponse {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $shiftType->update($data);

        return redirect()->route('shift-types.index')
            ->with('success', __('Schichttyp aktualisiert.'));
    }

    public function htmlDestroy(ShiftType $shiftType): RedirectResponse {
        Gate::authorize('delete', $shiftType);

        if ($shiftType->scheduledShifts()->exists()) {
            return redirect()->route('shift-types.index')
                ->with('error', __('Schichttyp wird noch verwendet und kann nicht gelöscht werden.'));
        }

        $shiftType->delete();

        return redirect()->route('shift-types.index')
            ->with('success', __('Schichttyp gelöscht.'));
    }

    // ── JSON API (eingebettet in den Schedule-Dialog) ────────────────────────

    public function store(StoreShiftTypeRequest $request): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validated();
        $data['created_by'] = $auth->id;

        $shiftType = ShiftType::create($data);

        return response()->json($shiftType, 201);
    }

    public function update(UpdateShiftTypeRequest $request, ShiftType $shiftType): JsonResponse {
        $shiftType->update($request->validated());

        return response()->json($shiftType);
    }

    public function destroy(ShiftType $shiftType): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            abort(403);
        }

        // Do not delete if shifts reference this type
        if ($shiftType->scheduledShifts()->exists()) {
            return response()->json(['message' => __('Schichttyp wird noch verwendet und kann nicht gelöscht werden.')], 422);
        }

        $shiftType->delete();

        return response()->json(['message' => __('Schichttyp gelöscht.')]);
    }
}
