<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationRulePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Benachrichtigungsregeln (MVP-018): Admin verwaltet (HasAdminBypass),
 * Teamleitung liest (notificationRule.viewAny via Seeder-Matrix).
 */
class NotificationRulePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::NotificationRuleViewAny,
        'update' => P::NotificationRuleUpdate,
    ];
}
