<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRequestDecisionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Rental\{RentalCaseStatus, RentalRequestStatus, RentalReservationKind};
use App\Exceptions\RentalConflictException;
use App\Mail\RentalRequestDecisionMail;
use App\Models\{Asset, Customer, User};
use App\Models\Rental\{RentalCase, RentalProfile, RentalRequest, RentalReservation};
use App\Notifications\GenericEventNotification;
use App\Services\Rental\{RentalCaseService, RentalRequestService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Mail, Notification};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-714 (Vollscan G10): interne Entscheidung über Portal-Verleihanfragen —
 * Annahme erzeugt Akte (Entwurf) + Vormerkung über die bestehenden
 * Schreibstellen, Ablehnung mit Grund, Überlappung → Hinweis statt
 * Doppelbuchung, Rechte wie die Verleihakte.
 */
final class RentalRequestDecisionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private Customer $customer;

    private User $portalUser;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id, 'email' => 'kunde@example.test']);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Minibagger']);
        RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->asset->id,
            'is_rentable' => true,
            'portal_bookable' => true,
            'group_code' => 'bagger',
            'buffer_after_hours' => 4,
        ]);
    }

    private function request(?Asset $asset = null, ?string $group = null): RentalRequest {
        return app(RentalRequestService::class)->requestFromPortal(
            $this->customer,
            $this->portalUser,
            $asset ?? ($group === null ? $this->asset : null),
            $group,
            Carbon::now()->addDays(2)->setTime(8, 0),
            Carbon::now()->addDays(4)->setTime(17, 0),
            'Aushub',
        );
    }

    public function test_request_notifies_internal_role_with_render_time_keys(): void {
        Notification::fake();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $request = $this->request();

        $this->assertSame(RentalRequestStatus::Requested, $request->status);
        Notification::assertSentTo($lead, GenericEventNotification::class, function (GenericEventNotification $n): bool {
            return $n->event === NotificationEvent::RentalRequested
                && ($n->payload['title_key'] ?? null) === 'Verleih-Anfrage von :customer'
                && ($n->payload['message_key'] ?? null) === ':subject vom :from bis :to'
                && ($n->payload['url'] ?? null) === route('rental.requests.index');
        });
        // Das Portalkonto selbst erhält keine interne Benachrichtigung.
        Notification::assertNotSentTo($this->portalUser, GenericEventNotification::class);
    }

    public function test_accept_creates_draft_case_and_soft_reservation_and_mails_customer(): void {
        $request = $this->request();

        $accepted = app(RentalRequestService::class)->accept($request, $this->admin);

        $this->assertSame(RentalRequestStatus::Accepted, $accepted->status);
        $this->assertSame((int) $this->admin->id, (int) $accepted->decided_by);
        $this->assertNotNull($accepted->decided_at);

        $case = RentalCase::query()->findOrFail($accepted->rental_case_id);
        $this->assertSame(RentalCaseStatus::Draft, $case->status);
        $this->assertSame((int) $this->customer->id, (int) $case->customer_id);
        $this->assertTrue($case->starts_at->equalTo($request->starts_at));
        $this->assertSame(1, $case->caseAssets()->where('asset_id', $this->asset->id)->count());

        $reservation = RentalReservation::query()->findOrFail($accepted->rental_reservation_id);
        $this->assertSame(RentalReservationKind::Soft, $reservation->kind);
        $this->assertSame((int) $case->id, (int) $reservation->rental_case_id);
        $this->assertSame('active', $reservation->status);

        Mail::assertSent(RentalRequestDecisionMail::class, fn (RentalRequestDecisionMail $m): bool => $m->hasTo('kunde@example.test'));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'event' => 'rental.requestAccepted']);

        // Vormerkung ist weich: die Akte lässt sich anschließend hart reservieren.
        app(RentalCaseService::class)->reserve($case, $this->admin);
        $this->assertSame(RentalCaseStatus::Reserved, $case->fresh()->status);
    }

    public function test_accept_with_overlap_raises_hint_and_leaves_request_open(): void {
        $request = $this->request();
        $blocking = app(RentalCaseService::class)->open($this->organization, $this->admin, [
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
            'starts_at' => $request->starts_at->copy()->subDay(),
            'ends_at' => $request->starts_at->copy()->addHours(3),
        ], [$this->asset->id]);
        app(RentalCaseService::class)->reserve($blocking, $this->admin);

        $this->expectException(RentalConflictException::class);
        try {
            app(RentalRequestService::class)->accept($request, $this->admin);
        } finally {
            $this->assertSame(RentalRequestStatus::Requested, $request->fresh()->status);
            $this->assertNull($request->fresh()->rental_case_id);
            $this->assertSame(1, RentalCase::query()->count());
        }
    }

    public function test_group_request_requires_asset_choice_on_accept(): void {
        $request = $this->request(group: 'bagger');

        try {
            app(RentalRequestService::class)->accept($request, $this->admin);
            $this->fail('Gruppenanfrage ohne Gerät darf nicht angenommen werden.');
        } catch (\RuntimeException $e) {
            $this->assertSame(RentalRequestStatus::Requested, $request->fresh()->status);
        }

        $accepted = app(RentalRequestService::class)->accept($request, $this->admin, $this->asset);
        $this->assertSame((int) $this->asset->id, (int) $accepted->asset_id);
        $this->assertNotNull($accepted->rental_case_id);
    }

    public function test_decline_records_reason_and_mails_customer(): void {
        $request = $this->request();

        $declined = app(RentalRequestService::class)->decline($request, $this->admin, 'Gerät in dieser Woche in Wartung.');

        $this->assertSame(RentalRequestStatus::Declined, $declined->status);
        $this->assertSame('Gerät in dieser Woche in Wartung.', $declined->decline_reason);
        $this->assertSame(0, RentalCase::query()->count());
        Mail::assertSent(RentalRequestDecisionMail::class, fn (RentalRequestDecisionMail $m): bool => $m->hasTo('kunde@example.test'));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'event' => 'rental.requestDeclined']);

        // Entschieden = unveränderlich.
        $this->expectException(\RuntimeException::class);
        app(RentalRequestService::class)->accept($request->fresh(), $this->admin);
    }

    public function test_withdrawn_request_cannot_be_decided(): void {
        $request = $this->request();
        app(RentalRequestService::class)->withdrawFromPortal($request, $this->portalUser);

        $this->expectException(\RuntimeException::class);
        app(RentalRequestService::class)->decline($request->fresh(), $this->admin, 'zu spät');
    }

    public function test_http_inbox_accept_and_decline_are_permission_gated(): void {
        $request = $this->request();
        $otherRequest = $this->request();

        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->get(route('rental.requests.index'))->assertForbidden();
        $this->actingAs($plain)->post(route('rental.requests.accept', $request))->assertForbidden();

        $this->actingAs($this->admin)->get(route('rental.requests.index'))
            ->assertOk()->assertSee('Minibagger')->assertSee($this->customer->name);

        $this->actingAs($this->admin)->post(route('rental.requests.accept', $request))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(RentalRequestStatus::Accepted, $request->fresh()->status);

        // Zweite Anfrage überlappt nun mit der Vormerkung? Weich → keine Kollision;
        // harte Reservierung der ersten Akte dagegen blockt mit Hinweis.
        app(RentalCaseService::class)->reserve(RentalCase::query()->findOrFail($request->fresh()->rental_case_id), $this->admin);
        $this->actingAs($this->admin)->post(route('rental.requests.accept', $otherRequest))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(RentalRequestStatus::Requested, $otherRequest->fresh()->status);

        $this->actingAs($this->admin)->post(route('rental.requests.decline', $otherRequest), ['reason' => 'Bereits vergeben.'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(RentalRequestStatus::Declined, $otherRequest->fresh()->status);
    }
}
