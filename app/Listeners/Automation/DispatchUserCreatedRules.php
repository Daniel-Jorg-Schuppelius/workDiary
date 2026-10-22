<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchUserCreatedRules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Automation;

use App\Automation\RuleEngine;
use App\Automation\Triggers\UserCreatedTrigger;
use App\Events\Platform\UserCreated;
use App\Models\Platform\User;

/** Automationsregeln auf „Mitarbeitende angelegt“ (MVP-1273). */
final class DispatchUserCreatedRules {
    public function __construct(private readonly RuleEngine $engine) {}

    public function handle(UserCreated $event): void {
        $user = User::query()->withoutGlobalScopes()->where('organization_id', $event->organizationId)->find($event->userId);
        if ($user === null || $user->isCustomer()) {
            return;
        }

        $this->engine->dispatch(UserCreatedTrigger::KEY, $user, $event->organizationId);
    }
}
