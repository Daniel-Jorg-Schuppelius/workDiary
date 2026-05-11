<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftTypeRequest;
use App\Http\Requests\UpdateShiftTypeRequest;
use App\Models\ShiftType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ShiftTypeController extends Controller {
    public function store(StoreShiftTypeRequest $request): JsonResponse {
        /** @var \App\Models\User $auth */
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
        /** @var \App\Models\User $auth */
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
