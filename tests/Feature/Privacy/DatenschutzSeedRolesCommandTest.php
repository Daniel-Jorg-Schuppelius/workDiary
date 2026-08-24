<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatenschutzSeedRolesCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Services\Privacy\DataProtectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: datenschutz:seed-roles legt die per-Org-Rolle
 * `datenschutz` samt Permissions an (Backfill, idempotent).
 */
class DatenschutzSeedRolesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function role(): ?Role {
        return Role::query()
            ->where('name', DataProtectionPermissions::ROLE_DATENSCHUTZ)
            ->where(config('permission.column_names.team_foreign_key', 'team_id'), $this->organization->id)
            ->first();
    }

    public function test_seeds_the_datenschutz_role_for_the_organization(): void {
        $this->artisan('datenschutz:seed-roles', ['organization' => (string) $this->organization->id])
            ->expectsOutputToContain("Rolle 'datenschutz' + Permissions geseedet.")
            ->assertExitCode(0);

        $role = $this->role();
        $this->assertNotNull($role, 'Rolle datenschutz muss org-gescopt angelegt werden.');
        $this->assertGreaterThan(0, $role->permissions()->count(), 'Rolle braucht ihre Datenschutz-Permissions.');

        // Idempotent: zweiter Lauf legt nichts doppelt an.
        $this->artisan('datenschutz:seed-roles', ['organization' => (string) $this->organization->id])
            ->assertExitCode(0);
        $this->assertSame(1, Role::query()
            ->where('name', DataProtectionPermissions::ROLE_DATENSCHUTZ)
            ->where(config('permission.column_names.team_foreign_key', 'team_id'), $this->organization->id)
            ->count());
    }

    public function test_fails_for_an_unknown_organization(): void {
        $this->artisan('datenschutz:seed-roles', ['organization' => '999999'])
            ->expectsOutputToContain('Organisation #999999 nicht gefunden.')
            ->assertExitCode(1);
    }
}
