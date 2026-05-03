<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TagController extends Controller {
    public function index(): AnonymousResourceCollection {
        return TagResource::collection(Tag::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse {
        Gate::authorize('create', Tag::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);
        $tag = Tag::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
        ]);
        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    public function update(Request $request, Tag $tag): TagResource {
        Gate::authorize('update', $tag);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:tags,name,' . $tag->id],
            'color' => ['nullable', 'string', 'max:16'],
        ]);
        $tag->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
        ]);
        return new TagResource($tag);
    }

    public function destroy(Tag $tag): JsonResponse {
        Gate::authorize('delete', $tag);
        $tag->delete();
        return response()->json(['status' => 'deleted']);
    }
}
