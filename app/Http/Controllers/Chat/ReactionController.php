<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReactionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Chat;

use App\Events\Chat\ReactionToggled;
use App\Http\Controllers\Controller;
use App\Models\Chat\Message;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

class ReactionController extends Controller {
    /** Reaktion (Emoji/Like) an-/abschalten. */
    public function toggle(Request $request, Message $message): JsonResponse {
        Gate::authorize('react', $message);
        $data = $request->validate(['emoji' => ['required', 'string', 'max:32']]);
        $userId = (int) Auth::id();

        $existing = $message->reactions()->where('user_id', $userId)->where('emoji', $data['emoji'])->first();
        if ($existing) {
            $existing->delete();
        } else {
            $message->reactions()->create(['user_id' => $userId, 'emoji' => $data['emoji']]);
        }

        broadcast(new ReactionToggled($message->channel_id, $message->id))->toOthers();
        $message->load(['user:id,name', 'attachments', 'reactions', 'poll.options.votes', 'replies']);

        return response()->json([
            'id' => $message->id,
            'html' => view('chat._message', ['message' => $message, 'me' => Auth::user()])->render(),
        ]);
    }
}
