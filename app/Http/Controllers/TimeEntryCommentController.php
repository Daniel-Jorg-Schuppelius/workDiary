<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryCommentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Comment, TimeEntry};
use App\Support\Setting;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};

class TimeEntryCommentController extends Controller {
    /**
     * Comments are intentionally allowed even when the entry is locked
     * (signed/locked/exported) or its correction window has elapsed:
     * commenting is the audit-friendly way to communicate after-the-fact
     * corrections without mutating the entry itself.
     */
    public function store(Request $request, TimeEntry $timeEntry): RedirectResponse {
        Gate::authorize('view', $timeEntry);
        Gate::authorize('create', Comment::class);

        $body = $request->validate([
            'body' => ['required', 'string', 'max:' . (int) Setting::get('validation.comment.body_max', 5000)],
        ])['body'];

        $timeEntry->comments()->create([
            'user_id' => Auth::id(),
            'body' => $body,
        ]);

        $date = $timeEntry->date instanceof \DateTimeInterface
            ? Carbon::instance($timeEntry->date)->toDateString()
            : (string) $timeEntry->date;

        return redirect()
            ->route('today.show', ['date' => $date])
            ->withFragment('time-entry-' . $timeEntry->getKey())
            ->with('success', __('Kommentar gespeichert.'));
    }
}
