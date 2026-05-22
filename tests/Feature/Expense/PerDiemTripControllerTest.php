<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Expense\PerDiemTripStatus;
use App\Models\ExpenseCategory;
use App\Models\PerDiemTrip;
use App\Models\User;
use Database\Seeders\PerDiemRateSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class PerDiemTripControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(PerDiemRateSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        ExpenseCategory::factory()->create([
            'organization_id' => $this->organization->id,
            'slug' => ExpenseCategory::SLUG_MEALS,
            'label' => 'Verpflegung',
        ]);
    }

    public function test_index_renders(): void {
        $this->actingAs($this->user);
        $this->get(route('per-diem-trips.index'))->assertOk();
    }

    public function test_store_creates_trip_with_days(): void {
        $this->actingAs($this->user);

        $response = $this->post(route('per-diem-trips.store'), [
            'country' => 'DE',
            'location' => 'Frankfurt',
            'purpose' => 'Workshop',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-12T18:00',
            'accommodation_provided' => 0,
            'notes' => '',
        ]);

        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();
        $response->assertRedirect(route('per-diem-trips.show', $trip));
        $this->assertSame(3, $trip->days()->count());
        $this->assertEqualsWithDelta(56.0, (float) $trip->totalAmount(), 0.01);
    }

    public function test_convert_creates_expense_and_sets_status(): void {
        $this->actingAs($this->user);
        $this->post(route('per-diem-trips.store'), [
            'country' => 'DE',
            'location' => 'Berlin',
            'purpose' => 'Konferenz',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-11T18:00',
            'accommodation_provided' => 0,
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();

        $this->post(route('per-diem-trips.convert', $trip))->assertRedirect(route('expenses.index'));
        $trip->refresh();
        $this->assertSame(PerDiemTripStatus::Converted, $trip->status);
        $this->assertNotNull($trip->expense_id);
        $this->assertSame(ExpenseStatus::Pending->value, $trip->expense->status->value);
    }

    public function test_destroy_only_for_draft(): void {
        $this->actingAs($this->user);
        $this->post(route('per-diem-trips.store'), [
            'country' => 'DE',
            'location' => 'München',
            'purpose' => 'Meeting',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-11T18:00',
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();
        $this->delete(route('per-diem-trips.destroy', $trip))->assertRedirect();
        $this->assertSame(0, PerDiemTrip::query()->count());
    }

    public function test_pdf_download_returns_attachment(): void {
        $this->actingAs($this->user);
        $this->post(route('per-diem-trips.store'), [
            'country' => 'DE',
            'location' => 'Hamburg',
            'purpose' => 'Audit',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-12T18:00',
            'accommodation_provided' => 0,
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();

        $response = $this->get(route('per-diem-trips.pdf', $trip));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $disposition = $response->headers->get('content-disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('verpflegungspauschale-2025-03-10-hamburg', $disposition);
        $this->assertStringStartsWith('%PDF', $response->getContent() ?: '');
    }

    public function test_pdf_forbidden_for_other_user(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->user);
        $this->post(route('per-diem-trips.store'), [
            'country' => 'DE',
            'location' => 'Köln',
            'purpose' => 'Schulung',
            'started_at' => '2025-03-10T08:00',
            'ended_at' => '2025-03-11T18:00',
        ]);
        $trip = PerDiemTrip::query()->where('user_id', $this->user->id)->firstOrFail();

        $this->actingAs($other);
        $this->get(route('per-diem-trips.pdf', $trip))->assertForbidden();
    }
}
