<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\{Article, ManufacturingOrder, Organization, ProcedureMaterialRequirement, ProcedureTemplateVersion};
use App\Services\Manufacturing\{ManufacturingOrderService, MaterialDemandCalculator};
use CommonToolkit\Enums\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fertigungspfad (Feature 047, MVP-061/062): reproduzierbare, deterministische
 * Materialbedarfsberechnung (fest/pro Stück/Rezept + Verschnitt), Freigabe als
 * Snapshot und Statusmaschine.
 */
final class ManufacturingOrderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private MaterialDemandCalculator $calculator;
    private ManufacturingOrderService $service;
    private ProcedureTemplateVersion $version;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->calculator = app(MaterialDemandCalculator::class);
        $this->service = app(ManufacturingOrderService::class);
        $this->version = ProcedureTemplateVersion::factory()->create();
    }

    public function test_ratio_recipe_demand(): void {
        $water = $this->material();
        $powder = $this->material();
        ProcedureMaterialRequirement::factory()->ratio('1')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $water->id,
        ]);
        ProcedureMaterialRequirement::factory()->ratio('3')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $powder->id,
        ]);

        $lines = collect($this->calculator->calculate($this->bom(), '20'));
        $this->assertSame('5.0000', $lines->firstWhere(fn ($l) => $l['requirement']->article_id === $water->id)['demand']);
        $this->assertSame('15.0000', $lines->firstWhere(fn ($l) => $l['requirement']->article_id === $powder->id)['demand']);
    }

    public function test_per_unit_demand(): void {
        ProcedureMaterialRequirement::factory()->perUnit('2')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $this->material()->id,
        ]);

        $this->assertSame('20.0000', collect($this->calculator->calculate($this->bom(), '10'))->first()['demand']);
    }

    public function test_fixed_demand_is_independent_of_target(): void {
        ProcedureMaterialRequirement::factory()->fixed('5')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $this->material()->id,
        ]);

        $this->assertSame('5.0000', collect($this->calculator->calculate($this->bom(), '10'))->first()['demand']);
    }

    public function test_waste_surcharge_is_applied(): void {
        ProcedureMaterialRequirement::factory()->perUnit('1')->withWaste('10')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $this->material()->id,
        ]);

        $this->assertSame('110.0000', collect($this->calculator->calculate($this->bom(), '100'))->first()['demand']);
    }

    public function test_rounding_mode_rounds_to_whole_units(): void {
        // Bedarf 2,5 × 3 = 7,5 → Ceil rundet auf ganze Einheit auf, Floor ab,
        // ohne Modus bleibt SCALE-genau.
        $cases = [
            [RoundingMode::Ceil, '8.0000'],
            [RoundingMode::Floor, '7.0000'],
            [null, '7.5000'],
        ];

        foreach ($cases as [$mode, $expected]) {
            $req = ProcedureMaterialRequirement::factory()->perUnit('2.5')->make([
                'procedure_template_version_id' => $this->version->id,
                'article_id' => $this->material()->id,
                'rounding' => $mode,
            ]);

            $demand = $this->calculator->calculate(collect([$req]), '3')[0]['demand'];
            $this->assertSame($expected, $demand, sprintf('rounding=%s', $mode?->value ?? 'null'));
        }
    }

    public function test_release_freezes_workplan_and_creates_material_snapshot(): void {
        ProcedureMaterialRequirement::factory()->perUnit('2')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $this->material()->id,
        ]);
        $product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_procedure_template_version_id' => $this->version->id,
        ]);
        $order = $this->service->createDraft($this->organization, $product, null, '10', 'Stk');
        $this->assertStringStartsWith('FA-', (string) $order->number);

        $this->service->release($order);

        $this->assertSame(ManufacturingOrderStatus::Released, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->released_at);
        $this->assertSame($this->version->id, $order->fresh()->procedure_template_version_id);
        $this->assertCount(1, $order->fresh()->materials);
        $this->assertSame('20.0000', $order->materials()->first()->target_qty);
        $this->assertCount(1, (array) $order->fresh()->bom_snapshot);
    }

    public function test_start_execution_creates_and_links_procedure_run(): void {
        ProcedureMaterialRequirement::factory()->perUnit('1')->create([
            'procedure_template_version_id' => $this->version->id, 'article_id' => $this->material()->id,
        ]);
        $product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_procedure_template_version_id' => $this->version->id,
        ]);
        $order = $this->service->release($this->service->createDraft($this->organization, $product, null, '5', 'Stk'));
        $user = \App\Models\User::factory()->create(['organization_id' => $this->organization->id]);

        $this->service->startExecution($order, $user->id);

        $fresh = $order->fresh();
        $this->assertSame(ManufacturingOrderStatus::InProgress, $fresh->status);
        $this->assertNotNull($fresh->procedure_run_id);
        $this->assertDatabaseHas('procedure_runs', ['id' => $fresh->procedure_run_id]);
    }

    public function test_release_without_workplan_throws(): void {
        $product = Article::factory()->create(['organization_id' => $this->organization->id]);
        $order = $this->service->createDraft($this->organization, $product, null, '5', 'Stk');

        $this->expectException(RuntimeException::class);
        $this->service->release($order);
    }

    public function test_status_machine_enforces_transitions(): void {
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ManufacturingOrderStatus::Released->value,
        ]);

        $this->service->transition($order, ManufacturingOrderStatus::InProgress);
        $this->service->transition($order, ManufacturingOrderStatus::Completed);
        $this->assertNotNull($order->fresh()->completed_at);

        // Abgeschlossen ist terminal.
        $this->expectException(RuntimeException::class);
        $this->service->transition($order->fresh(), ManufacturingOrderStatus::InProgress);
    }

    public function test_draft_cannot_jump_to_completed(): void {
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ManufacturingOrderStatus::Draft->value,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->transition($order, ManufacturingOrderStatus::Completed);
    }

    public function test_orders_are_isolated_per_organization(): void {
        $product = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->service->createDraft($this->organization, $product, null, '1', 'Stk');
        $this->assertSame(1, ManufacturingOrder::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, ManufacturingOrder::query()->count());
    }

    private function material(): Article {
        return Article::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @return Collection<int, ProcedureMaterialRequirement> */
    private function bom(): Collection {
        return ProcedureMaterialRequirement::query()
            ->where('procedure_template_version_id', $this->version->id)
            ->orderBy('position')->get();
    }
}
