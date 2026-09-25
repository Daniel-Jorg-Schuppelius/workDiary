<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractCostCenterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractKind, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Models\Finance\CostCenter;
use App\Models\Platform\User;
use App\Services\Contract\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-894: Vertragswerte je Kostenstelle. */
final class ContractCostCenterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_running_contract_values_are_normalised_per_cost_center(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $center = CostCenter::query()->create(['organization_id' => $this->organization->id, 'code' => 'KST-10', 'label' => 'Werkstatt', 'active' => true]);
        $service = app(ContractService::class);
        $base = ['kind' => ContractKind::Rent->value, 'partner_type' => ContractPartnerType::Other->value, 'partner_name' => 'X', 'term_kind' => ContractTermKind::OpenEnded->value, 'starts_on' => '2026-01-01', 'indexation_method' => IndexationMethod::None->value, 'currency' => 'EUR'];
        foreach ([['Hallenmiete', 1000, 'monthly', $center->id, ContractStatus::Active], ['Versicherung', 1200, 'yearly', $center->id, ContractStatus::Terminated], ['Einrichtung', 500, 'once', $center->id, ContractStatus::Active], ['Entwurf', 999, 'monthly', $center->id, ContractStatus::Draft], ['Telefon', 50, 'monthly', null, ContractStatus::Active]] as [$title, $value, $period, $cc, $status]) {
            $contract = $service->create($this->organization, $admin, $base + ['title' => $title, 'value_amount' => $value, 'value_period' => $period, 'cost_center_id' => $cc]);
            $contract->forceFill(['status' => $status->value])->save();
        }
        $this->actingAs($admin);

        $rows = $service->valueByCostCenter();

        $this->assertSame(['KST-10', null], array_map(static fn (array $r): ?string => $r['cost_center']?->code, $rows));
        $this->assertSame([3, 13200.0, 1100.0, 500.0], [$rows[0]['count'], $rows[0]['yearly'], $rows[0]['monthly'], $rows[0]['once']]);
        $this->assertSame(600.0, $rows[1]['yearly']);

        $this->get(route('contracts.cost-centers'))->assertOk()->assertSee('Werkstatt')->assertSee('13.200,00');
        $this->get(route('contracts.create'))->assertOk()->assertSee('name="cost_center_id"', false);
    }
}
