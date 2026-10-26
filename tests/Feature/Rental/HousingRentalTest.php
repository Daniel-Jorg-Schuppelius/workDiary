<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HousingRentalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Rental\{RentalCaseStatus, RentalRateCardStatus, RentalRequestStatus, RentalReservationKind, RentalTenancyStatus};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Rental\{RentalCase, RentalProfile, RentalRateCard, RentalRequest, RentalReservation, RentalTenancy};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Rental\{RentalAvailabilityService, RentalRequestService, RentalTenancyService};
use App\Support\Tz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization, WithPortalVisibility};
use Tests\TestCase;

final class HousingRentalTest extends TestCase {
    use RefreshDatabase, WithGlobalDateRange, WithOrganization, WithPortalVisibility;

    private User $admin;
    private User $portalUser;
    private Customer $customer;
    private RentalProfile $profile;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-10-01 09:00:00');
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()->kunde($this->customer->id, $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Wohnung Seeblick']);
        $this->asset = $asset;
        $this->profile = RentalProfile::query()->create([
            'organization_id' => $this->organization->id, 'asset_id' => $asset->id,
            'rental_mode' => 'both', 'is_rentable' => true, 'portal_bookable' => true,
            'max_guests' => 4, 'minimum_nights' => 2, 'rent_amount' => '800.00', 'ancillary_amount' => '150.00',
            'public_description' => 'Helle Wohnung am See', 'notes' => 'INTERNE NOTIZ NICHT ZEIGEN',
        ]);
    }

    private function tenancy(?string $end = null): RentalTenancy {
        return app(RentalTenancyService::class)->create($this->profile, $this->customer, $this->admin, ['starts_on' => '2026-10-10', 'ends_on' => $end]);
    }

    private function available(string $from, string $to): bool {
        return app(RentalAvailabilityService::class)->isAvailable($this->asset, Carbon::instance(Tz::parse($from)), Carbon::instance(Tz::parse($to)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function holiday(array $overrides = []): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->portalUser, 'customer')->post(route('customer.housing.store'), array_replace([
            'profile_id' => $this->profile->sqid, 'mode' => 'holiday', 'starts_on' => '2026-10-15', 'ends_on' => '2026-10-18', 'guests' => 3,
        ], $overrides));
    }

    public function test_profile_install_is_idempotent_and_recommends_rental_module(): void {
        $installer = app(BranchProfileInstaller::class);
        $installer->install($this->organization, 'vermieter', $this->admin);
        $repeat = $installer->install($this->organization, 'vermieter', $this->admin);
        $this->assertSame(0, $repeat['created']['classifications']);
        $this->assertTrue($this->organization->refresh()->hasBranchProfile('vermieter'));
        $profile = require database_path('data/branchprofiles/vermieter.php');
        $this->assertContains('module.rental', $profile['modules_recommended']);

        // Abläufe für Einzug, Auszug und Gästewechsel, das Übergabeprotokoll, Mietvertragsvorlagen und Felder der Wohnung.
        $this->assertEqualsCanonicalizing(['VM_WOHNUNGSUEBERGABE', 'VM_WOHNUNGSABNAHME', 'VM_GAESTEWECHSEL'],
            \App\Models\Procedure\ProcedureTemplate::query()->where('organization_id', $this->organization->id)->where('domain', 'vermieter')->pluck('code')->all());
        $this->assertTrue(\App\Models\Protocol\ProtocolTemplate::query()->where('organization_id', $this->organization->id)->where('name', 'Wohnungsübergabeprotokoll')->exists());
        $rent = \App\Models\Contract\ContractTemplate::query()->where('organization_id', $this->organization->id)->where('name', 'Wohnraummietvertrag mit Indexmiete')->sole();
        $this->assertSame(\App\Enums\Contract\ContractKind::Rent, $rent->kind);
        $this->assertContains('indexation', array_column($rent->obligations, 'kind'));
        $fields = \App\Models\Fields\CustomFieldDefinition::query()->where('organization_id', $this->organization->id)->where('subject_alias', 'assets')->sole();
        $this->assertSame(5, $fields->schema->count());
        $this->assertSame(3, \App\Models\Contract\ContractTemplate::query()->where('organization_id', $this->organization->id)->count(), 'Ein zweiter Lauf legt nichts doppelt an.');
        $this->assertSame('Move-out inspection', \App\Models\Classification\Classification::query()->where('organization_id', $this->organization->id)->where('code', 'wohnungsabnahme')->sole()->displayLabel('en'));
    }

    public function test_admin_can_create_and_edit_a_unit_with_an_asset_in_dialogs(): void {
        $this->actingAs($this->admin)->get(route('rental.housing.create') . '?dialog=1')->assertOk()->assertSee(__('housing.create'));
        $payload = [
            'name' => 'Ferienwohnung Garten', 'rental_mode' => 'both', 'is_rentable' => 1, 'portal_bookable' => 0,
            'max_guests' => 2, 'minimum_nights' => 1, 'check_in_time' => '15:00', 'check_out_time' => '10:00',
            'rent_amount' => '900.00', 'buffer_after_hours' => 0,
        ];
        $this->post(route('rental.housing.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $created = RentalProfile::query()->where('asset_id', '!=', $this->profile->asset_id)->sole();
        $this->assertSame('Ferienwohnung Garten', $created->asset?->name);
        $this->get(route('rental.housing.edit', $created) . '?dialog=1')->assertOk();
        $this->put(route('rental.housing.update', $created), array_replace($payload, ['name' => 'Wohnung Garten']))->assertSessionHasNoErrors();
        $this->assertSame('Wohnung Garten', $created->asset->refresh()->name);
    }

    public function test_indefinite_tenancy_blocks_all_later_holiday_bookings(): void {
        $this->tenancy();
        $this->assertTrue($this->available('2026-10-08 15:00', '2026-10-09 10:00'));
        $this->assertFalse($this->available('2035-08-01 15:00', '2035-08-05 10:00'));
        $this->holiday()->assertSessionHasErrors('starts_on');
        $this->assertSame(0, RentalRequest::query()->count());
    }

    public function test_last_rental_day_is_inclusive_and_next_day_is_free(): void {
        $this->tenancy('2026-10-14');
        $this->assertFalse($this->available('2026-10-14 15:00', '2026-10-15 10:00'));
        $this->assertTrue($this->available('2026-10-15 15:00', '2026-10-18 10:00'));
    }

    public function test_existing_holiday_reservation_blocks_a_new_indefinite_tenancy(): void {
        RentalReservation::query()->create([
            'organization_id' => $this->organization->id, 'asset_id' => $this->profile->asset_id,
            'kind' => RentalReservationKind::Hard, 'status' => 'active', 'starts_at' => '2028-10-15 13:00', 'ends_at' => '2028-10-18 08:00',
        ]);
        $this->actingAs($this->admin)->post(route('rental.housing.tenancies.store'), [
            'profile_id' => $this->profile->sqid, 'customer_id' => $this->customer->sqid, 'starts_on' => '2026-10-10',
        ])->assertSessionHasErrors('starts_on');
        $this->assertSame(0, RentalTenancy::query()->count());
    }

    public function test_tenancy_can_end_and_the_next_tenant_can_move_in_the_next_day(): void {
        $tenancy = $this->tenancy();
        $this->actingAs($this->admin)->get(route('rental.housing.tenancies.edit', $tenancy) . '?dialog=1')->assertOk();
        $this->put(route('rental.housing.tenancies.update', $tenancy), ['status' => 'active', 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-31'])->assertSessionHasNoErrors();
        $this->post(route('rental.housing.tenancies.store'), ['profile_id' => $this->profile->sqid, 'customer_id' => $this->customer->sqid, 'starts_on' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->assertSame(2, RentalTenancy::query()->where('status', 'active')->count());
    }

    public function test_portal_long_term_request_is_not_a_confirmed_occupancy_and_cannot_set_money(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.housing.store'), [
            'profile_id' => $this->profile->sqid, 'mode' => 'long_term', 'starts_on' => '2026-10-10', 'rent_amount' => '1.00', 'customer_id' => 999,
        ])->assertSessionHasNoErrors()->assertRedirect(route('customer.housing.index'));
        $tenancy = RentalTenancy::query()->sole();
        $this->assertSame(RentalTenancyStatus::Requested, $tenancy->status);
        $this->assertNull($tenancy->rent_amount);
        $this->assertSame($this->customer->id, $tenancy->customer_id);
        $this->assertTrue($this->available('2026-10-15 15:00', '2026-10-18 10:00'));
        $this->actingAs($this->admin)->put(route('rental.housing.tenancies.update', $tenancy), [
            'status' => 'active', 'starts_on' => '2026-10-10', 'rent_amount' => '800.00',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->available('2026-10-15 15:00', '2026-10-18 10:00'));
    }

    public function test_holiday_request_uses_local_checkin_times_and_confirmation_blocks_the_unit(): void {
        $this->holiday()->assertSessionHasNoErrors();
        $booking = RentalRequest::query()->sole();
        $this->assertSame(3, $booking->guests);
        $this->assertSame('2026-10-15 13:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-18 08:00', $booking->ends_at->format('Y-m-d H:i'));
        $this->assertSame(0, RentalReservation::query()->count());
        $this->actingAs($this->admin);
        app(RentalRequestService::class)->accept($booking, $this->admin);
        $this->assertSame(RentalCaseStatus::Reserved, RentalCase::query()->sole()->status);
        $this->assertFalse($this->available('2026-10-15 15:00', '2026-10-18 10:00'));
    }

    public function test_portal_rejects_capacity_minimum_nights_and_reversed_dates(): void {
        $this->holiday(['guests' => 5])->assertSessionHasErrors('guests');
        $this->holiday(['ends_on' => '2026-10-16'])->assertSessionHasErrors('guests');
        $this->holiday(['ends_on' => '2026-10-14'])->assertSessionHasErrors('ends_on');
        $this->assertSame(0, RentalRequest::query()->count());
    }

    public function test_portal_lists_only_published_units_and_own_requests_without_internal_data(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'FREMDER MIETER']);
        RentalTenancy::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $this->profile->asset_id, 'customer_id' => $other->id, 'status' => 'active', 'starts_on' => '2026-10-10', 'note' => 'FREMDES GEHEIMNIS']);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.housing.index', ['arrival' => '2026-10-15', 'departure' => '2026-10-18']))->assertOk()
            ->assertSee('Wohnung Seeblick')->assertSee(__('housing.unavailable_status'))->assertDontSee('FREMDER MIETER')->assertDontSee('FREMDES GEHEIMNIS')->assertDontSee('INTERNE NOTIZ NICHT ZEIGEN');
        $this->profile->update(['portal_bookable' => false]);
        $this->get(route('customer.housing.index'))->assertOk()->assertDontSee('Wohnung Seeblick');
        $this->holiday()->assertNotFound();
    }

    public function test_portal_cannot_withdraw_another_customers_tenancy_or_an_active_one(): void {
        $tenancy = $this->tenancy();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.housing.withdraw', $tenancy))->assertSessionHasErrors('status');
        $this->assertSame(RentalTenancyStatus::Active, $tenancy->refresh()->status);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $tenancy->forceFill(['customer_id' => $other->id, 'status' => 'requested'])->save();
        $this->post(route('customer.housing.withdraw', $tenancy))->assertNotFound();
    }

    public function test_foreign_organization_units_are_inaccessible(): void {
        $otherOrg = Organization::factory()->create();
        $asset = Asset::factory()->create(['organization_id' => $otherOrg->id]);
        $profile = RentalProfile::query()->create(['organization_id' => $otherOrg->id, 'asset_id' => $asset->id, 'rental_mode' => 'both', 'is_rentable' => true, 'portal_bookable' => true]);
        $this->actingAs($this->admin)->get(route('rental.housing.edit', $profile))->assertNotFound();
        $this->holiday(['profile_id' => $profile->sqid])->assertNotFound();
    }

    public function test_overview_uses_global_range_and_keeps_vacant_units_visible(): void {
        $this->tenancy('2026-10-14');
        $this->actingAs($this->admin)->withSession($this->dateRangeSession('2026-10-15', '2026-10-18'))
            ->get(route('rental.housing.index'))->assertOk()->assertSee('Wohnung Seeblick')->assertSee(__('housing.free'));
    }

    public function test_direct_holiday_booking_requires_explicit_setting_and_blocks_duplicates(): void {
        $this->holiday(['booking' => 'direct'])->assertSessionHasErrors('starts_on');
        $this->organization->update(['settings' => ['rental' => ['portal_direct_booking' => true]]]);
        $this->holiday(['booking' => 'direct'])->assertSessionHasNoErrors();
        $this->holiday(['booking' => 'direct'])->assertSessionHasErrors('starts_on');
        $this->assertSame(1, RentalCase::query()->count());
    }

    public function test_cleaning_buffer_blocks_both_booking_orders(): void {
        $this->profile->update(['buffer_after_hours' => 8]);
        RentalReservation::query()->create([
            'organization_id' => $this->organization->id, 'asset_id' => $this->profile->asset_id,
            'kind' => RentalReservationKind::Hard, 'status' => 'active',
            'starts_at' => Tz::parse('2026-10-18 15:00'), 'ends_at' => Tz::parse('2026-10-20 10:00'), 'buffer_after_hours' => 8,
        ]);
        $this->assertFalse($this->available('2026-10-15 15:00', '2026-10-18 10:00'));
        $this->assertFalse($this->available('2026-10-20 15:00', '2026-10-23 10:00'));
    }

    public function test_nightly_price_uses_calendar_nights_across_dst_and_late_checkout(): void {
        $card = RentalRateCard::query()->create(['organization_id' => $this->organization->id, 'name' => 'Nächte', 'version' => 1, 'status' => RentalRateCardStatus::Active, 'created_by' => $this->admin->id]);
        $card->items()->create(['organization_id' => $this->organization->id, 'kind' => 'daily_rate', 'label' => 'Übernachtung', 'amount' => '100.00', 'unit' => 'night']);
        $this->profile->update(['default_rate_card_id' => $card->id, 'check_in_time' => '10:00', 'check_out_time' => '15:00']);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.housing.index', ['arrival' => '2026-10-24', 'departure' => '2026-10-26']))->assertOk()->assertSee('200,00');
        $this->holiday(['starts_on' => '2026-10-24', 'ends_on' => '2026-10-26'])->assertSessionHasNoErrors();
        $booking = RentalRequest::query()->sole();
        $this->actingAs($this->admin);
        app(RentalRequestService::class)->accept($booking, $this->admin);
        $charges = app(\App\Services\Rental\RentalBillingService::class)->suggestCharges(RentalCase::query()->sole());
        $this->assertSame(2.0, $charges[0]['quantity']);
    }

    public function test_confirming_a_conflicting_long_term_request_keeps_it_pending(): void {
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.housing.store'), ['profile_id' => $this->profile->sqid, 'mode' => 'long_term', 'starts_on' => '2026-10-10'])->assertSessionHasNoErrors();
        $request = RentalTenancy::query()->sole();
        $this->tenancy();
        $this->actingAs($this->admin)->put(route('rental.housing.tenancies.update', $request), ['status' => 'active', 'starts_on' => '2026-10-10'])->assertSessionHasErrors('starts_on');
        $this->assertSame(RentalTenancyStatus::Requested, $request->refresh()->status);
    }

    public function test_long_term_only_units_cannot_be_booked_as_holiday_rentals(): void {
        $this->profile->update(['rental_mode' => 'long_term']);
        $this->holiday()->assertSessionHasErrors('mode');
        $this->assertSame(0, RentalCase::query()->count());
    }

    public function test_last_rental_day_keeps_its_full_length_at_the_dst_change(): void {
        RentalReservation::query()->create([
            'organization_id' => $this->organization->id, 'asset_id' => $this->profile->asset_id,
            'kind' => RentalReservationKind::Hard, 'status' => 'active',
            'starts_at' => Tz::parse('2026-10-25 23:30'), 'ends_at' => Tz::parse('2026-10-26 01:00'),
        ]);
        $this->actingAs($this->admin)->post(route('rental.housing.tenancies.store'), [
            'profile_id' => $this->profile->sqid, 'customer_id' => $this->customer->sqid,
            'starts_on' => '2026-10-10', 'ends_on' => '2026-10-25',
        ])->assertSessionHasErrors('starts_on');
    }

    public function test_checkin_uses_the_property_timezone_instead_of_personal_timezone(): void {
        $this->portalUser->update(['timezone' => 'Pacific/Honolulu']);
        $this->holiday()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-15 13:00', RentalRequest::query()->sole()->starts_at->format('Y-m-d H:i'));
    }

    public function test_stale_requests_cannot_withdraw_or_decline_a_confirmed_holiday_booking(): void {
        $this->holiday()->assertSessionHasNoErrors();
        $stale = RentalRequest::query()->sole();
        $this->actingAs($this->admin);
        $service = app(RentalRequestService::class);
        $service->accept($stale, $this->admin);
        foreach (['withdraw', 'decline'] as $action) {
            try {
                if ($action === 'withdraw') {
                    $service->withdrawFromPortal($stale, $this->portalUser);
                } else {
                    $service->decline($stale, $this->admin, 'Nicht verfügbar');
                }
                $this->fail('Eine bereits bestätigte Anfrage darf nicht erneut entschieden werden.');
            } catch (\RuntimeException) {
                $this->assertSame(RentalRequestStatus::Accepted, $stale->refresh()->status);
            }
        }
        $this->assertSame(1, RentalReservation::query()->active()->count());
    }

    public function test_portal_capability_is_required_for_housing(): void {
        $this->allowPortal($this->customer, []);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.housing.index'))->assertNotFound();
    }

    public function test_status_follows_the_transition_table_and_closed_tenancies_stay_unchanged(): void {
        $tenancy = $this->tenancy();
        $update = fn (array $data) => $this->actingAs($this->admin)->put(route('rental.housing.tenancies.update', $tenancy), $data + ['starts_on' => '2026-10-10']);

        $update(['status' => 'declined'])->assertSessionHasErrors('status');
        $this->assertSame(RentalTenancyStatus::Active, $tenancy->refresh()->status);
        $update(['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(RentalTenancyStatus::Cancelled, $tenancy->refresh()->status);
        $this->assertTrue($this->available('2035-08-01 15:00', '2035-08-05 10:00'), 'Storno gibt die Wohnung frei.');

        $update(['status' => 'cancelled', 'starts_on' => '2026-10-11'])->assertSessionHasErrors('status');
        $this->assertSame('2026-10-10', $tenancy->refresh()->starts_on->toDateString());
        $this->assertSame([], RentalTenancyStatus::Cancelled->allowedTransitions());
    }

    public function test_rent_is_shown_as_money_in_the_overview_and_the_portal(): void {
        $tenancy = $this->tenancy();
        $this->assertSame('950.00', $tenancy->totalRent()?->getAmount());
        $this->actingAs($this->admin)->get(route('rental.housing.index'))->assertOk()->assertSee('800,00 €')->assertSee('150,00 €');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.housing.index'))->assertOk()
            ->assertSee(__('housing.rent_display', ['rent' => '800,00 €', 'costs' => '150,00 €']))
            ->assertSee(\App\Enums\Rental\RentalMode::Both->label());
    }
}
