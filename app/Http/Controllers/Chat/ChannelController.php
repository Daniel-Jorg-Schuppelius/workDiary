<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChannelController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Channel;
use App\Models\User;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChannelController extends Controller {
    /** Chat-Startseite: Kanalliste + optional aktiver Kanal. */
    public function index(?Channel $channel = null): View {
        Gate::authorize('viewAny', Channel::class);
        /** @var User $user */
        $user = Auth::user();

        $channels = $this->visibleChannels($user);

        if ($channel && $channel->exists) {
            Gate::authorize('view', $channel);
        }

        // Mitarbeiter der eigenen Organisation (Mandantengrenze explizit, da User
        // kein globaler Org-Scope), ohne sich selbst.
        $users = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Ohne expliziten Kanal: bei genau EINEM Kanal diesen direkt öffnen
        // (es gibt nichts zu wählen); bei mehreren/keinem die Kanalliste (mobil)
        // bzw. den Empty-State (Desktop) zeigen – damit der Zurück-Pfeil greift.
        $active = $channel && $channel->exists
            ? $channel
            : ($channels->count() === 1 ? $channels->first() : null);

        return view('chat.index', [
            'channels' => $channels,
            'activeChannel' => $active,
            'orgUsers' => $users,
        ]);
    }

    /** Gesamtzahl ungelesener Nachrichten des Nutzers (für das Header-Badge, live). */
    public function unreadCount(): JsonResponse {
        $user = Auth::user();

        return response()->json(['count' => $user instanceof User ? Channel::unreadTotalFor($user) : 0]);
    }

    /** Gerenderte Kanalliste (für Live-Refresh der Sidebar). */
    public function channelList(Request $request): JsonResponse {
        /** @var User $user */
        $user = Auth::user();
        $channels = $this->visibleChannels($user);
        $activeSqid = (string) $request->query('active', '');
        $activeChannel = $activeSqid !== '' ? $channels->firstWhere('sqid', $activeSqid) : null;

        return response()->json([
            'html' => view('chat._channel_list', [
                'channels' => $channels,
                'activeChannel' => $activeChannel,
                'me' => $user,
            ])->render(),
        ]);
    }

    /**
     * Betroffene Nutzer über eine geänderte Kanalliste informieren (Live-Sidebar).
     *
     * @param array<int, int|string> $userIds
     */
    private function notifyChannelListChanged(array $userIds): void {
        foreach (array_unique(array_map('intval', $userIds)) as $id) {
            broadcast(new \App\Events\Chat\ChannelListChanged($id));
        }
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Channel::class);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:channel,group'],
            'visibility' => ['required', 'in:public,private'],
            'members' => ['sometimes', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $channel = Channel::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'visibility' => $data['visibility'],
            'created_by' => $user->id,
        ]);
        $channel->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        $added = [];
        foreach (array_unique($data['members'] ?? []) as $memberId) {
            if ((int) $memberId !== $user->id) {
                $channel->members()->syncWithoutDetaching([(int) $memberId => ['role' => 'member', 'joined_at' => now()]]);
                $added[] = (int) $memberId;
            }
        }
        $this->notifyChannelListChanged($added);

        return redirect()->route('chat.show', $channel)->with('success', __('Kanal erstellt.'));
    }

    /** Direktnachricht 1:1 – findet existierenden DM-Kanal oder legt ihn an. */
    public function direct(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $other = (int) $data['user_id'];
        abort_if($other === $user->id, 422);

        // Bestehenden DM-Kanal genau zwischen diesen beiden Personen finden.
        // has('members','=',2) erzeugt eine WHERE-Count-Subquery (kein HAVING/
        // GROUP BY) und ist damit auch auf SQLite gültig.
        $existing = Channel::query()->where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->whereKey($user->id))
            ->whereHas('members', fn ($q) => $q->whereKey($other))
            ->has('members', '=', 2)->first();

        if (! $existing) {
            $existing = Channel::create(['type' => 'direct', 'visibility' => 'private', 'created_by' => $user->id]);
            $existing->members()->attach([
                $user->id => ['role' => 'owner', 'joined_at' => now()],
                $other => ['role' => 'member', 'joined_at' => now()],
            ]);
            $this->notifyChannelListChanged([$other]);
        }

        return redirect()->route('chat.show', $existing);
    }

    public function update(Request $request, Channel $channel): RedirectResponse {
        Gate::authorize('update', $channel);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:public,private'],
            'is_archived' => ['sometimes', 'boolean'],
        ]);
        $channel->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'],
            'is_archived' => $request->boolean('is_archived'),
        ]);

        return back()->with('success', __('Kanal aktualisiert.'));
    }

    public function destroy(Channel $channel): RedirectResponse {
        Gate::authorize('delete', $channel);
        $channel->delete();

        return redirect()->route('chat.index')->with('success', __('Kanal gelöscht.'));
    }

    public function join(Channel $channel): RedirectResponse {
        Gate::authorize('join', $channel);
        /** @var User $user */
        $user = Auth::user();
        $channel->members()->syncWithoutDetaching([$user->id => ['role' => 'member', 'joined_at' => now()]]);

        return redirect()->route('chat.show', $channel);
    }

    public function leave(Channel $channel): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $channel->members()->detach($user->id);

        return redirect()->route('chat.index')->with('success', __('Kanal verlassen.'));
    }

    public function invite(Request $request, Channel $channel): RedirectResponse {
        Gate::authorize('manageMembers', $channel);
        $data = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);
        foreach (array_unique($data['members']) as $memberId) {
            $channel->members()->syncWithoutDetaching([(int) $memberId => ['role' => 'member', 'joined_at' => now()]]);
        }
        $this->notifyChannelListChanged($data['members']);

        return back()->with('success', __('Mitglieder eingeladen.'));
    }

    /** Markiert den Kanal für den Benutzer als gelesen (pivot.last_read_at). */
    public function markRead(Channel $channel): RedirectResponse {
        Gate::authorize('view', $channel);
        /** @var User $user */
        $user = Auth::user();
        $now = now();
        $channel->members()->updateExistingPivot($user->id, ['last_read_at' => $now]);
        broadcast(new \App\Events\Chat\ChannelRead($channel->sqid, $user->id, $now->getTimestamp()))->toOthers();

        return back();
    }

    /** @return \Illuminate\Support\Collection<int, Channel> */
    private function visibleChannels(User $user) {
        return Channel::query()
            ->where('is_archived', false)
            ->where(function ($q) use ($user): void {
                $q->whereHas('members', fn ($m) => $m->whereKey($user->id))
                    ->orWhere(fn ($p) => $p->where('type', 'channel')->where('visibility', 'public'));
            })
            ->with(['members:id,name'])
            ->orderByRaw("CASE type WHEN 'channel' THEN 0 WHEN 'group' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();
    }

    private function uniqueSlug(string $name): string {
        $base = Str::slug($name) ?: 'kanal';
        $slug = $base;
        $i = 1;
        while (Channel::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
