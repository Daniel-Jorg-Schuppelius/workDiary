<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RestoreTestRegisterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\RestoreTestResult;
use App\Models\{RestoreTest, User};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreTestRegisterTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_create_modal_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('admin.backup.restore-tests.create'))
            ->assertForbidden();
    }

    public function test_create_modal_renders_for_admin(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.backup.restore-tests.create'))
            ->assertOk()
            ->assertSee(__('backup.title.log_restore_test'));
    }

    public function test_store_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('admin.backup.restore-tests.store'), [
                'source' => 'nightly',
                'tested_on' => CarbonImmutable::now()->toDateString(),
                'result' => RestoreTestResult::Passed->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('restore_tests', 0);
    }

    public function test_admin_can_log_restore_test_with_result_and_due_date(): void {
        $admin = User::factory()->admin()->create();
        $testedOn = CarbonImmutable::now()->subDay()->toDateString();
        $nextDue = CarbonImmutable::now()->addDays(180)->toDateString();

        $this->actingAs($admin)
            ->post(route('admin.backup.restore-tests.store'), [
                'source' => 'offsite',
                'tested_on' => $testedOn,
                'result' => RestoreTestResult::Partial->value,
                'scope' => 'DB only',
                'restored_size_bytes' => 4096,
                'duration_minutes' => 42,
                'notes' => 'Storage partiell.',
                'next_due_on' => $nextDue,
            ])
            ->assertRedirect(route('admin.backup.status'));

        $this->assertDatabaseHas('restore_tests', [
            'source' => 'offsite',
            'result' => RestoreTestResult::Partial->value,
            'scope' => 'DB only',
            'restored_size_bytes' => 4096,
            'duration_minutes' => 42,
            'performed_by_user_id' => $admin->id,
        ]);

        $test = RestoreTest::query()->firstOrFail();
        $this->assertSame($nextDue, $test->next_due_on?->toDateString());
        $this->assertSame(RestoreTestResult::Partial, $test->result);
    }

    public function test_store_validates_required_fields_and_future_date(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.backup.restore-tests.store'), [
                'source' => '',
                'tested_on' => CarbonImmutable::now()->addYear()->toDateString(), // in der Zukunft
                'result' => 'bogus',
            ])
            ->assertSessionHasErrors(['source', 'tested_on', 'result']);

        $this->assertDatabaseCount('restore_tests', 0);
    }
}
