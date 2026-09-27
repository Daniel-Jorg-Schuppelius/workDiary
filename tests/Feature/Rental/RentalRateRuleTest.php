<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRateRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Rental\{RentalChargeKind, RentalRateCardStatus};
use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Models\Rental\{RentalProfile, RentalRateCard, RentalRateRule};
use App\Services\Rental\RentalBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-950: Mietpreisregeln und Zuschläge je Tag. */
final class RentalRateRuleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_rules_and_day_based_surcharges(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $card = RentalRateCard::query()->create(['organization_id' => $this->organization->id, 'name' => 'Standard', 'version' => 1, 'status' => RentalRateCardStatus::Draft, 'created_by' => $admin->id]);
        $card->items()->create(['organization_id' => $this->organization->id, 'kind' => RentalChargeKind::DailyRate, 'label' => 'Tagesmiete', 'amount' => '100', 'unit' => 'day']);
        $card->items()->create(['organization_id' => $this->organization->id, 'kind' => RentalChargeKind::WeekendSurcharge, 'label' => 'Wochenende', 'amount' => '20', 'unit' => 'day']);

        $this->actingAs($admin)->post(route('rental.rates.rules.store', $card), ['kind' => 'season', 'label' => 'Hochsaison', 'valid_from' => '2026-10-01', 'valid_until' => '2026-10-31', 'adjust_percent' => '10'])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('rental.rates.rules.store', $card), ['kind' => 'weekday', 'label' => 'Wochenendrabatt', 'weekdays' => [6, 7], 'adjust_percent' => '-20'])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('rental.rates.rules.store', $card), ['kind' => 'weekday', 'label' => 'x', 'adjust_percent' => '5'])->assertSessionHasErrors('weekdays');
        $this->assertSame(2, RentalRateRule::query()->count());
        $this->actingAs($admin)->get(route('rental.rates.index'))->assertOk()->assertSee('Hochsaison');

        $card->forceFill(['status' => RentalRateCardStatus::Active])->save();
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $profile = RentalProfile::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $asset->id, 'is_rentable' => true, 'default_rate_card_id' => $card->id]);

        // Fr 09.10. 08:00 bis Mo 12.10. 08:00: 3 Tage, davon Sa+So.
        $total = app(RentalBillingService::class)->estimate($profile->fresh(), Carbon::parse('2026-10-09 08:00'), Carbon::parse('2026-10-12 08:00'));
        // 3×100 + 2×20 Wochenende + 3×10 Saison − 2×20 Wochenendrabatt = 330
        $this->assertSame('330.00', $total?->getAmount());
    }
}
