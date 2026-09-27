<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalPortalDirectBookingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Rental\{RentalCaseStatus, RentalRateCardStatus, RentalRequestStatus, RentalReservationKind};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalProfile, RentalRateCard, RentalRequest, RentalReservation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-916: Direktbuchung und Preisangabe im Kundenportal. */
final class RentalPortalDirectBookingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $portalUser;

    private Asset $asset;

    /** @var array{from: string, to: string} */
    private array $period = ['from' => '2026-10-07 08:00', 'to' => '2026-10-09 17:00'];

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-10-05 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer);
        $this->portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Minibagger']);
        $card = RentalRateCard::query()->create(['organization_id' => $this->organization->id, 'name' => 'Standard', 'version' => 1, 'status' => RentalRateCardStatus::Active->value, 'created_by' => $admin->id]);
        $card->items()->createMany([
            ['organization_id' => $this->organization->id, 'kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'group_code' => 'bagger', 'amount' => '100.00', 'unit' => 'day'],
            ['organization_id' => $this->organization->id, 'kind' => 'cleaning', 'label' => 'Endreinigung', 'amount' => '50.00', 'unit' => 'flat'],
        ]);
        RentalProfile::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $this->asset->id, 'is_rentable' => true, 'portal_bookable' => true, 'group_code' => 'bagger', 'default_rate_card_id' => $card->id]);
    }

    private function enableDirectBooking(): void {
        $this->organization->forceFill(['settings' => array_replace_recursive((array) $this->organization->settings, ['rental' => ['portal_direct_booking' => true]])])->save();
    }

    private function book(): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->portalUser, 'customer')->post(route('customer.rentals.requests.store'), $this->period + ['subject' => 'asset:' . $this->asset->sqid, 'booking' => 'direct']);
    }

    public function test_price_estimate_comes_from_the_default_rate_card(): void {
        // 57 Stunden → 3 Tagessätze à 100 € + Endreinigung 50 €
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.rentals.requests.index', $this->period))->assertOk()
            ->assertSee('350,00')
            ->assertDontSee(__('rental.portal.direct_book'));
    }

    public function test_direct_booking_is_refused_without_the_setting(): void {
        $this->book()->assertSessionHas('error', __('rental.portal.direct_disabled'));
        $this->assertSame(0, RentalCase::query()->count());
    }

    public function test_direct_booking_reserves_hard_and_blocks_a_second_booking(): void {
        $this->enableDirectBooking();
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.rentals.requests.index', $this->period))->assertOk()->assertSee(__('rental.portal.direct_book'));

        $this->book()->assertRedirect(route('customer.rentals.requests.index'))->assertSessionHas('success');

        $request = RentalRequest::query()->sole();
        $this->assertTrue($request->is_direct);
        $this->assertSame(RentalRequestStatus::Accepted, $request->status);
        $this->assertNull($request->decided_by);
        $case = RentalCase::query()->findOrFail($request->rental_case_id);
        $this->assertSame(RentalCaseStatus::Reserved, $case->status);
        $this->assertSame(1, (int) data_get($case->terms_snapshot, 'version'));
        $reservation = RentalReservation::query()->findOrFail($request->rental_reservation_id);
        $this->assertSame(RentalReservationKind::Hard, $reservation->kind);

        $this->book()->assertSessionHas('error');
        $this->assertSame(1, RentalCase::query()->count());
        $this->assertSame(1, RentalRequest::query()->count());
    }

    public function test_group_requests_cannot_be_booked_directly(): void {
        $this->enableDirectBooking();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.rentals.requests.store'), $this->period + ['subject' => 'group:bagger', 'booking' => 'direct'])->assertNotFound();
    }
}
