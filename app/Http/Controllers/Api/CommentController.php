<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\DiaryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller {
    public function store(Request $request, DiaryEntry $diary): JsonResponse {
        Gate::authorize('create', Comment::class);
        $data = $request->validate(['body' => ['required', 'string', 'max:65535']]);
        $comment = $diary->comments()->create([
            'user_id' => Auth::id(),
            'body' => $data['body'],
        ]);
        return (new CommentResource($comment->load('user:id,name')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Comment $comment): CommentResource {
        Gate::authorize('update', $comment);
        $data = $request->validate(['body' => ['required', 'string', 'max:65535']]);
        $comment->update($data);
        return new CommentResource($comment->fresh('user:id,name'));
    }

    public function destroy(Comment $comment): JsonResponse {
        Gate::authorize('delete', $comment);
        $comment->delete();
        return response()->json(['status' => 'deleted']);
    }
}
