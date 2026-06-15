<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{DiaryEntry, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class DiaryEntryPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && ($this->owns($user, $entry) || $user->can(Permission::DiaryViewAny->value));
    }

    public function update(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can(Permission::DiaryUpdate->value)
                )
            );
    }

    public function delete(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can(Permission::DiaryDelete->value)
                )
            );
    }

    public function archive(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can(Permission::DiaryUpdate->value)
                )
            );
    }

    public function accept(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderAccept);
    }

    public function start(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function pause(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function resume(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function complete(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderComplete);
    }

    public function handover(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderHandover);
    }

    public function markInvoiced(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && $user->can(Permission::OrderMarkInvoiced->value);
    }

    public function cancel(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderCancel);
    }

    private function canAct(User $user, DiaryEntry $entry, Permission $permission): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || (int) $entry->assigned_user_id === (int) $user->id
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can($permission->value)
                )
            );
    }
}
