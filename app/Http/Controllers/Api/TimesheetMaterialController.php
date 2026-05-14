<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMaterialUsageRequest;
use App\Http\Resources\MaterialUsageResource;
use App\Models\Material;
use App\Models\MaterialUsage;
use App\Models\Timesheet;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TimesheetMaterialController extends Controller
{
    public function index(Timesheet $timesheet): AnonymousResourceCollection
    {
        Gate::authorize('view', $timesheet);

        return MaterialUsageResource::collection($timesheet->materialUsages()->get());
    }

    public function store(Timesheet $timesheet, SaveMaterialUsageRequest $request): MaterialUsageResource
    {
        Gate::authorize('update', $timesheet);
        $data = $request->validated();
        if (! empty($data['material_id'])) {
            $m = Material::find($data['material_id']);
            if ($m) {
                $data['unit_price'] = $data['unit_price'] ?? $m->default_unit_price;
                $data['tax_rate'] = $data['tax_rate'] ?? $m->tax_rate;
                $data['unit'] = $data['unit'] ?: $m->unit;
                $data['description'] = $data['description'] ?: $m->name;
            }
        }

        return new MaterialUsageResource($timesheet->materialUsages()->create($data));
    }

    public function update(Timesheet $timesheet, MaterialUsage $usage, SaveMaterialUsageRequest $request): MaterialUsageResource
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);
        $usage->update($request->validated());

        return new MaterialUsageResource($usage);
    }

    public function destroy(Timesheet $timesheet, MaterialUsage $usage): Response
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);
        $usage->delete();

        return response()->noContent();
    }
}
