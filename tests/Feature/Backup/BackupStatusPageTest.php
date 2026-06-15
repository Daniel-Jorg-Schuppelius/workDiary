<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupStatusPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\RestoreTestResult;
use App\Models\{BackupHeartbeat, RestoreTest, User};
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupStatusPageTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        config()->set('backup.heartbeat_freshness_hours', 26);
        config()->set('backup.restore_test_overdue_days', 180);
    }

    public function test_status_requires_authentication(): void {
        $this->get(route('admin.backup.status'))->assertRedirect(route('login'));
    }

    public function test_status_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.backup.status'))->assertForbidden();
    }

    public function test_status_renders_for_admin(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertSee(__('backup.title.status'));
    }

    public function test_no_heartbeat_shows_no_backup_warning(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertSee(__('backup.warn.no_heartbeat_title'));
    }

    public function test_fresh_heartbeat_shows_fresh_badge_not_overdue(): void {
        $admin = User::factory()->admin()->create();
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(2),
            'size_bytes' => 12_345_678,
            'manifest_hash' => str_repeat('a', 64),
            'source' => 'nightly',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertSee('nightly')
            ->assertSee(__('backup.badge.fresh'))
            ->assertDontSee(__('backup.warn.overdue_title'));
    }

    public function test_stale_heartbeat_shows_overdue_warning(): void {
        $admin = User::factory()->admin()->create();
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(48),
            'size_bytes' => 100,
            'source' => 'nightly',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertSee(__('backup.warn.overdue_title'))
            ->assertSee(__('backup.badge.overdue'));
    }

    public function test_no_passed_restore_test_is_overdue(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertSee(__('backup.warn.restore_overdue_title'));
    }

    public function test_recent_passed_restore_test_not_overdue(): void {
        $admin = User::factory()->admin()->create();
        RestoreTest::factory()->create([
            'result' => RestoreTestResult::Passed,
            'tested_on' => CarbonImmutable::now()->subDays(10),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertDontSee(__('backup.warn.restore_overdue_title'));
    }

    public function test_old_passed_restore_test_is_overdue(): void {
        $admin = User::factory()->admin()->create();
        RestoreTest::factory()->create([
            'result' => RestoreTestResult::Passed,
            'tested_on' => CarbonImmutable::now()->subDays(365),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertSee(__('backup.warn.restore_overdue_title'));
    }

    public function test_failed_restore_test_does_not_count_as_passed(): void {
        $admin = User::factory()->admin()->create();
        // Ein junger, aber FEHLGESCHLAGENER Test darf die Überfälligkeit nicht aufheben.
        RestoreTest::factory()->failed()->create([
            'tested_on' => CarbonImmutable::now()->subDays(1),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.backup.status'))
            ->assertOk()
            ->assertSee(__('backup.warn.restore_overdue_title'));
    }
}
