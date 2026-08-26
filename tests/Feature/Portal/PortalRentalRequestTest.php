<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalRentalRequestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\Rental\{RentalRequestStatus, RentalReservationKind};
use App\Models\{Asset, Customer, Organization, User};
use App\Models\Rental\{RentalProfile, RentalRequest, RentalReservation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-714 (Vollscan G10): Verleih-Anfrage im Portal — Default-Deny der
 * Capability UND des Sortiments, grobe Verfügbarkeit ohne Fremddetails,
 * Anfrage bleibt `requested` (kein Kalender-Schreibzugriff), Rücknahme.
 */
final class PortalRentalRequestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private Asset $bagger;

    private Asset $hidden;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);

        $this->bagger = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Minibagger MB-1']);
        RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->bagger->id,
            'is_rentable' => true,
            'portal_bookable' => true,
            'group_code' => 'bagger',
        ]);
        // Leihfähig, aber NICHT fürs Portal freigegeben (Default-Deny).
        $this->hidden = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Interner Radlader']);
        RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->hidden->id,
            'is_rentable' => true,
            'group_code' => 'radlader',
        ]);
    }

    /** @return array<string, string> */
    private function payload(string $subject, array $overrides = []): array {
        return array_merge([
            'subject' => $subject,
            'from' => now()->addDays(2)->setTime(8, 0)->format('Y-m-d H:i'),
            'to' => now()->addDays(4)->setTime(17, 0)->format('Y-m-d H:i'),
            'note' => 'Aushub Garten',
        ], $overrides);
    }

    public function test_without_capability_endpoints_are_404(): void {
        $this->allowPortal($this->customer, ['rentals']); // rental_requests fehlt bewusst

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.rentals.requests.index'))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('asset:' . $this->bagger->sqid))
            ->assertNotFound();
        $this->assertSame(0, RentalRequest::query()->withoutGlobalScopes()->count());

        // Auch Navigation und Verleih-Liste zeigen keinen Einstieg.
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.rentals.index'))
            ->assertOk()->assertDontSee(route('customer.rentals.requests.index'));
    }

    public function test_index_lists_only_portal_bookable_assets_with_rough_availability(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Geheimkunde GmbH']);
        $from = now()->addDays(2)->setTime(8, 0);
        $to = now()->addDays(4)->setTime(17, 0);
        // Fremdbelegung im Zeitraum: nur „belegt" darf sichtbar werden.
        RentalReservation::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->bagger->id,
            'kind' => RentalReservationKind::Maintenance->value,
            'status' => 'active',
            'starts_at' => $from->copy()->addHours(2),
            'ends_at' => $from->copy()->addHours(6),
            'note' => 'Wartung für ' . $otherCustomer->name,
        ]);

        $response = $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.rentals.requests.index', ['from' => $from->format('Y-m-d\TH:i'), 'to' => $to->format('Y-m-d\TH:i')]));

        $response->assertOk()
            ->assertSee('Minibagger MB-1')
            ->assertDontSee('Interner Radlader')
            ->assertSee(__('belegt'))
            ->assertDontSee('Geheimkunde GmbH')
            ->assertDontSee(__('Wartungsfenster'));
    }

    public function test_store_creates_requested_request_without_touching_calendar(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('asset:' . $this->bagger->sqid))
            ->assertRedirect(route('customer.rentals.requests.index'));

        $request = RentalRequest::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(RentalRequestStatus::Requested, $request->status);
        $this->assertSame((int) $this->customer->id, (int) $request->customer_id);
        $this->assertSame((int) $this->portalUser->id, (int) $request->portal_user_id);
        $this->assertSame((int) $this->bagger->id, (int) $request->asset_id);
        $this->assertSame('Aushub Garten', $request->note);
        $this->assertNull($request->rental_reservation_id);
        // Zweiphasig: kein Belegungsfenster, keine Akte durch den Kunden.
        $this->assertSame(0, RentalReservation::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'event' => 'rental.requested']);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.rentals.requests.index'))
            ->assertOk()->assertSee('Minibagger MB-1')->assertSee(__('angefragt'));
    }

    public function test_group_request_is_possible_for_released_group(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('group:bagger'))
            ->assertRedirect(route('customer.rentals.requests.index'));

        $request = RentalRequest::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertNull($request->asset_id);
        $this->assertSame('bagger', $request->group_code);

        // Gruppe ohne Portal-Freigabe → abgelehnt, keine Anfrage.
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('group:radlader'))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame(1, RentalRequest::query()->withoutGlobalScopes()->count());
    }

    public function test_store_rejects_hidden_foreign_and_invalid_periods(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('asset:' . $this->hidden->sqid))
            ->assertRedirect()->assertSessionHas('error');

        $otherOrg = Organization::factory()->create();
        $foreignAsset = Asset::factory()->create(['organization_id' => $otherOrg->id]);
        RentalProfile::query()->create(['organization_id' => $otherOrg->id, 'asset_id' => $foreignAsset->id, 'is_rentable' => true, 'portal_bookable' => true]);
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('asset:' . $foreignAsset->sqid))
            ->assertNotFound();

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('asset:' . $this->bagger->sqid, [
                'from' => now()->subDay()->format('Y-m-d H:i'),
                'to' => now()->addDay()->format('Y-m-d H:i'),
            ]))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, RentalRequest::query()->withoutGlobalScopes()->count());
    }

    public function test_withdraw_only_own_open_request(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.store'), $this->payload('asset:' . $this->bagger->sqid));
        $request = RentalRequest::query()->withoutGlobalScopes()->firstOrFail();

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($otherCustomer);
        $stranger = User::factory()->kunde((int) $otherCustomer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->actingAs($stranger, 'customer')
            ->post(route('customer.rentals.requests.withdraw', $request))
            ->assertNotFound();
        $this->actingAs($stranger, 'customer')->get(route('customer.rentals.requests.index'))
            ->assertOk()->assertDontSee('Aushub Garten');

        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.withdraw', $request))
            ->assertRedirect();
        $this->assertSame(RentalRequestStatus::Withdrawn, $request->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $request->id, 'event' => 'rental.requestWithdrawn']);

        // Entschiedene/zurückgenommene Anfrage: keine zweite Rücknahme.
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.withdraw', $request))
            ->assertRedirect()->assertSessionHas('error');
    }
}
