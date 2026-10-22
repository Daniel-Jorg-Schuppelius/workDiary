<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionAreasAudit20261021Test.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\Location\LocationPendingTripStatus;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Location\LocationPendingTrip;
use App\Models\Platform\User;
use App\Models\Privacy\RetentionProposal;
use App\Plugins\Etsy\Models\EtsyReceipt;
use App\Plugins\Etsy\Services\EtsyRetentionPolicies;
use App\Services\Retention\RetentionScanService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Löschfristen aus dem Vollscan 2026-10-21 (li-6, lk-26): Standort-Vorschläge, Terminanfragen, Marktplatz-Käuferdaten. */
final class RetentionAreasAudit20261021Test extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_settled_location_suggestions_go_after_the_track_period(): void {
        config()->set('location.retention_days', 30);
        $old = $this->trip(LocationPendingTripStatus::Dismissed, now()->subDays(40));
        $fresh = $this->trip(LocationPendingTripStatus::Imported, now()->subDays(5));
        $open = $this->trip(LocationPendingTripStatus::Open, null);

        $service = app(RetentionScanService::class);
        $service->scan($this->organization);
        $proposals = RetentionProposal::query()->where('area', 'location_trip_suggestions')->get();
        $this->assertSame([$old->id], $proposals->pluck('subject_id')->map(intval(...))->all());

        $service->approve($proposals->firstOrFail(), $this->admin);
        $service->purge($proposals->firstOrFail()->fresh(), $this->admin);
        $this->assertDatabaseMissing('location_pending_trips', ['id' => $old->id]);
        $this->assertDatabaseHas('location_pending_trips', ['id' => $fresh->id]);
        $this->assertDatabaseHas('location_pending_trips', ['id' => $open->id]);
    }

    public function test_decided_appointment_requests_are_proposed_after_a_year(): void {
        $declined = $this->request(AppointmentRequestStatus::Declined, now()->subMonths(14));
        $recent = $this->request(AppointmentRequestStatus::Confirmed, now()->subMonths(2));
        $pending = $this->request(AppointmentRequestStatus::Requested, null);

        app(RetentionScanService::class)->scan($this->organization);

        $this->assertSame([$declined->id], RetentionProposal::query()->where('area', 'appointment_requests')->pluck('subject_id')->map(intval(...))->all());
        $this->assertNotNull($recent->fresh());
        $this->assertNotNull($pending->fresh());
    }

    public function test_marketplace_buyer_data_is_removed_but_the_order_stays(): void {
        $receipt = EtsyReceipt::query()->create([
            'organization_id' => $this->organization->id,
            'receipt_id' => 4711,
            'currency' => 'EUR',
            'total_gross' => '19.90',
            'buyer_external_id' => 'buyer-1',
            'buyer' => ['name' => 'Erika Muster', 'address' => 'Musterweg 1'],
            'raw' => ['buyer_email' => 'erika@example.test'],
            'ordered_at' => now()->subYears(11),
        ]);
        $policy = (new EtsyRetentionPolicies)->policies()[0];
        $this->assertSame('marketplace_buyers', $policy->area);
        $this->assertSame([$receipt->id], ($policy->overdueQuery)($this->organization, CarbonImmutable::now()->subYears(10))->pluck('id')->all());

        ($policy->purge)($receipt);

        $receipt->refresh();
        $this->assertNull($receipt->buyer);
        $this->assertNull($receipt->raw);
        $this->assertNull($receipt->buyer_external_id);
        $this->assertSame([], ($policy->overdueQuery)($this->organization, CarbonImmutable::now()->subYears(10))->pluck('id')->all(), 'Ein bereinigter Beleg wird nicht erneut vorgeschlagen.');
    }

    private function trip(LocationPendingTripStatus $status, ?\DateTimeInterface $resolvedAt): LocationPendingTrip {
        return LocationPendingTrip::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'from_lat' => 52.5, 'from_lng' => 13.4, 'to_lat' => 52.6, 'to_lng' => 13.5,
            'started_at' => now()->subDays(50), 'ended_at' => now()->subDays(50)->addHour(),
            'distance_km' => '12.30',
            'status' => $status,
            'resolved_at' => $resolvedAt,
        ]);
    }

    private function request(AppointmentRequestStatus $status, ?\DateTimeInterface $decidedAt): AppointmentRequest {
        return AppointmentRequest::query()->create([
            'organization_id' => $this->organization->id,
            'source' => AppointmentRequest::SOURCE_CALENDLY,
            'source_uri' => 'https://api.calendly.com/invitees/' . uniqid(),
            'status' => $status,
            'invitee_name' => 'Jane Doe',
            'service_label' => 'Erstberatung',
            'start_at' => CarbonImmutable::parse('2025-08-01T10:00:00Z'),
            'end_at' => CarbonImmutable::parse('2025-08-01T10:30:00Z'),
            'decided_at' => $decidedAt,
        ]);
    }
}
