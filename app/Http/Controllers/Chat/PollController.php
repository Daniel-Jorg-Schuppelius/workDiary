<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PollController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Chat;

use App\Events\Chat\{MessageSent, PollVoted};
use App\Http\Controllers\Controller;
use App\Models\Chat\{Channel, Poll};
use App\Models\User;
use App\Support\Tz;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate};

class PollController extends Controller {
    /** Umfrage als Nachricht im Kanal erstellen. */
    public function store(Request $request, Channel $channel): JsonResponse {
        Gate::authorize('post', $channel);
        /** @var User $user */
        $user = Auth::user();

        // Leere Options-Felder verwerfen (das Formular bietet mehrere an).
        $request->merge([
            'options' => array_values(array_filter(
                (array) $request->input('options', []),
                static fn ($o) => is_string($o) && trim($o) !== '',
            )),
        ]);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'options' => ['required', 'array', 'min:2', 'max:20'],
            'options.*' => ['required', 'string', 'max:200'],
            'multiple' => ['sometimes', 'boolean'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $message = DB::transaction(function () use ($channel, $user, $data, $request) {
            $message = $channel->messages()->create([
                'user_id' => $user->id,
                'type' => 'poll',
                'body' => $data['question'],
            ]);
            $poll = $message->poll()->create([
                'question' => $data['question'],
                'multiple' => $request->boolean('multiple'),
                'closes_at' => filled($data['closes_at'] ?? null) ? Tz::parse($data['closes_at']) : null,
            ]);
            foreach (array_values($data['options']) as $i => $label) {
                $poll->options()->create(['label' => $label, 'position' => $i]);
            }

            return $message;
        });

        $message->load(['user:id,name', 'poll.options.votes', 'attachments', 'reactions', 'replies']);
        broadcast(new MessageSent($message))->toOthers();

        return response()->json(['id' => $message->id, 'html' => $this->render($message)], 201);
    }

    /** Stimme(n) abgeben. Bei single-choice wird die vorige Stimme ersetzt. */
    public function vote(Request $request, Poll $poll): JsonResponse {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }
        $message = $poll->message;
        abort_if($message === null, 404);
        $channel = $message->channel;
        abort_if($channel === null, 404);
        Gate::authorize('view', $channel);
        abort_if(! $channel->hasMember($user), 403);
        abort_if($poll->isClosed(), 422, (string) __('Die Umfrage ist beendet.'));

        $optionIds = $poll->options()->pluck('id')->all();
        $data = $request->validate([
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['integer', 'in:' . implode(',', $optionIds ?: [0])],
        ]);
        $chosen = array_map('intval', $data['options']);
        if (! $poll->multiple) {
            $chosen = array_slice($chosen, 0, 1);
        }

        $userId = $user->id;
        DB::transaction(function () use ($poll, $chosen, $userId): void {
            // Alle bisherigen Stimmen des Users in dieser Umfrage entfernen, dann neu setzen.
            \App\Models\Chat\PollVote::query()
                ->whereIn('poll_option_id', $poll->options()->pluck('id'))
                ->where('user_id', $userId)->delete();
            foreach ($chosen as $optId) {
                \App\Models\Chat\PollVote::create(['poll_option_id' => $optId, 'user_id' => $userId]);
            }
        });

        broadcast(new PollVoted($channel->sqid, $message->sqid))->toOthers();
        $message->load(['user:id,name', 'poll.options.votes', 'attachments', 'reactions', 'replies']);

        return response()->json(['id' => $message->id, 'html' => $this->render($message)]);
    }

    private function render(\App\Models\Chat\Message $message): string {
        return view('chat._message', ['message' => $message, 'me' => Auth::user()])->render();
    }
}
