<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetShowControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetStatus;
use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Protocol\ProtocolType;
use App\Enums\Timesheet\{TimesheetKind, TimesheetStatus};
use App\Enums\User\UserRole;
use App\Models\{Asset, Attachment, DiaryEntry, MaterialUsage, OpenIssue, Project, Protocol, Timesheet, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetShowControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_user_without_asset_permission_cannot_access_show(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('assets.show', $asset))
            ->assertForbidden();
    }

    public function test_teamleitung_can_view_asset_show_with_related_entries(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Asset Master',
            'asset_no' => 'AS-2026-7777',
            'status' => AssetStatus::Active->value,
        ]);

        $asset->audit('asset.statusChanged', [
            'from' => AssetStatus::Active->value,
            'to' => AssetStatus::Blocked->value,
            'actor_id' => $user->id,
        ]);

        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'title' => 'Auftrag am Asset',
        ]);

        Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => ProtocolType::Service->value,
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'created_by_user_id' => $user->id,
            'title' => 'Serviceprotokoll Asset',
        ]);

        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Asset Projekt',
        ]);

        $timesheet = Timesheet::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'kind' => TimesheetKind::Project->value,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Draft->value,
            'totals_minutes' => 0,
            'attendance_total_minutes' => 0,
            'entries_total_minutes' => 0,
            'untracked_minutes' => 0,
            'totals_material_net' => '0.00',
        ]);

        MaterialUsage::query()->create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'asset_id' => $asset->id,
            'description' => 'Filtereinsatz',
            'quantity' => '2.000',
            'unit' => 'Stk.',
            'unit_price' => '9.5000',
            'tax_rate' => '19.00',
            'line_total_net' => '19.00',
        ]);

        OpenIssue::query()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'source_type' => OpenIssueSource::ProtocolDefect->value,
            'source_ref_id' => null,
            'title' => 'Blocker Defekt',
            'description' => null,
            'category' => 'asset',
            'severity' => OpenIssueSeverity::Critical->value,
            'status' => OpenIssueStatus::Blocked->value,
            'assignee_user_id' => $user->id,
            'due_at' => now()->addDay(),
            'visibility' => OpenIssueVisibility::Internal->value,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closed_reason' => null,
            'created_by_user_id' => $user->id,
        ]);

        Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => Asset::class,
            'attachable_id' => $asset->id,
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => 'attachments/assets/spec-sheet.pdf',
            'original_name' => 'spec-sheet.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ]);

        $this->actingAs($user)
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSeeText('Asset Master')
            ->assertSeeText('Aufträge (1)')
            ->assertSeeText('Protokolle (1)')
            ->assertSeeText('Materialeinsatz (1)')
            ->assertSeeText('Anhänge (1)')
            ->assertSeeText('Timeline')
            ->assertSeeText('Status geändert')
            ->assertSeeText('Asset gesperrt')
            ->assertSeeText('Offene Issues: 1')
            ->assertSeeText('Kritisch: 1')
            ->assertSeeText('Defektprotokolle: 0')
            ->assertSeeText('Auftrag am Asset')
            ->assertSeeText('Serviceprotokoll Asset')
            ->assertSeeText('Filtereinsatz')
            ->assertSeeText('spec-sheet.pdf');
    }

    public function test_teamleitung_can_unblock_blocked_asset(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => AssetStatus::Blocked->value,
        ]);

        $this->actingAs($user)
            ->post(route('assets.unblock', $asset))
            ->assertRedirect(route('assets.show', $asset));

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Active->value,
        ]);
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);

        $orgRole = Role::query()
            ->where('name', $role)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }
}
