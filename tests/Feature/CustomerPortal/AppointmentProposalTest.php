<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentProposalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Mail\AppointmentDecisionMail;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Sales\BookableService;
use App\Services\Appointments\AppointmentRequestService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** Ersatztermin vorschlagen und umbuchen (MVP-1287), Meldung neuer Anfragen (MVP-1286). */
final class AppointmentProposalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private User $lead;

    private AppointmentRequest $request;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create();
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $service = BookableService::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Wartungstermin', 'duration_minutes' => 90,
            'lead_time_hours' => 24, 'cancel_hours' => 24, 'buffer_minutes' => 15, 'active' => true,
        ]);
        $this->request = app(AppointmentRequestService::class)->requestFromPortal($service, $this->customer, $this->portalUser, CarbonImmutable::now()->addDays(3)->setTime(9, 0));
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorSetup::class);
    }

    private function localInput(CarbonImmutable $at): string {
        return $at->setTimezone(Tz::current())->format('Y-m-d\TH:i');
    }

    public function test_new_portal_request_notifies_the_lead(): void {
        $this->assertTrue($this->lead->notifications()->get()->contains(fn ($n): bool => ($n->data['event'] ?? null) === 'appointment.requested'));
    }

    public function test_proposal_is_mailed_and_accepted_in_the_portal(): void {
        Mail::fake();
        $proposed = CarbonImmutable::now()->addDays(5)->setTime(14, 0);

        $this->actingAs($this->lead)->post(route('appointments.propose', $this->request), ['start' => $this->localInput($proposed), 'note' => 'Vormittags sind wir ausgebucht.'])
            ->assertSessionHas('success');
        $fresh = $this->request->fresh();
        $this->assertSame(AppointmentRequestStatus::Proposed, $fresh->status);
        $this->assertSame(90, (int) $fresh->proposed_start_at->diffInMinutes($fresh->proposed_end_at));
        Mail::assertSent(AppointmentDecisionMail::class);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.appointments.index'))
            ->assertOk()->assertSee(route('customer.appointments.proposal.accept', $this->request), false);
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.appointments.proposal.accept', $this->request))
            ->assertSessionHas('success');

        $fresh = $this->request->fresh();
        $this->assertSame(AppointmentRequestStatus::Confirmed, $fresh->status);
        $this->assertSame($proposed->utc()->format('Y-m-d H:i'), $fresh->start_at->format('Y-m-d H:i'));
        $this->assertSame((int) $this->lead->id, (int) $fresh->decided_by);
        $this->assertSame(1, DiaryEntry::query()->count());
    }

    public function test_declining_the_proposal_cancels_and_foreign_customers_are_refused(): void {
        app(AppointmentRequestService::class)->proposeAlternative($this->request, $this->lead, CarbonImmutable::now()->addDays(5)->setTime(14, 0));
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($other);
        $stranger = User::factory()->kunde((int) $other->id, (int) $this->organization->id)->create();

        $this->actingAs($stranger, 'customer')->post(route('customer.appointments.proposal.accept', $this->request))
            ->assertSessionHas('error');
        $this->assertSame(AppointmentRequestStatus::Proposed, $this->request->fresh()->status);

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.appointments.proposal.decline', $this->request))
            ->assertSessionHas('success');
        $this->assertSame(AppointmentRequestStatus::Canceled, $this->request->fresh()->status);
        $this->assertSame(0, DiaryEntry::query()->count());
    }

    public function test_reschedule_moves_request_and_order(): void {
        Mail::fake();
        $entry = app(AppointmentRequestService::class)->confirm($this->request, $this->lead);
        $later = CarbonImmutable::now()->addDays(10)->setTime(11, 30);

        $this->actingAs($this->lead)->post(route('appointments.reschedule', $this->request), ['start' => $this->localInput($later)])
            ->assertSessionHas('success');

        $this->assertSame($later->utc()->format('Y-m-d H:i'), $this->request->fresh()->start_at->format('Y-m-d H:i'));
        $this->assertSame($later->utc()->format('Y-m-d H:i'), $entry->fresh()->start_at->format('Y-m-d H:i'));
        Mail::assertSent(AppointmentDecisionMail::class, fn (AppointmentDecisionMail $mail): bool => $mail->rescheduled);
    }
}
