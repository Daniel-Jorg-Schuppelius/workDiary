<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\DiaryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller {
    public function store(Request $request, DiaryEntry $diary): RedirectResponse {
        Gate::authorize('create', Comment::class);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $diary->comments()->create([
            'user_id' => Auth::id(),
            'body' => $data['body'],
        ]);

        return redirect()
            ->route('diary.show', $diary)
            ->withFragment('comments')
            ->with('success', __('Kommentar gespeichert.'));
    }

    public function update(Request $request, Comment $comment): RedirectResponse {
        Gate::authorize('update', $comment);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update($data);

        return redirect()
            ->route('diary.show', $comment->diary_entry_id)
            ->withFragment('comments')
            ->with('success', __('Kommentar aktualisiert.'));
    }

    public function destroy(Comment $comment): RedirectResponse {
        Gate::authorize('delete', $comment);

        $diaryId = $comment->diary_entry_id;
        $comment->delete();

        return redirect()
            ->route('diary.show', $diaryId)
            ->withFragment('comments')
            ->with('success', __('Kommentar gelöscht.'));
    }
}
