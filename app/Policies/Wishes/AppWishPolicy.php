<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWishPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Wishes;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Wishes\AppWish;

/**
 * Wünsche an die App (Feature 206, MVP-1658/1659). Jedes Mitglied äußert
 * Wünsche, sieht die der eigenen Organisation und stimmt über die anderer ab;
 * den Text ändert nur die verfassende Person, solange der Wunsch neu ist.
 * Status, Antwort und Weitergabe an den Plattformbetrieb brauchen
 * `wishes.manage`. Bewusst ohne pauschalen Admin-Durchgriff: auch ein
 * Org-Admin ändert keinen fremden Text und stimmt nicht für den eigenen
 * Wunsch. Die Plattformsicht prüft der Controller (Plattform-Betreiber).
 */
class AppWishPolicy {
    public function viewAny(User $user): bool {
        return $user->organization_id !== null;
    }

    public function create(User $user): bool {
        return $user->organization_id !== null;
    }

    public function view(User $user, AppWish $wish): bool {
        return $this->sameOrganization($user, $wish);
    }

    public function update(User $user, AppWish $wish): bool {
        return $this->sameOrganization($user, $wish)
            && (int) $wish->created_by === (int) $user->id
            && $wish->isEditableByAuthor();
    }

    /** Eine Stimme für fremde, noch offene Wünsche der eigenen Organisation. */
    public function vote(User $user, AppWish $wish): bool {
        return $this->sameOrganization($user, $wish)
            && (int) $wish->created_by !== (int) $user->id
            && $wish->status->isOpen();
    }

    /** Status setzen, antworten, weitergeben. */
    public function manage(User $user): bool {
        return $user->isAdmin() || $user->can(Permission::WishesManage->value);
    }

    public function answer(User $user, AppWish $wish): bool {
        return $this->sameOrganization($user, $wish) && $this->manage($user);
    }

    /** Weitergabe an den Plattformbetrieb (MVP-1659): einmal je Wunsch. */
    public function forward(User $user, AppWish $wish): bool {
        return $this->sameOrganization($user, $wish) && $this->manage($user) && ! $wish->isForwarded();
    }

    private function sameOrganization(User $user, AppWish $wish): bool {
        return (int) $wish->organization_id === (int) $user->organization_id;
    }
}
