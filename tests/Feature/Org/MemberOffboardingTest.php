<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MemberOffboardingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Models\{Asset, AssetAssignment, TimeEntry};
use App\Models\Platform\{Organization, User};
use App\Models\Project\{Project, Task};
use App\Services\Org\UserOffboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mitarbeiter-Austritt (Feature 126, MVP-689 — Vollscan H1, Entscheid E4):
 * Regelweg Deaktivieren statt Löschen; Nachweise bleiben, Sitzungen/Tokens
 * enden, der Lizenzsitz wird frei; Hard-Delete nur ohne Nachweise.
 */
final class MemberOffboardingTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    private function member(): User {
        return User::factory()->user()->create(['organization_id' => $this->org->id]);
    }

    public function test_offboarding_today_deactivates_and_revokes_access(): void {
        $member = $this->member();
        DB::table('sessions')->insert([
            'id' => 'sess-' . $member->id,
            'user_id' => $member->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);
        $member->createToken('api');

        $this->actingAs($this->admin)
            ->post(route('org.members.offboard', $member), ['left_at' => now()->toDateString()])
            ->assertRedirect(route('org.members.index'));

        $member->refresh();
        $this->assertTrue($member->isDeactivated());
        $this->assertFalse($member->canLogin());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $member->id)->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $member->id)->count());
        $this->assertSame(1, $member->auditLogs()->where('event', 'user.offboarded')->count());
    }

    public function test_future_offboarding_is_scheduled_and_executed_by_the_command(): void {
        $member = $this->member();

        $this->actingAs($this->admin)
            ->post(route('org.members.offboard', $member), ['left_at' => now()->addDays(7)->toDateString()])
            ->assertRedirect();

        $this->assertFalse($member->fresh()?->isDeactivated());
        $this->assertSame(now()->addDays(7)->toDateString(), $member->fresh()?->left_at?->toDateString());

        // Am Stichtag vollzieht der Scheduler-Command.
        $this->travelTo(now()->addDays(7));
        $this->artisan('org:offboard-due')->assertSuccessful();
        $this->assertTrue($member->fresh()?->isDeactivated());
    }

    public function test_deactivated_member_frees_the_license_seat(): void {
        $member = $this->member();
        $before = $this->org->activeUserCount();

        app(UserOffboardingService::class)->execute($member, $this->admin);

        $this->assertSame($before - 1, $this->org->activeUserCount());
    }

    public function test_destroy_is_blocked_when_evidence_exists(): void {
        $member = $this->member();
        TimeEntry::query()->create([
            'organization_id' => $this->org->id,
            'user_id' => $member->id,
            'date' => now()->toDateString(),
            'minutes' => 60,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('org.members.destroy', $member))
            ->assertRedirect();

        $this->assertNotNull(User::query()->find($member->id));
        $this->assertNotNull(TimeEntry::query()->where('user_id', $member->id)->first());
    }

    public function test_destroy_without_evidence_still_works(): void {
        $member = $this->member();

        $this->actingAs($this->admin)
            ->delete(route('org.members.destroy', $member))
            ->assertRedirect(route('org.members.index'));

        $this->assertNull(User::query()->find($member->id));
    }

    public function test_offboarding_dialog_lists_what_the_member_still_holds(): void {
        // MVP-798 (P1-21): Stichtag wählbar statt fest „heute", dazu die Übergabeliste.
        $member = $this->member();
        $asset = Asset::factory()->create(['organization_id' => $this->org->id, 'name' => 'Bohrhammer 7']);
        AssetAssignment::factory()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $member->id,
        ]);
        $project = Project::factory()->create(['organization_id' => $this->org->id]);
        $open = Task::factory()->create(['project_id' => $project->id, 'created_by' => $this->admin->id, 'title' => 'Wartungsplan Halle 3']);
        $done = Task::factory()->done()->create(['project_id' => $project->id, 'created_by' => $this->admin->id, 'title' => 'Altlast erledigt']);
        $open->assignees()->attach($member->id);
        $done->assignees()->attach($member->id);

        $this->actingAs($this->admin)
            ->get(route('org.members.offboard.dialog', $member))
            ->assertOk()
            ->assertSee('name="left_at"', false)
            ->assertSee('Bohrhammer 7')
            ->assertSee('Wartungsplan Halle 3')
            ->assertDontSee('Altlast erledigt');
    }

    public function test_offboarding_dialog_requires_member_management(): void {
        $member = $this->member();

        $this->actingAs($this->member())
            ->get(route('org.members.offboard.dialog', $member))
            ->assertForbidden();
    }

    public function test_self_offboarding_is_rejected(): void {
        $this->actingAs($this->admin)
            ->post(route('org.members.offboard', $this->admin), ['left_at' => now()->toDateString()])
            ->assertRedirect();

        $this->assertFalse($this->admin->fresh()?->isDeactivated());
    }
}
