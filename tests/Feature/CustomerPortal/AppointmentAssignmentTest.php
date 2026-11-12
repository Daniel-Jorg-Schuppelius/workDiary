<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentAssignmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Customer\Customer;
use App\Models\Facility\Site;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\User;
use App\Models\Sales\{BookableService, Lead};
use App\Plugins\Calendly\CalendlyPlugin;
use App\Plugins\Calendly\Enums\CalendlyConnectionStatus;
use App\Plugins\Calendly\Models\CalendlyConnection;
use App\Plugins\Calendly\Services\{CalendlyAppointmentMatcher, CalendlyIngestService};
use App\Services\Appointments\{AppointmentRequestService, AppointmentSlotService};
use App\Services\Integration\InboxActionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1610 und MVP-1612 (d): Terminanfragen ohne Kundentreffer zuordnen
 * (Termin- und Integrations-Eingang), Gastgeber-Zuordnung für Calendly,
 * Leistungsarten bearbeiten, Standortbindung in Slot-Suche und Einsatzort.
 */
final class AppointmentAssignmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function calendlyBooking(string $uri, string $email = 'neu@example.com', ?array $memberships = null): AppointmentRequest {
        $invitee = [
            'uri' => $uri,
            'email' => $email,
            'name' => 'Nora Neukunde',
            'scheduled_event' => [
                'uri' => 'https://api.calendly.com/scheduled_events/e1',
                'name' => 'Erstberatung',
                'start_time' => CarbonImmutable::now()->addDays(3)->setTime(9, 0)->toIso8601String(),
                'end_time' => CarbonImmutable::now()->addDays(3)->setTime(9, 30)->toIso8601String(),
                'event_memberships' => $memberships ?? [],
            ],
        ];

        $request = app(CalendlyIngestService::class)->handlePayload($this->organization, ['event' => 'invitee.created', 'payload' => $invitee]);
        $this->assertInstanceOf(AppointmentRequest::class, $request);

        return $request;
    }

    public function test_unmatched_booking_is_a_visible_customer_case_and_assigning_sets_the_customer(): void {
        $request = $this->calendlyBooking('https://api.calendly.com/invitees/i1');
        $item = IntegrationInboxItem::query()->sole();
        $this->assertNull($item->group_key, 'Ohne Gruppenbuchung wäre der Fall im Eingang unsichtbar.');
        $this->assertSame('customers', $item->target_type);
        $this->assertSame(['name' => 'Nora Neukunde', 'email' => 'neu@example.com'], $item->mapped_snapshot);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        app(InboxActionService::class)->assignTo($item, $customer);

        $this->assertSame($customer->id, $request->refresh()->customer_id);
        $this->assertSame(IntegrationInboxStatus::ResolvedLinked, $item->refresh()->status);
    }

    public function test_creating_from_the_inbox_creates_the_customer_for_the_request(): void {
        $request = $this->calendlyBooking('https://api.calendly.com/invitees/i2');
        $this->actingAs($this->admin);

        $customer = app(InboxActionService::class)->createFromItem(IntegrationInboxItem::query()->sole());

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('Nora Neukunde', $customer->name);
        $this->assertSame($customer->id, $request->refresh()->customer_id);
    }

    public function test_appointment_inbox_assigns_a_lead_and_closes_the_inbox_case(): void {
        $request = $this->calendlyBooking('https://api.calendly.com/invitees/i3');
        $leadCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $lead = Lead::query()->create([
            'organization_id' => $this->organization->id,
            'company' => 'Beispiel Werkstatt',
            'source' => 'web',
            'status' => 'converted',
            'customer_id' => $leadCustomer->id,
        ]);

        $this->actingAs($this->admin)->get(route('appointments.index'))->assertOk()->assertSee(route('appointments.assign.form', $request), false);
        $this->actingAs($this->admin)->get(route('appointments.assign.form', $request))->assertOk()->assertSee('Beispiel Werkstatt');
        $this->actingAs($this->admin)
            ->post(route('appointments.assign', $request), ['lead' => $lead->sqid])
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame($lead->id, $request->lead_id);
        $this->assertSame($leadCustomer->id, $request->customer_id, 'Ein konvertierter Lead bringt seinen Kunden mit.');
        $this->assertSame(IntegrationInboxStatus::ResolvedLinked, IntegrationInboxItem::query()->sole()->status);

        $this->actingAs($this->admin)->post(route('appointments.assign', $request), [])->assertSessionHasErrors('customer');
    }

    public function test_decided_request_is_not_reassigned(): void {
        $request = $this->calendlyBooking('https://api.calendly.com/invitees/i4');
        $request->forceFill(['status' => AppointmentRequestStatus::Declined])->save();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->post(route('appointments.assign', $request), ['customer' => $customer->sqid])
            ->assertSessionHas('error');
        $this->assertNull($request->refresh()->customer_id);
    }

    public function test_mapped_calendly_host_receives_the_booking(): void {
        CalendlyConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'tok',
            'status' => CalendlyConnectionStatus::Active,
            'calendly_organization_uri' => 'https://api.calendly.com/organizations/o1',
            'calendly_user_uri' => 'https://api.calendly.com/users/u1',
        ]);
        $host = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'dispo@example.org']);
        $this->actingAs($this->admin)
            ->post(route('admin.calendly.hosts.store'), ['email' => 'Kalender@Example.org', 'user' => $host->sqid])
            ->assertSessionHas('success');
        $this->assertSame(1, ExternalReference::query()->forPlugin($this->organization, CalendlyPlugin::ID, CalendlyAppointmentMatcher::HOST_REFERENCE)->forExternalId('kalender@example.org')->count());

        $request = $this->calendlyBooking('https://api.calendly.com/invitees/i5', memberships: [['user_email' => 'kalender@example.org']]);
        $this->assertSame($host->id, $request->assigned_user_id);

        $this->actingAs($this->admin)->get(route('admin.calendly.index'))->assertOk()->assertSee('kalender@example.org');
    }

    public function test_calendly_decisions_follow_the_dispatch_right(): void {
        $request = $this->calendlyBooking('https://api.calendly.com/invitees/i6');
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->post(route('admin.calendly.requests.decline', $request))->assertForbidden();

        $dispatcher = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($dispatcher)->post(route('admin.calendly.requests.decline', $request))->assertSessionHas('success');
        $this->assertSame(AppointmentRequestStatus::Declined, $request->refresh()->status);
    }

    public function test_bookable_service_is_editable(): void {
        $service = BookableService::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Wartung', 'duration_minutes' => 60,
            'lead_time_hours' => 24, 'cancel_hours' => 24, 'buffer_minutes' => 15, 'active' => true,
        ]);

        $this->actingAs($this->admin)->get(route('appointments.index', ['tab' => 'services']))->assertOk()->assertSee(route('appointments.services.edit', $service), false);
        $this->actingAs($this->admin)->get(route('appointments.services.edit', $service))->assertOk()->assertSee('Wartung');
        $this->actingAs($this->admin)->put(route('appointments.services.update', $service), [
            'title' => 'Jahreswartung', 'duration_minutes' => 90, 'lead_time_hours' => 48,
            'cancel_hours' => 12, 'buffer_minutes' => 10,
        ])->assertRedirect(route('appointments.index', ['tab' => 'services']));

        $service->refresh();
        $this->assertSame(['Jahreswartung', 90, 48, 12, 10], [$service->title, $service->duration_minutes, $service->lead_time_hours, $service->cancel_hours, $service->buffer_minutes]);
    }

    public function test_site_bound_service_is_only_for_the_site_owner_and_sets_the_location(): void {
        $owner = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $site = Site::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $owner->id,
            'address_street' => 'Objektweg 7', 'address_zip' => '54321', 'address_city' => 'Objektstadt']);
        $service = BookableService::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Vor-Ort-Prüfung', 'duration_minutes' => 60,
            'lead_time_hours' => 0, 'cancel_hours' => 24, 'buffer_minutes' => 0, 'site_id' => $site->id, 'active' => true,
        ]);
        $start = CarbonImmutable::now()->addDays(2)->setTime(10, 0);

        $this->assertSame([], app(AppointmentSlotService::class)->slotsFor($service, $start->startOfDay(), customer: $other));
        $otherPortal = User::factory()->kunde((int) $other->id, (int) $this->organization->id)->create();
        try {
            app(AppointmentRequestService::class)->requestFromPortal($service, $other, $otherPortal, $start);
            $this->fail('Fremder Kunde konnte die objektgebundene Leistung anfragen.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('appointment.service.site_foreign'), $e->getMessage());
        }

        $ownerPortal = User::factory()->kunde((int) $owner->id, (int) $this->organization->id)->create();
        $request = app(AppointmentRequestService::class)->requestFromPortal($service, $owner, $ownerPortal, $start);
        $entry = app(AppointmentRequestService::class)->confirm($request, $this->admin);

        $this->assertSame(['Objektweg 7', '54321', 'Objektstadt'], [$entry->address_line, $entry->address_zip, $entry->address_city]);
    }
}
