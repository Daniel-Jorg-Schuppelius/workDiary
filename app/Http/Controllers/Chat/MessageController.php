<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MessageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Chat;

use App\Events\Chat\{MessageDeleted, MessageSent, MessageUpdated};
use App\Http\Controllers\Controller;
use App\Models\Chat\{Channel, Message};
use App\Models\User;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Support\Str;

class MessageController extends Controller {
    private const PAGE = 30;
    private const MAX_FILE_KB = 20480; // 20 MB
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip'];

    /** Cursor-paginierte Top-Level-Nachrichten eines Kanals (JSON mit gerendertem HTML). */
    public function index(Request $request, Channel $channel): JsonResponse {
        Gate::authorize('view', $channel);

        $query = $channel->messages()->whereNull('parent_id')
            ->with($this->eager());

        if ($before = (int) $request->query('before', 0)) {
            $query->where('id', '<', $before);
        }
        if ($after = (int) $request->query('after', 0)) {
            $query->where('id', '>', $after)->orderBy('id'); // neue Nachrichten aufsteigend
            $messages = $query->limit(self::PAGE)->get();
        } else {
            $messages = $query->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
        }

        return response()->json([
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'pinned' => $m->isPinned(),
                'html' => $this->render($m),
            ])->all(),
            'oldest_id' => $messages->first()?->id,
            'has_more' => $messages->count() === self::PAGE && ! $after,
        ]);
    }

    public function store(Request $request, Channel $channel): JsonResponse {
        Gate::authorize('post', $channel);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000', 'required_without:files'],
            'parent_id' => ['nullable', 'integer'],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:' . self::MAX_FILE_KB],
        ]);

        $parentId = null;
        if (! empty($data['parent_id'])) {
            $parent = $channel->messages()->whereKey((int) $data['parent_id'])->first();
            $parentId = $parent?->id;
        }

        $message = $channel->messages()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => $data['body'] ?? null,
            'type' => 'text',
        ]);

        foreach ((array) $request->file('files', []) as $file) {
            $this->attach($message, $file);
        }

        $message->load($this->eager());
        broadcast(new MessageSent($message))->toOthers();
        app(\App\Services\PushNotifier::class)->chatMessage($message);
        $channel->members()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json(['id' => $message->id, 'parent_id' => $parentId, 'html' => $this->render($message)], 201);
    }

    public function update(Request $request, Message $message): JsonResponse {
        Gate::authorize('update', $message);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $message->update(['body' => $data['body'], 'edited_at' => now()]);
        $message->load($this->eager());
        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['id' => $message->id, 'html' => $this->render($message)]);
    }

    public function destroy(Message $message): JsonResponse {
        Gate::authorize('delete', $message);
        $channelId = $message->channel_id;
        $id = $message->id;
        $message->delete();
        broadcast(new MessageDeleted($channelId, $id))->toOthers();

        return response()->json(['id' => $id]);
    }

    public function pin(Message $message): JsonResponse {
        Gate::authorize('pin', $message);
        $message->update(['pinned_at' => $message->isPinned() ? null : now(), 'pinned_by' => $message->isPinned() ? null : Auth::id()]);
        $message->load($this->eager());
        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['id' => $message->id, 'pinned' => $message->isPinned(), 'html' => $this->render($message)]);
    }

    /** Einzelne Nachricht als HTML (für Patch nach Reaktion/Edit/Poll). */
    public function show(Message $message): JsonResponse {
        Gate::authorize('view', $message->channel);
        $message->load($this->eager());

        return response()->json(['id' => $message->id, 'pinned' => $message->isPinned(), 'html' => $this->render($message)]);
    }

    /** Thread-Antworten (Kommentare) einer Nachricht. */
    public function replies(Message $message): JsonResponse {
        Gate::authorize('view', $message->channel);
        $replies = $message->replies()->with($this->eager())->orderBy('id')->get();

        return response()->json([
            'parent' => ['id' => $message->id, 'html' => $this->render($message)],
            'replies' => $replies->map(fn (Message $m) => ['id' => $m->id, 'html' => $this->render($m)])->all(),
        ]);
    }

    /** Gepinnte Nachrichten eines Kanals. */
    public function pinned(Channel $channel): JsonResponse {
        Gate::authorize('view', $channel);
        $pinned = $channel->messages()->whereNotNull('pinned_at')->with($this->eager())->orderByDesc('pinned_at')->get();

        return response()->json(['messages' => $pinned->map(fn (Message $m) => ['id' => $m->id, 'html' => $this->render($m)])->all()]);
    }

    /** Volltextsuche über Nachrichten der Kanäle, in denen der Nutzer Mitglied ist. */
    public function search(Request $request): JsonResponse {
        /** @var User $user */
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $messages = Message::query()
            ->whereIn('channel_id', fn ($sub) => $sub->select('channel_id')->from('chat_channel_user')->where('user_id', $user->id))
            ->where('body', 'like', '%' . $q . '%')
            ->with(['channel:id,name,type', 'user:id,name'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $messages->map(fn (Message $m) => [
                'channel_id' => $m->channel_id,
                'channel' => $m->channel->name ?? __('Direktnachricht'),
                'user' => $m->user?->name,
                'snippet' => Str::limit((string) $m->body, 140),
                'message_id' => $m->id,
            ])->all(),
        ]);
    }

    /** @return array<int, string> */
    private function eager(): array {
        return ['user:id,name', 'attachments', 'reactions', 'poll.options.votes', 'replies'];
    }

    private function render(Message $message): string {
        return view('chat._message', ['message' => $message, 'me' => Auth::user()])->render();
    }

    private function attach(Message $message, \Illuminate\Http\UploadedFile $file): void {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return;
        }
        $path = $file->storeAs('attachments/chat/' . now()->format('Y/m'), Str::uuid()->toString() . '.' . $ext, 'local');
        $message->attachments()->create([
            'user_id' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
