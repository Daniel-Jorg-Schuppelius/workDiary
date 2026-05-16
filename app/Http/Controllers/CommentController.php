<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\DiaryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, DiaryEntry $diary): RedirectResponse
    {
        Gate::authorize('create', Comment::class);

        $diary->comments()->create([
            'user_id' => Auth::id(),
            'body' => $this->validateBody($request),
        ]);

        return redirect()
            ->route('diary.show', $diary)
            ->withFragment('comments')
            ->with('success', __('Kommentar gespeichert.'));
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        Gate::authorize('update', $comment);

        $comment->update(['body' => $this->validateBody($request)]);

        return redirect()
            ->route('diary.show', $comment->diaryEntry)
            ->withFragment('comments')
            ->with('success', __('Kommentar aktualisiert.'));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $diaryEntry = $comment->diaryEntry;
        $comment->delete();

        return redirect()
            ->route('diary.show', $diaryEntry)
            ->withFragment('comments')
            ->with('success', __('Kommentar gelöscht.'));
    }

    private function validateBody(Request $request): string
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ])['body'];
    }
}
