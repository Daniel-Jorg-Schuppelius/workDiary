<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReminderServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Reminders;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Expense\PerDiemTripStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PerDiemTrip;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ReminderServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    private ExpenseCategory $category;

    private ReminderService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
        $this->service = app(ReminderService::class);
    }

    public function test_no_reminders_for_clean_user(): void {
        $this->assertSame([], $this->service->for($this->user));
    }

    public function test_old_draft_expense_triggers_reminder(): void {
        $old = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'status' => ExpenseStatus::Draft,
            'created_at' => CarbonImmutable::now()->subDays(10),
        ]);
        // junger Draft soll nicht zählen
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'status' => ExpenseStatus::Draft,
            'created_at' => CarbonImmutable::now()->subDays(2),
        ]);

        $items = $this->service->for($this->user);
        $keys = array_map(fn($i) => $i->key, $items);
        $this->assertContains('expense_drafts', $keys);
        $item = collect($items)->firstWhere('key', 'expense_drafts');
        $this->assertSame(1, $item->count);
    }

    public function test_old_draft_trip_triggers_reminder(): void {
        PerDiemTrip::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'status' => PerDiemTripStatus::Draft,
            'created_at' => CarbonImmutable::now()->subDays(20),
        ]);

        $items = $this->service->for($this->user);
        $keys = array_map(fn($i) => $i->key, $items);
        $this->assertContains('per_diem_drafts', $keys);
    }

    public function test_admin_sees_pending_approval_reminders(): void {
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'expense_category_id' => $this->category->id,
            'status' => ExpenseStatus::Pending,
            'updated_at' => CarbonImmutable::now()->subDays(5),
        ]);

        $items = $this->service->for($this->admin);
        $keys = array_map(fn($i) => $i->key, $items);
        $this->assertContains('expense_approvals_due', $keys);
    }

    public function test_user_does_not_see_admin_reminders(): void {
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'expense_category_id' => $this->category->id,
            'status' => ExpenseStatus::Pending,
            'updated_at' => CarbonImmutable::now()->subDays(5),
        ]);

        $items = $this->service->for($this->user);
        $keys = array_map(fn($i) => $i->key, $items);
        $this->assertNotContains('expense_approvals_due', $keys);
    }
}
