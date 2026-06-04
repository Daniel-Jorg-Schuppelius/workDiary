<?php
/*
 * Created on   : Wed May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserRoleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Enums;

use App\Enums\User\UserRole;
use Tests\TestCase;

final class UserRoleTest extends TestCase {
    public function test_values_contain_all_seedable_roles(): void {
        $values = UserRole::values();

        // Reihenfolge im Enum spiegelt die Sortierung in der Admin-UI wider
        // und ist daher Teil der \u00f6ffentlichen Vertragsfl\u00e4che.
        $this->assertSame([
            'admin',
            'geschaeftsfuehrung',
            'personalverwaltung',
            'teamleitung',
            'buchhaltung',
            'user',
            'aussendienst',
            'callcenter',
            'support',
            'training_manager',
            'kunde',
        ], $values);
    }

    public function test_labels_are_non_empty(): void {
        foreach (UserRole::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }

    public function test_try_from_unknown_returns_null(): void {
        $this->assertNull(UserRole::tryFrom('xxx'));
    }
}
