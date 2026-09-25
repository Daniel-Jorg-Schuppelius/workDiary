<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractPartnerType, ContractTermKind, IndexationMethod};
use App\Models\Contract\{Contract, ContractTemplate};
use App\Models\Platform\User;
use App\Services\Contract\{ContractService, ContractTemplateService};
use App\Services\Contract\Install\ContractTemplateInstallStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-893: Vertragsvorlagen aus einem Vertrag, Vorbelegung und Pflichten. */
final class ContractTemplateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function sampleContract(): Contract {
        $service = app(ContractService::class);
        $contract = $service->create($this->organization, $this->admin, [
            'title' => 'Wartung Heizung', 'kind' => ContractKind::Maintenance->value, 'partner_type' => ContractPartnerType::Other->value,
            'partner_name' => 'Muster', 'term_kind' => ContractTermKind::Fixed->value, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
            'notice_period_days' => 90, 'auto_renew' => true, 'renew_period_months' => 12, 'indexation_method' => IndexationMethod::None->value,
            'currency' => 'EUR', 'value_period' => 'yearly', 'value_amount' => 1200,
        ]);
        $service->addObligation($contract, ['kind' => ContractObligationKind::Review->value, 'title' => 'Jahreswartung', 'due_on' => '2026-06-01', 'warn_days_before' => 14, 'recurring' => true, 'recurrence_months' => 12]);

        return $contract;
    }

    public function test_contract_is_saved_as_template_and_prefills_a_new_contract_with_obligations(): void {
        $contract = $this->sampleContract();

        $this->actingAs($this->admin)->get(route('contracts.templates.from-contract.form', $contract))->assertOk();
        $this->actingAs($this->admin)->post(route('contracts.templates.from-contract', $contract), ['name' => 'Heizungswartung'])
            ->assertRedirect(route('contracts.show', $contract));

        $template = ContractTemplate::query()->where('name', 'Heizungswartung')->firstOrFail();
        $this->assertSame([['kind' => 'review', 'title' => 'Jahreswartung', 'offset_months' => 5, 'warn_days_before' => 14, 'recurring' => true, 'recurrence_months' => 12]], $template->obligations);

        $this->actingAs($this->admin)->get(route('contracts.create', ['template' => $template->sqid]))
            ->assertOk()
            ->assertSee('name="template_id" value="' . $template->sqid . '"', false)
            ->assertSee('Wartung Heizung');

        $this->actingAs($this->admin)->post(route('contracts.store'), [
            'template_id' => $template->sqid,
            'title' => 'Wartung Heizung Meier', 'kind' => ContractKind::Maintenance->value, 'partner_type' => ContractPartnerType::Other->value,
            'partner_name' => 'Meier', 'term_kind' => ContractTermKind::Fixed->value, 'starts_on' => '2026-10-01',
            'notice_period_days' => 90, 'indexation_method' => IndexationMethod::None->value, 'currency' => 'EUR', 'value_period' => 'yearly',
        ])->assertRedirect();

        $new = Contract::query()->where('title', 'Wartung Heizung Meier')->firstOrFail();
        $obligation = $new->obligations()->where('title', 'Jahreswartung')->firstOrFail();
        $this->assertSame('2027-03-01', $obligation->due_on->toDateString());
        $this->assertTrue($obligation->recurring);
    }

    public function test_template_list_and_branch_profile_install(): void {
        $step = app(ContractTemplateInstallStep::class);
        $rows = [['name' => 'Mietvertrag Gerät', 'kind' => 'rent', 'notice_period_days' => 30, 'obligations' => [['kind' => 'payment', 'title' => 'Miete prüfen', 'offset_months' => 1]]]];

        $this->assertSame(['created' => 1, 'skipped' => 0], $step->install($this->organization, $rows, $this->admin));
        $this->assertSame(['created' => 0, 'skipped' => 1], $step->install($this->organization, $rows, $this->admin));

        $template = ContractTemplate::query()->where('name', 'Mietvertrag Gerät')->firstOrFail();
        $this->assertSame(30, app(ContractTemplateService::class)->preset($template)['notice_period_days']);
        $this->actingAs($this->admin)->get(route('contracts.templates.index'))->assertOk()->assertSee('Mietvertrag Gerät');
        $this->actingAs($this->admin)->put(route('contracts.templates.update', $template), ['name' => 'Gerätemiete', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($template->fresh()->is_active);
    }
}
