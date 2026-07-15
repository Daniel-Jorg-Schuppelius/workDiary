<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas;

use App\Enums\Ideas\{IdeaMapVisibility, IdeaShareRole};
use App\Enums\Notification\NotificationEvent;
use App\Models\{IdeaMap, IdeaMapShare, Organization, Team, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lebenszyklus einer Ideenlandkarte (Feature 054, MVP-104/105): Anlage mit
 * Wurzelknoten-Invariante (genau einer je Karte), Umbenennen, Archivieren und
 * die auditierte Eigentumsübertragung (auch durch `manageLifecycle`-Admins
 * ohne Inhaltszugriff, z. B. bei Nutzer-Austritt).
 */
class IdeaMapService {
    /**
     * Legt Karte + Wurzelknoten transaktional an (Karte ist ab Anlage nutzbar).
     *
     * @param  array{customer_id?: int|null, project_id?: int|null, diary_entry_id?: int|null}  $context
     */
    public function create(Organization $organization, User $owner, string $title, ?string $description = null, array $context = []): IdeaMap {
        return DB::transaction(function () use ($organization, $owner, $title, $description, $context): IdeaMap {
            $map = IdeaMap::query()->create([
                'organization_id' => $organization->id,
                'created_by' => $owner->id,
                'owner_user_id' => $owner->id,
                'title' => $title,
                'description' => $description,
                'visibility' => IdeaMapVisibility::Private->value,
                'customer_id' => $context['customer_id'] ?? null,
                'project_id' => $context['project_id'] ?? null,
                'diary_entry_id' => $context['diary_entry_id'] ?? null,
            ]);

            $map->nodes()->create([
                'organization_id' => $organization->id,
                'is_root' => true,
                'title' => $title,
                'color' => \App\Enums\Ideas\IdeaNodeColor::Default->value,
                'sort_order' => 0,
                'created_by' => $owner->id,
            ]);

            return $map;
        });
    }

    /** @param array{customer_id?: int|null, project_id?: int|null, diary_entry_id?: int|null} $context */
    public function rename(IdeaMap $map, string $title, ?string $description = null, array $context = []): IdeaMap {
        $map->fill([
            'title' => $title,
            'description' => $description,
            'customer_id' => $context['customer_id'] ?? $map->customer_id,
            'project_id' => $context['project_id'] ?? $map->project_id,
        ])->save();

        return $map;
    }

    /** Archivieren: lesbar, aber nicht mehr bearbeitbar (Editor-API lehnt Mutationen ab). */
    public function archive(IdeaMap $map): IdeaMap {
        $map->forceFill(['archived_at' => Carbon::now()])->save();
        $map->audit('idea_map.archived');

        return $map;
    }

    public function unarchive(IdeaMap $map): IdeaMap {
        $map->forceFill(['archived_at' => null])->save();
        $map->audit('idea_map.unarchived');

        return $map;
    }

    /**
     * Gibt die Karte für eine Person frei (MVP-107). Idempotent je Person;
     * Benachrichtigung trägt bewusst NUR Titel + Link — die Policy greift
     * beim Klick.
     */
    public function shareWithUser(IdeaMap $map, User $user, IdeaShareRole $role, User $actor): IdeaMapShare {
        $share = DB::transaction(function () use ($map, $user, $role, $actor): IdeaMapShare {
            /** @var IdeaMapShare $share */
            $share = $map->shares()->firstOrNew(['user_id' => $user->id, 'team_id' => null]);
            $share->fill([
                'organization_id' => $map->organization_id,
                'role' => $role->value,
                'created_by' => $actor->id,
            ])->save();
            $this->syncVisibility($map);

            return $share;
        });

        $map->audit('idea_map.share_granted', ['user_id' => $user->id, 'role' => $role->value]);
        $this->notifyShared($map, $user, $actor);

        return $share;
    }

    /** Gibt die Karte für ein Team frei; Mitgliedschaft wird beim Zugriff aufgelöst. */
    public function shareWithTeam(IdeaMap $map, Team $team, IdeaShareRole $role, User $actor): IdeaMapShare {
        $share = DB::transaction(function () use ($map, $team, $role, $actor): IdeaMapShare {
            /** @var IdeaMapShare $share */
            $share = $map->shares()->firstOrNew(['team_id' => $team->id, 'user_id' => null]);
            $share->fill([
                'organization_id' => $map->organization_id,
                'role' => $role->value,
                'created_by' => $actor->id,
            ])->save();
            $this->syncVisibility($map);

            return $share;
        });

        $map->audit('idea_map.share_granted', ['team_id' => $team->id, 'role' => $role->value]);
        foreach ($team->members()->where('users.id', '!=', $map->owner_user_id)->get() as $member) {
            $this->notifyShared($map, $member, $actor);
        }

        return $share;
    }

    /** Entzieht eine Freigabe; die letzte Freigabe setzt die Karte zurück auf privat. */
    public function revokeShare(IdeaMap $map, IdeaMapShare $share): void {
        DB::transaction(function () use ($map, $share): void {
            $share->delete();
            $this->syncVisibility($map);
        });

        $map->audit('idea_map.share_revoked', [
            'user_id' => $share->user_id,
            'team_id' => $share->team_id,
        ]);
    }

    /** Invariante MVP-107: `shared` genau dann, wenn mindestens eine aktive Freigabe existiert. */
    private function syncVisibility(IdeaMap $map): void {
        $visibility = $map->shares()->exists() ? IdeaMapVisibility::Shared : IdeaMapVisibility::Private;
        if ($map->visibility !== $visibility) {
            $map->forceFill(['visibility' => $visibility->value])->save();
        }
    }

    private function notifyShared(IdeaMap $map, User $recipient, User $actor): void {
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::IdeaMapShared,
            $map,
            $recipient,
            [
                'title' => (string) $map->title,
                'message' => (string) __('ideas.notification.shared', ['actor' => $actor->name]),
                'message_key' => 'ideas.notification.shared',
                'message_params' => ['actor' => $actor->name],
                'url' => route('ideas.show', $map),
            ],
        );
    }

    /**
     * Auditiertes Sonderereignis: Eigentum übertragen (Eigentümer selbst oder
     * `manageLifecycle`-Admin bei Austritt/Deaktivierung — ohne Inhaltszugriff).
     */
    public function transferOwnership(IdeaMap $map, User $newOwner, User $actor): IdeaMap {
        $previousOwnerId = (int) $map->owner_user_id;
        $map->forceFill(['owner_user_id' => $newOwner->id])->save();
        $map->audit('idea_map.ownership_transferred', [
            'from_user_id' => $previousOwnerId,
            'to_user_id' => (int) $newOwner->id,
            'actor_user_id' => (int) $actor->id,
        ]);

        return $map;
    }
}
