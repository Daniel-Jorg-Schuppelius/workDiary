<?php

/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserGroupControllerAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Access;

use App\Models\{AuditLog, User, UserGroup};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class UserGroupControllerAuditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->admin = User::factory()
            ->admin()
            ->create(['organization_id' => $this->organization->id]);
    }

    public function test_attach_and_detach_member_are_audited(): void {
        $group = UserGroup::create([
            'organization_id' => $this->organization->id,
            'name' => 'Service Team',
            'description' => 'Support group for audits',
            'color' => '#ff9900',
            'is_system' => false,
        ]);

        $member = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.access.groups.members.attach', $group), [
                'user_id' => $member->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'event' => 'user_group.member_added',
            'auditable_type' => UserGroup::class,
            'auditable_id' => $group->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.access.groups.members.detach', [$group, $member]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'event' => 'user_group.member_removed',
            'auditable_type' => UserGroup::class,
            'auditable_id' => $group->id,
        ]);

        $this->assertSame(
            2,
            AuditLog::query()
                ->where('organization_id', $this->organization->id)
                ->where('auditable_type', UserGroup::class)
                ->whereIn('event', ['user_group.member_added', 'user_group.member_removed'])
                ->count(),
        );
    }
}
