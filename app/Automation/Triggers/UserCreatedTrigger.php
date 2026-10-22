<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserCreatedTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Automation\Triggers;

use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

/** Mitarbeitende Person angelegt (MVP-1273); Bedingungen sehen auch die Rollen. */
final class UserCreatedTrigger implements RuleTrigger {
    public const KEY = 'user.created';

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('automation.trigger.user_created');
    }

    /** @return array<string, mixed> */
    public function exampleConditions(): array {
        return ['all' => [['field' => 'role', 'op' => 'in', 'value' => ['user', 'teamleitung']]]];
    }

    /** @return array<string, mixed> */
    public function context(Model $subject): array {
        if (! $subject instanceof User) {
            return $subject->toArray();
        }

        $roles = $subject->roles()->orderBy('name')->pluck('name')->values()->all();

        return [
            'id' => (int) $subject->id,
            'name' => (string) $subject->name,
            'email' => (string) $subject->email,
            'personnel_number' => $subject->personnel_number,
            'role' => $roles[0] ?? null,
            'roles' => implode(',', $roles),
        ];
    }
}
