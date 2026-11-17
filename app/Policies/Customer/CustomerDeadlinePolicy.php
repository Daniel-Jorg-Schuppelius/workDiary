<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlinePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Customer;

use App\Enums\User\Permission;
use App\Models\Customer\CustomerDeadline;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Services\Delegation\ActsForResolver;

/**
 * Kundenfristen (Feature 197). Sehen mit `customer.deadline.view` (oder
 * `…manage`), nur in der eigenen Organisation. Fristmerkmale und
 * Leistungsumfang pflegen sowie jede Frist bearbeiten mit
 * `customer.deadline.manage`; die zuständige Person — auch wer sie heute
 * vertritt ({@see ActsForResolver::actsFor()}) — bearbeitet ihre Fristen mit
 * dem Leserecht. Die Vertretung verleiht kein Recht: ohne Leserecht sieht auch
 * die zuständige Person nichts.
 */
class CustomerDeadlinePolicy {
    use HasAdminBypass;

    public function __construct(private readonly ActsForResolver $actsFor) {}

    public function viewAny(User $user): bool {
        return $user->can(Permission::CustomerDeadlineView->value) || $user->can(Permission::CustomerDeadlineManage->value);
    }

    public function view(User $user, CustomerDeadline $deadline): bool {
        return (int) $deadline->organization_id === (int) $user->organization_id && $this->viewAny($user);
    }

    /** Fristmerkmale, Leistungsumfang und Einstellungen der Kundenfristen pflegen. */
    public function manage(User $user): bool {
        return $user->can(Permission::CustomerDeadlineManage->value);
    }

    /** Erledigen, verlängern, vorangefordert setzen, entfallen lassen, wieder öffnen. */
    public function update(User $user, CustomerDeadline $deadline): bool {
        if (! $this->view($user, $deadline)) {
            return false;
        }

        return $this->manage($user) || $this->actsFor->actsFor($user, $deadline->responsible_user_id);
    }
}
