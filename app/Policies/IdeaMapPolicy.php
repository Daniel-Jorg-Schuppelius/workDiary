<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\Ideas\IdeaShareRole;
use App\Enums\User\Permission as P;
use App\Models\{IdeaMap, User};
use App\Policies\Concerns\ChecksOwnership;

/**
 * Zugriffsregeln Ideenlandkarten (Feature 054, MVP-104/107) — die zentrale
 * Sicherheitskomponente des Moduls:
 *
 * - `private` ist Default; Inhaltszugriff (view/update/share/delete/export)
 *   löst AUSSCHLIESSLICH über Eigentümer + aktive Freigaben auf. Die
 *   Org-Zugehörigkeit allein gewährt keinen Zugriff.
 * - BEWUSST KEIN {@see \App\Policies\Concerns\HasAdminBypass}: Auch Admins
 *   sehen private Karteninhalte nicht. Für Aufbewahrung/Betrieb gibt es die
 *   getrennten Abilities `viewMeta`/`manageLifecycle` (nur Titel/Eigentümer/
 *   Status — nie Knoteninhalt), Recht `ideas.manageLifecycle`.
 * - Teamfreigaben werden BEIM ZUGRIFF gegen `team_user` aufgelöst — wer das
 *   Team verlässt, verliert den Zugriff sofort.
 * - Knoten-Zugriffe delegieren immer hierher (keine eigene Sichtbarkeit).
 */
class IdeaMapPolicy {
    use ChecksOwnership;

    public function viewAny(User $user): bool {
        return $user->can(P::IdeasViewAny->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IdeasCreate->value);
    }

    public function view(User $user, IdeaMap $map): bool {
        return $this->sharesOrganization($user, $map)
            && ($map->isOwnedBy($user) || $this->shareRole($user, $map) !== null);
    }

    /** Bearbeiten: Eigentümer oder Freigabe mit Editor-Rolle; nie auf archivierten Karten. */
    public function update(User $user, IdeaMap $map): bool {
        if (! $this->sharesOrganization($user, $map) || $map->isArchived()) {
            return false;
        }

        return $map->isOwnedBy($user) || $this->shareRole($user, $map) === IdeaShareRole::Editor;
    }

    /** Freigaben verwaltet ausschließlich der Eigentümer. */
    public function share(User $user, IdeaMap $map): bool {
        return $this->sharesOrganization($user, $map) && $map->isOwnedBy($user);
    }

    /** Archivieren/Löschen/Wiederherstellen: nur der Eigentümer. */
    public function delete(User $user, IdeaMap $map): bool {
        return $this->sharesOrganization($user, $map) && $map->isOwnedBy($user);
    }

    public function restore(User $user, IdeaMap $map): bool {
        return $this->sharesOrganization($user, $map) && $map->isOwnedBy($user);
    }

    /** Export (PDF/JSON, P6): nur der Eigentümer. */
    public function export(User $user, IdeaMap $map): bool {
        return $this->sharesOrganization($user, $map) && $map->isOwnedBy($user);
    }

    /**
     * Metadaten-Sicht für Org-Admins (Titel, Eigentümer, Status) — für
     * Aufbewahrung und Nutzer-Austritt. KEIN Knoteninhalt.
     */
    public function viewMeta(User $user, IdeaMap $map): bool {
        return $this->sharesOrganization($user, $map) && $user->can(P::IdeasManageLifecycle->value);
    }

    /** Eigentum auditierbar übertragen / fremde Karte archivieren (Austritt/Deaktivierung). */
    public function manageLifecycle(User $user, IdeaMap $map): bool {
        return $this->sharesOrganization($user, $map) && $user->can(P::IdeasManageLifecycle->value);
    }

    /** Aktive Freigabe-Rolle des Nutzers (direkte Personen- oder Team-Freigabe), sonst null. */
    private function shareRole(User $user, IdeaMap $map): ?IdeaShareRole {
        $share = $map->shares()
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->orWhereIn('team_id', $user->teams()->select('teams.id'));
            })
            ->orderByRaw("CASE WHEN role = 'editor' THEN 0 ELSE 1 END")
            ->first();

        return $share?->role;
    }
}
