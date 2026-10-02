<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubjectChannelService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\Chat\ChannelListChanged;
use App\Models\Attachments\Attachment;
use App\Models\Chat\{Channel, Message};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Collection, Str};

/**
 * Projekt- und Auftragschat (MVP-1061): ein privater Gruppenkanal je Träger.
 * Mitglieder sind die eingeteilten Personen — beim Öffnen werden neu
 * Eingeteilte ergänzt, niemand wird still entfernt. Dateien aus dem Kanal
 * zeigt der Träger an, statt sie zu kopieren.
 */
final class SubjectChannelService {
    public function channelFor(Model $subject): ?Channel {
        return Channel::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->first();
    }

    public function open(Model $subject, User $actor): Channel {
        $channel = $this->channelFor($subject);
        if ($channel === null) {
            $channel = Channel::query()->create([
                'organization_id' => $subject->getAttribute('organization_id'),
                'name' => mb_substr($this->name($subject), 0, 120),
                'slug' => $this->slug($subject),
                'description' => (string) __('chat.subject.description'),
                'type' => 'group',
                'visibility' => 'private',
                'created_by' => $actor->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ]);
            $channel->members()->attach($actor->id, ['role' => 'owner', 'joined_at' => now()]);
        }
        $this->syncMembers($channel, $subject, $actor);

        return $channel;
    }

    /** @return Collection<int, User> eingeteilte Personen des Trägers */
    public function members(Model $subject): Collection {
        $users = match (true) {
            $subject instanceof Project => $subject->assignableUsers(),
            $subject instanceof DiaryEntry => collect([$subject->user, $subject->assignedUser])
                ->merge($subject->project?->assignableUsers() ?? collect())
                ->filter(fn ($u): bool => $u instanceof User),
            default => collect(),
        };

        return $users->unique('id')->values();
    }

    /** @return Collection<int, Attachment> Dateien aus dem Kanal des Trägers, neueste zuerst */
    public function attachments(Model $subject, int $limit = 50): Collection {
        $channel = $this->channelFor($subject);
        if ($channel === null) {
            return collect();
        }

        return Attachment::query()
            ->where('attachable_type', (new Message())->getMorphClass())
            ->whereIn('attachable_id', Message::query()->where('channel_id', $channel->id)->select('id'))
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function syncMembers(Channel $channel, Model $subject, User $actor): void {
        $existing = $channel->members()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
        $added = [];
        foreach ($this->members($subject)->push($actor) as $user) {
            if (! in_array((int) $user->id, $existing, true)) {
                $channel->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);
                $existing[] = (int) $user->id;
                $added[] = (int) $user->id;
            }
        }
        foreach ($added as $id) {
            broadcast(new ChannelListChanged($id));
        }
    }

    private function name(Model $subject): string {
        return match (true) {
            $subject instanceof Project => (string) __('chat.subject.project', ['name' => $subject->name]),
            $subject instanceof DiaryEntry => (string) __('chat.subject.diary', ['name' => $subject->title ?: ('#' . $subject->getKey())]),
            default => (string) __('chat.subject.other'),
        };
    }

    private function slug(Model $subject): string {
        $base = Str::slug($subject->getMorphClass() . '-' . $subject->getKey()) ?: 'kanal';
        $slug = $base;
        $i = 1;
        while (Channel::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
