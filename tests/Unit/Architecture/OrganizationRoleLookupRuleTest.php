<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationRoleLookupRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Spatie löst Rollennamen mit Teams über `team_id IS NULL OR team_id = …`
 * ohne Ordnung auf; neben jeder Org-Rolle `admin` liegt die globale Zeile.
 * Rollen nach Namen daher nur über `App\Services\Org\OrganizationRoles`
 * (Vollscan 2026-10-21).
 */
class OrganizationRoleLookupRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERNS = [
        '/\bRole::(?:findOrCreate|findByName)\s*\(/' => 'Role::findOrCreate/findByName',
        '/->(?:assignRole|syncRoles)\s*\(\s*(?:[\'"]|UserRole::|\[\s*[\'"])/' => 'assignRole/syncRoles mit Rollennamen',
        '/->(?:role|withoutRole)\(\s*(?!\))(?![^;]*OrganizationRoles::matching)/' => 'role()/withoutRole() ohne OrganizationRoles::matching',
    ];

    public function test_rollen_nach_namen_nur_aus_der_organisation(): void {
        $violations = [];

        foreach ($this->filesUnder('app', '/\.php$/') as $path) {
            $source = (string) file_get_contents($path);
            foreach (self::PATTERNS as $pattern => $label) {
                if (preg_match($pattern, $source) === 1) {
                    $violations[] = str_replace($this->repoRoot() . '/', '', $path) . " ({$label})";
                }
            }
        }

        $this->assertSame([], $violations, sprintf(
            "Rolle über ihren Namen aufgelöst:\n  %s\nVergeben mit OrganizationRoles::named(), filtern mit OrganizationRoles::matching().",
            implode("\n  ", $violations),
        ));
    }
}
