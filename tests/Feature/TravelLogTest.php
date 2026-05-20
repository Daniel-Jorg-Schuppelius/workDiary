<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\TimeEntry;
use App\Models\TravelLog;
use App\Models\User;
use App\Services\Travel\MileageRateResolver;
use App\Services\Travel\TravelLogService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use App\Enums\Travel\TravelLogVehicle;

class TravelLogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_renders(): void {
        $this->actingAs($this->user);
        $this->get(route('travel-logs.index'))->assertOk()->assertSee(__('Fahrtenbuch'));
    }

    public function test_store_creates_travel_log_and_paired_time_entry(): void {
        $this->actingAs($this->user);

        $start = CarbonImmutable::today()->setTime(8, 0);
        $end = $start->addHour();

        $this->post(route('travel-logs.store'), [
            'date' => $start->toDateString(),
            'started_at' => $start->format('Y-m-d\TH:i'),
            'ended_at' => $end->format('Y-m-d\TH:i'),
            'from_address' => 'Berlin',
            'to_address' => 'Potsdam',
            'distance_km' => 30,
            'vehicle' => TravelLogVehicle::Private_->value,
            'purpose' => 'Kundentermin',
            'round_trip' => '1',
            'reimbursable' => '1',
        ])->assertRedirect(route('travel-logs.index'));

        $log = TravelLog::query()->firstOrFail();
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertEquals(30.0, (float) $log->distance_km);
        // round_trip doubles for reimbursement: 30 * 2 * 0.30 = 18.00
        $this->assertEqualsWithDelta(18.0, (float) $log->reimbursement_total, 0.01);
        $this->assertSame(60, $log->duration_minutes);

        $entry = TimeEntry::query()->where('travel_log_id', $log->id)->firstOrFail();
        $this->assertSame(TimeEntryKind::Travel, $entry->kind);
        $this->assertSame(\App\Enums\TimeEntry\TimeEntryActivityType::Travel, $entry->activity_type);
        $this->assertSame(60, $entry->minutes);
    }

    public function test_store_without_timestamps_does_not_create_time_entry(): void {
        $this->actingAs($this->user);

        $this->post(route('travel-logs.store'), [
            'date' => CarbonImmutable::today()->toDateString(),
            'distance_km' => 10,
            'vehicle' => TravelLogVehicle::Private_->value,
            'purpose' => 'Botengang',
        ])->assertRedirect();

        $log = TravelLog::query()->firstOrFail();
        $this->assertSame(0, TimeEntry::query()->where('travel_log_id', $log->id)->count());
    }

    public function test_destroy_removes_paired_entry(): void {
        $this->actingAs($this->user);
        $service = app(TravelLogService::class);

        $start = CarbonImmutable::today()->setTime(9, 0);
        $log = $service->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $start->addMinutes(30),
            'distance_km' => 5,
            'vehicle' => TravelLogVehicle::Private_->value,
            'purpose' => 'Test',
        ]);

        $this->assertDatabaseHas('time_entries', ['travel_log_id' => $log->id]);

        $this->delete(route('travel-logs.destroy', $log))->assertRedirect();

        $this->assertDatabaseMissing('travel_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('time_entries', ['travel_log_id' => $log->id]);
    }

    public function test_user_cannot_edit_others_travel_log(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $log = TravelLog::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $other->id,
        ]);

        $this->actingAs($this->user);
        $this->get(route('travel-logs.edit', $log))->assertForbidden();
    }

    public function test_mileage_rate_resolver_uses_config(): void {
        config()->set('timesheet.travel.rates.private', 0.42);
        $resolver = app(MileageRateResolver::class);

        $this->assertSame(0.42, $resolver->rateFor(TravelLogVehicle::Private_->value));
    }

    public function test_csv_export_returns_download(): void {
        $this->actingAs($this->user);

        TravelLog::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => CarbonImmutable::today(),
            'distance_km' => 12.5,
            'vehicle' => TravelLogVehicle::Private_->value,
            'rate_per_km' => '0.3000',
            'from_address' => 'A',
            'to_address' => 'B',
        ]);

        $response = $this->get(route('travel-logs.export'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('12,50', $response->streamedContent());
    }

    public function test_index_uses_global_header_date_range_by_default(): void {
        $this->actingAs($this->user);

        // Header selects April 2026 — May entries must NOT be included.
        session()->put('ui.daterange.preset', 'custom');
        session()->put('ui.daterange.from', '2026-04-01');
        session()->put('ui.daterange.to', '2026-04-30');

        TravelLog::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => CarbonImmutable::create(2026, 4, 15),
            'distance_km' => 50,
            'reimbursement_total' => 21.0,
            'vehicle' => TravelLogVehicle::Private_->value,
            'from_address' => 'A',
            'to_address' => 'B',
        ]);
        TravelLog::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => CarbonImmutable::create(2026, 5, 4),
            'distance_km' => 999,
            'reimbursement_total' => 400.0,
            'vehicle' => TravelLogVehicle::Private_->value,
            'from_address' => 'C',
            'to_address' => 'D',
        ]);

        $response = $this->get(route('travel-logs.index'));
        $response->assertOk();
        $this->assertSame(50.0, (float) $response->viewData('totals')['distance_km']);
        $this->assertSame('2026-04-01', $response->viewData('from')->toDateString());
        $this->assertSame('2026-04-30', $response->viewData('to')->toDateString());
    }
}
