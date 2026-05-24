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

use App\Models\{Comment, DiaryEntry, TimeEntry};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};

class CommentController extends Controller {
    public function store(Request $request, DiaryEntry $diary): RedirectResponse {
        Gate::authorize('create', Comment::class);

        $diary->comments()->create([
            'user_id' => Auth::id(),
            'body' => $this->validateBody($request),
        ]);

        return $this->redirectToParent($diary)
            ->with('success', __('Kommentar gespeichert.'));
    }

    public function update(Request $request, Comment $comment): RedirectResponse {
        Gate::authorize('update', $comment);

        $comment->update(['body' => $this->validateBody($request)]);

        return $this->redirectToParent($comment->commentable)
            ->with('success', __('Kommentar aktualisiert.'));
    }

    public function destroy(Comment $comment): RedirectResponse {
        Gate::authorize('delete', $comment);

        $parent = $comment->commentable;
        $comment->delete();

        return $this->redirectToParent($parent)
            ->with('success', __('Kommentar gelöscht.'));
    }

    private function validateBody(Request $request): string {
        return $request->validate([
            'body' => ['required', 'string', 'max:' . (int) setting('validation.comment.body_max', 5000)],
        ])['body'];
    }

    private function redirectToParent(?Model $parent): RedirectResponse {
        if ($parent instanceof DiaryEntry) {
            return redirect()
                ->route('diary.show', $parent)
                ->withFragment('comments');
        }

        if ($parent instanceof TimeEntry) {
            $date = $parent->date instanceof \DateTimeInterface
                ? Carbon::instance($parent->date)->toDateString()
                : (string) $parent->date;

            return redirect()
                ->route('today.show', ['date' => $date])
                ->withFragment('time-entry-' . $parent->getKey());
        }

        return redirect()->back()->withFragment('comments');
    }
}
