<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmergencyAssignmentResource;
use App\Models\EmergencyAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmergencyAssignmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = EmergencyAssignment::query()->with('user:id,name');
        if ($request->filled('from')) {
            $q->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('start_at', '<=', $request->to);
        }
        if (! $request->boolean('archived')) {
            $q->where('is_archived', false);
        }

        return EmergencyAssignmentResource::collection($q->orderBy('start_at')->paginate(min(100, (int) $request->input('per_page', 20))));
    }

    public function show(EmergencyAssignment $assignment): EmergencyAssignmentResource
    {
        return new EmergencyAssignmentResource($assignment->load('user:id,name'));
    }
}
