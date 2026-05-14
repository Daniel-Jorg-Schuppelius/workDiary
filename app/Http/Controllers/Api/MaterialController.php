<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\Material\MaterialProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MaterialController extends Controller
{
    public function index(Request $request, MaterialProviderRegistry $registry): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Material::class);
        $q = $request->string('q')->toString();
        $provider = $request->string('provider', 'local')->toString();

        $items = $provider === '*'
            ? $registry->searchAll($q, (int) $request->input('limit', 25))
            : ($registry->get($provider)?->search($q, (int) $request->input('limit', 25)) ?? collect());

        return MaterialResource::collection($items);
    }
}
