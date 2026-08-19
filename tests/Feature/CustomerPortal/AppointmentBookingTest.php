<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentBookingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Models\{AppointmentRequest, BookableService, Customer, DiaryEntry, User};
use App\Services\Appointments\AppointmentRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Online-Terminbuchung (Feature 087, MVP-666–668).
 *
 * Kern der Prüfung: **Zweiphasigkeit** — kein Anfrage-Pfad erzeugt ohne
 * interne Bestätigung einen Dispositions-Eintrag; Vorlauf und Stornofrist
 * gelten; ohne Portal-Freigabe ist der Bereich unsichtbar (Default-Deny).
 */
final class AppointmentBookingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private User $admin;

    private BookableService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->service = BookableService::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Wartungstermin',
            'duration_minutes' => 60,
            'lead_time_hours' => 24,
            'cancel_hours' => 24,
            'buffer_minutes' => 15,
            'active' => true,
        ]);
    }

    private function request(?CarbonImmutable $start = null): AppointmentRequest {
        return app(AppointmentRequestService::class)->requestFromPortal(
            $this->service,
            $this->customer,
            $this->portalUser,
            $start ?? CarbonImmutable::now()->addDays(3)->setTime(9, 0),
        );
    }

    /** Kein Anfrage-Pfad erzeugt ohne Bestätigung einen Eintrag. */
    public function test_request_alone_creates_no_diary_entry(): void {
        $request = $this->request();

        $this->assertSame(AppointmentRequest::STATUS_REQUESTED, $request->status);
        $this->assertSame(0, DiaryEntry::query()->count());
    }

    /** Erst die Bestätigung der Disposition erzeugt den Eintrag. */
    public function test_confirmation_creates_the_dispatch_entry(): void {
        $request = $this->request();

        $entry = app(AppointmentRequestService::class)->confirm($request, $this->admin);

        $this->assertSame(1, DiaryEntry::query()->count());
        $fresh = $request->fresh();
        $this->assertSame(AppointmentRequest::STATUS_CONFIRMED, $fresh?->status);
        $this->assertSame($entry->id, $fresh?->diary_entry_id);
        $this->assertSame($this->admin->id, $fresh?->decided_by);
    }

    /** Der Vorlauf der Leistungsart gilt. */
    public function test_lead_time_is_enforced(): void {
        $this->expectException(\RuntimeException::class);
        $this->request(CarbonImmutable::now()->addHours(2));
    }

    /** Storno nur innerhalb der Frist. */
    public function test_cancellation_respects_the_deadline(): void {
        $request = $this->request(CarbonImmutable::now()->addHours(30));

        // 30 h Abstand, 24 h Frist → noch stornierbar.
        app(AppointmentRequestService::class)->cancelFromPortal($request, $this->portalUser);
        $this->assertSame(AppointmentRequest::STATUS_CANCELED, $request->fresh()?->status);

        // Innerhalb der Frist: abgelehnt.
        $late = $this->request(CarbonImmutable::now()->addHours(25));
        $late->forceFill(['start_at' => CarbonImmutable::now()->addHours(3)])->save();
        $this->expectException(\RuntimeException::class);
        app(AppointmentRequestService::class)->cancelFromPortal($late->fresh(), $this->portalUser);
    }

    /** Fremde Anfragen lassen sich nicht stornieren. */
    public function test_foreign_request_cannot_be_cancelled(): void {
        $request = $this->request();
        $other = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create();

        $this->expectException(\RuntimeException::class);
        app(AppointmentRequestService::class)->cancelFromPortal($request, $other);
    }

    /** Default-Deny: ohne Freigabe der Capability ist der Bereich 404. */
    public function test_portal_page_requires_the_capability(): void {
        $this->allowPortal($this->customer, ['diary']);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.appointments.index'))
            ->assertNotFound();

        $this->allowPortal($this->customer);
        // Die User-Instanz memoisiert die customer-Relation über Requests
        // hinweg - ohne unset sähe der zweite Request die alten Freigaben.
        $this->portalUser->unsetRelation('customer');
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.appointments.index'))
            ->assertOk()
            ->assertSee('Wartungstermin');
    }

    /** Die Dispositions-Inbox zeigt die Anfrage; Ablehnen trägt den Grund. */
    public function test_inbox_shows_and_declines_with_reason(): void {
        $request = $this->request();

        $this->actingAs($this->admin)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Wartungstermin');

        $this->actingAs($this->admin)
            ->post(route('appointments.decline', $request), ['reason' => 'Kein Personal an dem Tag'])
            ->assertRedirect();

        $fresh = $request->fresh();
        $this->assertSame(AppointmentRequest::STATUS_DECLINED, $fresh?->status);
        $this->assertSame('Kein Personal an dem Tag', $fresh?->decline_reason);
    }
}
