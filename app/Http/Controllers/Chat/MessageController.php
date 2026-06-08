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

    /** Serverseitig erkannte MIME-Typen (Defense-in-Depth gegen umbenannte Dateien). */
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip-compressed',
    ];

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

        // Erste ungelesene Nachricht (für den "Neue Nachrichten"-Trenner).
        $user = Auth::user();
        $lastRead = \Illuminate\Support\Facades\DB::table('chat_channel_user')
            ->where('channel_id', $channel->id)->where('user_id', $user?->id)->value('last_read_at');
        $firstUnread = $channel->messages()->whereNull('parent_id')
            ->where('user_id', '!=', $user?->id)
            ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->orderBy('id')->first(['id']);

        // Höchster Lesestand der anderen Mitglieder (für ✓✓ an eigenen Nachrichten).
        $othersRead = \Illuminate\Support\Facades\DB::table('chat_channel_user')
            ->where('channel_id', $channel->id)->where('user_id', '!=', $user?->id)
            ->max('last_read_at');
        $othersReadTs = $othersRead ? \Illuminate\Support\Carbon::parse($othersRead)->getTimestamp() : 0;

        return response()->json([
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,            // numerisch: Pagination-Cursor (intern)
                'sqid' => $m->sqid,        // opak: DOM-Id / Dedup / API
                'pinned' => $m->isPinned(),
                'html' => $this->render($m),
            ])->all(),
            'oldest_id' => $messages->first()?->id,
            'has_more' => $messages->count() === self::PAGE && ! $after,
            'first_unread_id' => $firstUnread?->sqid,
            'others_read_ts' => $othersReadTs,
        ]);
    }

    public function store(Request $request, Channel $channel): JsonResponse {
        Gate::authorize('post', $channel);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000', 'required_without:files'],
            'parent_id' => ['nullable', 'string'],
            'quoted_id' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'max:' . self::MAX_FILE_KB],
        ]);

        // Geplanter Versand: Nachricht in die Warteschlange (eigene Tabelle) legen.
        if (! empty($data['scheduled_at'])) {
            $when = \App\Support\Tz::parse($data['scheduled_at']);
            if ($when->isFuture()) {
                abort_if(blank($data['body'] ?? null), 422, (string) __('Geplante Nachrichten benötigen Text.'));
                \App\Models\Chat\ScheduledMessage::create([
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                    'body' => $data['body'],
                    'scheduled_at' => $when,
                ]);

                return response()->json(['scheduled' => true, 'at' => $when->getTimestamp()], 202);
            }
        }

        // parent_id (Thread) und quoted_id (Zitat) kommen als Sqid → dekodieren
        // und nur Nachrichten desselben Kanals zulassen.
        $parentId = $this->resolveChannelMessageId($channel, $data['parent_id'] ?? null);
        $quotedId = $this->resolveChannelMessageId($channel, $data['quoted_id'] ?? null);

        $message = $channel->messages()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'quoted_id' => $quotedId,
            'body' => $data['body'] ?? null,
            'type' => 'text',
        ]);

        foreach ((array) $request->file('files', []) as $file) {
            $this->attach($message, $file);
        }

        $message->load($this->eager());
        broadcast(new MessageSent($message))->toOthers();
        $this->notifyMembers($channel, $user->id);
        app(\App\Services\PushNotifier::class)->chatMessage($message);
        $channel->members()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json(['id' => $message->id, 'parent_id' => $parentId, 'html' => $this->render($message)], 201);
    }

    /** Nachricht in einen anderen Kanal weiterleiten (Kopie mit Herkunfts-Markierung). */
    public function forward(Request $request, Message $message): JsonResponse {
        Gate::authorize('view', $message->channel);
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate(['channel_id' => ['required', 'string']]);

        // Zielkanal kommt als Sqid → dekodieren (org-gescopt via resolveRouteBinding).
        $target = (new Channel)->resolveRouteBinding($data['channel_id']);
        abort_if(! $target instanceof Channel || ! $target->hasMember($user), 403);
        Gate::authorize('post', $target);

        $copy = $target->messages()->create([
            'user_id' => $user->id,
            'body' => $message->body,
            'type' => 'text',
            'forwarded_from_user_id' => $message->user_id,
        ]);
        $copy->load($this->eager());
        broadcast(new MessageSent($copy))->toOthers();
        $this->notifyMembers($target, $user->id);
        app(\App\Services\PushNotifier::class)->chatMessage($copy);

        return response()->json(['channel_id' => $target->id, 'id' => $copy->id], 201);
    }

    /** Erinnerung an eine Nachricht für den aktuellen Nutzer anlegen. */
    public function remind(Request $request, Message $message): JsonResponse {
        Gate::authorize('view', $message->channel);
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate(['remind_at' => ['required', 'date']]);

        // Eingabe wird in der Anzeige-Zeitzone interpretiert und als UTC gespeichert.
        $when = \App\Support\Tz::parse($data['remind_at']);
        abort_if($when->isPast(), 422, (string) __('Der Zeitpunkt muss in der Zukunft liegen.'));

        \App\Models\Chat\Reminder::create([
            'user_id' => $user->id,
            'message_id' => $message->id,
            'channel_id' => $message->channel_id,
            'remind_at' => $when,
        ]);

        return response()->json(['ok' => true], 201);
    }

    /** Nachricht für den aktuellen Nutzer (de)markieren (Favorit/Stern). */
    public function star(Message $message): JsonResponse {
        Gate::authorize('view', $message->channel);
        /** @var User $user */
        $user = Auth::user();
        $message->starredBy()->toggle($user->id);
        $message->load($this->eager());

        return response()->json(['id' => $message->id, 'html' => $this->render($message)]);
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
        $channel = $message->channel;
        $sqid = $message->sqid;
        $message->delete();
        if ($channel instanceof Channel) {
            broadcast(new MessageDeleted($channel->sqid, $sqid))->toOthers();
        }

        return response()->json(['id' => $sqid]);
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
                'channel_id' => $m->channel->sqid ?? null,
                'channel' => $m->channel->name ?? __('Direktnachricht'),
                'user' => $m->user?->name,
                'snippet' => Str::limit((string) $m->body, 140),
                'message_id' => $m->sqid,
            ])->all(),
        ]);
    }

    /** @return array<int, string> */
    private function eager(): array {
        return ['user:id,name', 'attachments', 'reactions', 'poll.options.votes', 'replies', 'quoted.user:id,name', 'forwardedFrom:id,name', 'starredBy:id'];
    }

    private function render(Message $message): string {
        return view('chat._message', ['message' => $message, 'me' => Auth::user()])->render();
    }

    /** Andere Kanal-Mitglieder über eine neue Nachricht informieren →
     *  Sidebar-Kanalliste (Ungelesen-Markierung) live aktualisieren. */
    private function notifyMembers(Channel $channel, int $exceptUserId): void {
        $channel->members()->where('users.id', '!=', $exceptUserId)->pluck('users.id')
            ->each(fn ($id) => broadcast(new \App\Events\Chat\ChannelListChanged((int) $id)));
    }

    /** Dekodiert einen Nachrichten-Sqid und liefert die numerische ID nur,
     *  wenn die Nachricht zum angegebenen Kanal gehört (sonst null). */
    private function resolveChannelMessageId(Channel $channel, ?string $sqid): ?int {
        if (! filled($sqid)) {
            return null;
        }
        $message = (new Message)->resolveRouteBinding($sqid);

        return ($message instanceof Message && $message->channel_id === $channel->id) ? $message->id : null;
    }

    private function attach(Message $message, \Illuminate\Http\UploadedFile $file): void {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return;
        }
        // Defense-in-Depth: serverseitig erkannten MIME-Typ gegen Allow-List prüfen
        // (verhindert ausführbare/unerwünschte Inhalte hinter erlaubter Endung).
        if (! in_array($file->getMimeType() ?? '', self::ALLOWED_MIMES, true)) {
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
