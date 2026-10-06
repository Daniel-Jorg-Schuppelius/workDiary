<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyOutboundTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Calendly;

use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\Diary\Status;
use App\Models\Calendar\AppointmentRequest;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\{PluginError, User};
use App\Plugins\Calendly\Enums\CalendlyConnectionStatus;
use App\Plugins\Calendly\Models\CalendlyConnection;
use App\Plugins\Calendly\Services\{CalendlyConfirmService, CalendlyIngestService, CalendlyOutboundService};
use App\Services\Diary\OrderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 095, P5 (Outbound): Einmal-Buchungslink über `POST
 * /one_off_event_types` aus dem Admin-Panel und Cancel-Sync — app-seitiger
 * Storno eines bestätigten Calendly-Termins wird best effort gegen Calendly
 * abgesagt (`POST /scheduled_events/{uuid}/cancellation`), ohne den App-Storno
 * je zu blockieren. Kein Echo: Calendly-seitige Absagen lösen keinen
 * Outbound-Call aus.
 */
final class CalendlyOutboundTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const EVENT_UUID = 'EV1';

    private const INVITEE_URI = 'https://api.calendly.com/scheduled_events/EV1/invitees/INV1';

    private User $admin;

    private CalendlyConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();

        $this->connection = CalendlyConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'tok',
            'status' => CalendlyConnectionStatus::Active,
            'calendly_organization_uri' => 'https://api.calendly.com/organizations/o1',
            'calendly_user_uri' => 'https://api.calendly.com/users/u1',
        ]);
    }

    /** Bestätigter Terminwunsch inkl. Dispositionseintrag (P4-Pfad). */
    private function confirmedRequest(): AppointmentRequest {
        $request = AppointmentRequest::query()->create([
            'organization_id' => $this->organization->id,
            'source' => AppointmentRequest::SOURCE_CALENDLY,
            'source_uri' => self::INVITEE_URI,
            'status' => AppointmentRequestStatus::Requested,
            'invitee_name' => 'Jane Doe',
            'service_label' => 'Erstberatung',
            'start_at' => CarbonImmutable::parse('2026-08-01T10:00:00Z'),
            'end_at' => CarbonImmutable::parse('2026-08-01T10:30:00Z'),
        ]);
        $this->assertNotNull(app(CalendlyConfirmService::class)->confirm($request, $this->admin));

        return $request->refresh();
    }

    public function test_admin_panel_renders_booking_link_form(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.calendly.index'))
            ->assertOk()
            ->assertSee(route('admin.calendly.booking-link'))
            ->assertSee(__('Einmal-Buchungslink'));
    }

    /** Offene Terminwünsche blättern — früher endete die Liste still nach 100 Einträgen. */
    public function test_open_requests_page_in_start_order(): void {
        foreach (range(1, 101) as $i) {
            AppointmentRequest::query()->create([
                'organization_id' => $this->organization->id,
                'source' => AppointmentRequest::SOURCE_CALENDLY,
                'source_uri' => self::INVITEE_URI . '-' . $i,
                'status' => AppointmentRequestStatus::Requested,
                'invitee_name' => sprintf('Gast %03d', $i),
                'service_label' => 'Erstberatung',
                'start_at' => CarbonImmutable::parse('2026-08-01T10:00:00Z')->addHours($i),
                'end_at' => CarbonImmutable::parse('2026-08-01T10:30:00Z')->addHours($i),
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.calendly.index'))->assertOk();
        $this->assertSame(101, $first->viewData('requests')->total());
        $this->assertCount(25, $first->viewData('requests')->items());
        $first->assertSee('Gast 001')->assertSee(__('Einmal-Buchungslink'));

        $last = $this->actingAs($this->admin)->get(route('admin.calendly.index', ['page' => 5]))->assertOk();
        $this->assertSame(['Gast 101'], $last->viewData('requests')->pluck('invitee_name')->all());
        $last->assertSee(route('admin.calendly.requests.confirm', $last->viewData('requests')->items()[0]));
    }

    public function test_booking_link_is_created_and_flashed(): void {
        $fake = FakePluginHttp::fake([
            'https://api.calendly.com/one_off_event_types*' => FakePluginHttp::response([
                'resource' => ['scheduling_url' => 'https://calendly.com/d/one-off-123'],
            ], 201),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.calendly.booking-link'), ['name' => 'Erstberatung', 'duration' => 45, 'days' => 14])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('calendly_booking_url', 'https://calendly.com/d/one-off-123');

        $fake->assertSent(function (RequestInterface $request): bool {
            if (! str_contains((string) $request->getUri(), '/one_off_event_types')) {
                return false;
            }
            /** @var array<string, mixed> $body */
            $body = json_decode((string) $request->getBody(), true);

            return ($body['name'] ?? null) === 'Erstberatung'
                && ($body['host'] ?? null) === 'https://api.calendly.com/users/u1'
                && ($body['duration'] ?? null) === 45;
        });
    }

    public function test_booking_link_api_error_flashes_error(): void {
        FakePluginHttp::fake([
            'https://api.calendly.com/one_off_event_types*' => FakePluginHttp::response(['message' => 'boom'], 500),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.calendly.booking-link'), ['name' => 'Erstberatung', 'duration' => 30])
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('calendly_booking_url');
    }

    public function test_disconnect_marks_the_connection_disconnected(): void {
        $fake = FakePluginHttp::fake();

        $this->actingAs($this->admin)->post(route('admin.calendly.disconnect'))
            ->assertRedirect()->assertSessionHas('success');

        $fresh = $this->connection->fresh();
        $this->assertInstanceOf(CalendlyConnection::class, $fresh);
        $this->assertSame(CalendlyConnectionStatus::Disconnected, $fresh->status);
        $this->assertNull($fresh->access_token);
        $this->assertFalse($fresh->isActive());
        $fake->assertNothingSent();
    }

    public function test_booking_link_requires_active_connection(): void {
        $fake = FakePluginHttp::fake();
        $this->connection->forceFill(['status' => CalendlyConnectionStatus::Disconnected])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.calendly.booking-link'), ['name' => 'Erstberatung', 'duration' => 30])
            ->assertRedirect()
            ->assertSessionHas('error');

        $fake->assertNothingSent();
    }

    public function test_app_side_cancel_is_synced_to_calendly(): void {
        $request = $this->confirmedRequest();
        $entry = $request->diaryEntry()->firstOrFail();

        $fake = FakePluginHttp::fake([
            'https://api.calendly.com/scheduled_events/' . self::EVENT_UUID . '/cancellation*' => FakePluginHttp::response(['resource' => []], 201),
        ]);

        app(OrderService::class)->cancel($entry, $this->admin, 'Kunde verhindert');

        $fake->assertSent(function (RequestInterface $sent): bool {
            if (! str_contains((string) $sent->getUri(), '/scheduled_events/' . self::EVENT_UUID . '/cancellation')) {
                return false;
            }
            /** @var array<string, mixed> $body */
            $body = json_decode((string) $sent->getBody(), true);

            return ($body['reason'] ?? null) === 'Kunde verhindert';
        });

        $request->refresh();
        $this->assertSame(AppointmentRequestStatus::Canceled, $request->status);
        $this->assertSame('Kunde verhindert', $request->cancellation['reason'] ?? null);
        $this->assertSame(Status::Cancelled, $entry->fresh()?->status);

        // Echo-Webhook der eigenen Absage → idempotent, kein Schein-Konflikt.
        app(CalendlyIngestService::class)->handleInvitee($this->organization, 'invitee.canceled', [
            'uri' => self::INVITEE_URI,
            'cancellation' => ['canceler_type' => 'host', 'reason' => 'Kunde verhindert'],
        ]);
        $this->assertSame(AppointmentRequestStatus::Canceled, $request->refresh()->status);
        $this->assertSame(0, IntegrationInboxItem::query()->where('case_type', IntegrationInboxItem::CASE_CONFLICT)->count());
    }

    public function test_failed_cancel_sync_never_blocks_the_app_storno(): void {
        $request = $this->confirmedRequest();
        $entry = $request->diaryEntry()->firstOrFail();

        FakePluginHttp::fake([
            'https://api.calendly.com/scheduled_events/*' => FakePluginHttp::response(['message' => 'down'], 500),
        ]);

        // Calendly nicht erreichbar → App-Storno gelingt trotzdem (best effort).
        app(OrderService::class)->cancel($entry, $this->admin, 'Absage');

        $this->assertSame(Status::Cancelled, $entry->fresh()?->status);
        // Terminwunsch bleibt confirmed — bei Calendly ist der Termin noch aktiv.
        $this->assertSame(AppointmentRequestStatus::Confirmed, $request->refresh()->status);
        $this->assertSame(1, PluginError::query()
            ->where('plugin_id', 'calendly')
            ->where('phase', 'outbound-cancel')
            ->count());
    }

    public function test_calendly_side_cancel_does_not_echo_back(): void {
        $request = $this->confirmedRequest();

        $fake = FakePluginHttp::fake();

        app(CalendlyIngestService::class)->handleInvitee($this->organization, 'invitee.canceled', [
            'uri' => self::INVITEE_URI,
            'cancellation' => ['canceler_type' => 'invitee', 'reason' => 'Verhindert'],
        ]);

        $this->assertSame(AppointmentRequestStatus::Canceled, $request->refresh()->status);
        $this->assertSame(Status::Cancelled, $request->diaryEntry()->firstOrFail()->status);
        $fake->assertNothingSent();
    }

    public function test_event_uuid_extraction_from_calendly_uris(): void {
        $this->assertSame('EV1', CalendlyOutboundService::eventUuidFromUri(self::INVITEE_URI));
        $this->assertSame('EV1', CalendlyOutboundService::eventUuidFromUri('https://api.calendly.com/scheduled_events/EV1'));
        $this->assertNull(CalendlyOutboundService::eventUuidFromUri('https://api.calendly.com/users/u1'));
    }
}
