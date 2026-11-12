<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalProtocolConditionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Rental;

use App\Enums\Rental\{RentalCaseStatus, RentalCondition, RentalReservationKind, RentalReservationStatus, RentalReturnFollowUp};
use App\Exceptions\AssetNotUsableException;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalProfile, RentalReservation, RentalReturnReport};
use App\Services\Rental\{RentalAvailabilityService, RentalCaseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1604: Hinweis bei weicher Vormerkung, Protokollpositionen aus dem
 * Formular, Mindestzustand des Verleihprofils und „Reinigung erforderlich“.
 */
final class RentalProtocolConditionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private Customer $customer;

    private Asset $asset;

    private RentalProfile $profile;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Rüttelplatte']);
        $this->profile = RentalProfile::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->asset->id,
            'is_rentable' => true,
            'buffer_after_hours' => 2,
            'accessories' => ['Transportrad', 'Gummimatte'],
            'checklist_labels' => ['Ölstand geprüft', 'Einweisung erfolgt'],
        ]);
    }

    private function openCase(?Carbon $from = null, ?Carbon $to = null): RentalCase {
        return app(RentalCaseService::class)->open($this->organization, $this->admin, [
            'customer_id' => $this->customer->id,
            'starts_at' => $from ?? now()->addDay()->setTime(8, 0),
            'ends_at' => $to ?? now()->addDays(3)->setTime(17, 0),
        ], [$this->asset->id]);
    }

    private function handedOverCase(): RentalCase {
        $case = $this->openCase();
        $service = app(RentalCaseService::class);
        $service->reserve($case, $this->admin);
        $service->handover($case->fresh(), $this->asset, $this->admin, ['condition' => 'good']);

        return $case->fresh();
    }

    public function test_soft_hold_of_another_case_warns_on_reserve_without_blocking(): void {
        $other = $this->openCase();
        app(RentalAvailabilityService::class)->createWindow(
            $this->asset,
            RentalReservationKind::Soft,
            Carbon::instance($other->starts_at),
            Carbon::instance($other->ends_at),
            'Vormerkung',
            $this->admin->id,
            $other->id,
        );
        $case = $this->openCase();

        $this->actingAs($this->admin)->get(route('rental.show', $case))
            ->assertOk()
            ->assertSee(__('rental.soft.title'))
            ->assertSee($other->number);

        $this->actingAs($this->admin)->post(route('rental.reserve', $case))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', trans_choice('rental.soft.reserved_warning', 1, ['count' => 1]));

        $this->assertSame(RentalCaseStatus::Reserved, $case->fresh()->status);
    }

    public function test_handover_form_stores_checklist_accessories_and_condition_items(): void {
        $case = $this->openCase();
        app(RentalCaseService::class)->reserve($case, $this->admin);

        $this->actingAs($this->admin)->get(route('rental.show', $case))
            ->assertOk()
            ->assertSee('Ölstand geprüft')
            ->assertSee('accessory_items[0][label]', false)
            ->assertSee('condition_items[0][label]', false);

        $this->actingAs($this->admin)->post(route('rental.handover', $case), [
            'asset_id' => $this->asset->sqid,
            'condition' => 'good',
            'checklist' => ['Ölstand geprüft'],
            'accessory_items' => [
                ['label' => 'Transportrad', 'quantity' => 1, 'present' => 1],
                ['label' => 'Gummimatte', 'quantity' => 2, 'present' => 0],
            ],
            'condition_items' => [
                ['label' => 'Bodenplatte', 'state' => 'worn', 'note' => 'Kratzer'],
                ['label' => '', 'state' => 'ok', 'note' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $report = $case->handoverReports()->firstOrFail();
        $this->assertSame(['done' => 1, 'total' => 2], $report->checklist?->progress());
        $this->assertSame(2, $report->accessoryItems()->count());
        $this->assertFalse((bool) $report->accessoryItems()->where('label', 'Gummimatte')->firstOrFail()->present);
        $this->assertSame(1, $report->conditionItems()->count());

        $this->actingAs($this->admin)->get(route('rental.show', $case))
            ->assertOk()
            ->assertSee('Bodenplatte')
            ->assertSee(__('rental.report.accessory_missing'));

        $portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        $this->actingAs($portalUser, 'customer')->get(route('customer.rentals.show', $case))
            ->assertOk()
            ->assertSee('Bodenplatte')
            ->assertDontSee('Kratzer');
    }

    public function test_return_form_stores_consumables_and_updates_the_profile_condition(): void {
        $case = $this->handedOverCase();

        $this->actingAs($this->admin)->post(route('rental.return', $case), [
            'asset_id' => $this->asset->sqid,
            'condition' => 'worn',
            'follow_up' => RentalReturnFollowUp::None->value,
            'consumables' => [
                ['label' => 'Diesel', 'quantity' => '12.5', 'unit' => 'l'],
                ['label' => '', 'quantity' => '', 'unit' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $report = RentalReturnReport::query()->firstOrFail();
        $this->assertSame([['label' => 'Diesel', 'quantity' => '12.5', 'unit' => 'l']], $report->consumables);
        $this->assertSame(RentalCondition::Worn, $this->profile->fresh()->current_condition);

        $this->actingAs($this->admin)->get(route('rental.show', $case))->assertOk()->assertSee('Diesel');
    }

    public function test_asset_below_the_minimum_condition_cannot_be_reserved(): void {
        $this->profile->forceFill(['min_condition' => 'good', 'current_condition' => 'worn'])->save();
        $case = $this->openCase();

        try {
            app(RentalCaseService::class)->reserve($case, $this->admin);
            $this->fail('Reservierung unter dem Mindestzustand muss scheitern.');
        } catch (AssetNotUsableException $e) {
            $this->assertStringContainsString(RentalCondition::Good->label(), $e->getMessage());
        }

        // Nach der Instandsetzung pflegt die Disposition den Zustand im Profil.
        $this->actingAs($this->admin)->put(route('rental.profiles.update', $this->profile), [
            'asset_id' => $this->asset->sqid,
            'is_rentable' => 1,
            'buffer_before_hours' => 0,
            'buffer_after_hours' => 2,
            'min_condition' => 'good',
            'current_condition' => 'good',
            'home_site_label' => 'Lager Nord',
            'checklist_labels' => "Ölstand geprüft\nEinweisung erfolgt",
        ])->assertSessionHasNoErrors();

        $profile = $this->profile->fresh();
        $this->assertSame('Lager Nord', $profile->home_site_label);
        $this->assertSame(['Ölstand geprüft', 'Einweisung erfolgt'], $profile->checklist_labels);
        $this->assertSame(RentalCaseStatus::Reserved, app(RentalCaseService::class)->reserve($case->fresh(), $this->admin)->status);
    }

    public function test_handover_checks_the_minimum_condition_again(): void {
        $this->profile->forceFill(['min_condition' => 'good', 'current_condition' => 'good'])->save();
        $case = $this->openCase();
        app(RentalCaseService::class)->reserve($case, $this->admin);

        // Seit der Reservierung ist der Zustand gesunken.
        $this->profile->forceFill(['current_condition' => RentalCondition::Worn->value])->save();
        $this->actingAs($this->admin)
            ->post(route('rental.handover', $case), ['asset_id' => $this->asset->sqid, 'condition' => 'worn'])
            ->assertSessionHasErrors('asset_id');
        $this->assertSame(0, $case->handoverReports()->count());
        $this->assertSame(RentalCaseStatus::Reserved, $case->fresh()->status);

        $this->profile->forceFill(['current_condition' => RentalCondition::Good->value])->save();
        app(RentalCaseService::class)->handover($case->fresh(), $this->asset, $this->admin, ['condition' => 'good']);
        $this->assertSame(RentalCaseStatus::HandedOver, $case->fresh()->status);
    }

    public function test_unknown_condition_does_not_block_and_invalid_minimum_is_rejected(): void {
        $this->profile->forceFill(['min_condition' => 'good'])->save();
        $this->assertTrue(app(RentalAvailabilityService::class)->isAvailable($this->asset, now()->addDay(), now()->addDays(2)));

        $this->actingAs($this->admin)->put(route('rental.profiles.update', $this->profile), [
            'asset_id' => $this->asset->sqid,
            'min_condition' => 'tadellos',
        ])->assertSessionHasErrors('min_condition');
    }

    public function test_cleaning_required_blocks_until_cleaning_is_marked_done(): void {
        $case = $this->handedOverCase();

        app(RentalCaseService::class)->returnAsset($case, $this->asset, $this->admin, [
            'condition' => 'good',
            'cleaning_required' => true,
            'follow_up' => RentalReturnFollowUp::None->value,
        ]);

        $window = RentalReservation::query()
            ->where('asset_id', $this->asset->id)
            ->where('kind', RentalReservationKind::Cleaning->value)
            ->firstOrFail();
        $this->assertSame(RentalReservationStatus::Active, $window->status);
        $availability = app(RentalAvailabilityService::class);
        $this->assertFalse($availability->isAvailable($this->asset, now()->addMinutes(10), now()->addMinutes(50)));

        $this->actingAs($this->admin)->get(route('rental.show', $case))
            ->assertOk()
            ->assertSee(__('rental.cleaning.mark_done'));

        $this->actingAs($this->admin)->post(route('rental.cleaning.done', $window))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('rental.cleaning.done'));

        $this->assertSame(RentalReservationStatus::Completed, $window->fresh()->status);
        $this->assertTrue($availability->isAvailable($this->asset, now()->addMinutes(10), now()->addMinutes(50)));

        $this->actingAs($this->admin)->post(route('rental.cleaning.done', $window))->assertSessionHasErrors('reservation');
    }
}
