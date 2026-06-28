<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureParameterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\{Article, ManufacturingOrder, ProcedureParameterDefinition, ProcedureTemplateVersion};
use App\Services\Manufacturing\ManufacturingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-061: typisierte Auftragsparameter werden bei der Freigabe vollständig
 * eingefroren (Akzeptanzkriterium P2) und gegen ihre Constraints validiert.
 */
final class ProcedureParameterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ManufacturingOrderService $service;

    private ProcedureTemplateVersion $version;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->service = app(ManufacturingOrderService::class);
        $this->version = ProcedureTemplateVersion::factory()->create();
    }

    /** @param  array<string, mixed>  $constraints */
    private function parameter(string $code, string $type, array $constraints, int $position = 0): void {
        ProcedureParameterDefinition::query()->create([
            'procedure_template_version_id' => $this->version->id,
            'code' => $code,
            'label' => ucfirst($code),
            'type' => $type,
            'constraints' => $constraints,
            'position' => $position,
        ]);
    }

    private function order(array $parameters = []): ManufacturingOrder {
        $product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_procedure_template_version_id' => $this->version->id,
        ]);

        return $this->service->createDraft($this->organization, $product, null, '10', 'Stk', ['parameters' => $parameters]);
    }

    public function test_release_freezes_parameter_snapshot_with_values_and_defaults(): void {
        $this->parameter('temperature', 'measure', ['required' => true, 'unit' => '°C', 'min' => 0, 'max' => 100], 0);
        $this->parameter('mode', 'choice', ['options' => ['auto', 'manual'], 'default' => 'auto'], 1);

        $order = $this->order(['temperature' => 80]);
        $this->service->release($order);

        $snapshot = $order->fresh()->parameter_snapshot;
        $this->assertCount(2, $snapshot);

        $temperature = collect($snapshot)->firstWhere('code', 'temperature');
        $this->assertSame(80, $temperature['value']);
        $this->assertSame('°C', $temperature['unit']);
        $this->assertSame('measure', $temperature['type']);

        // Nicht gesetzter Parameter fällt auf den Default zurück.
        $this->assertSame('auto', collect($snapshot)->firstWhere('code', 'mode')['value']);
    }

    public function test_release_rejects_missing_required_parameter(): void {
        $this->parameter('temperature', 'measure', ['required' => true, 'unit' => '°C']);
        $order = $this->order();

        try {
            $this->service->release($order);
            $this->fail('Erwartete RuntimeException wegen fehlendem Pflichtparameter.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->assertSame(ManufacturingOrderStatus::Draft, $order->fresh()->status);
        $this->assertNull($order->fresh()->parameter_snapshot);
    }

    public function test_release_rejects_value_out_of_range(): void {
        $this->parameter('temperature', 'measure', ['required' => true, 'min' => 0, 'max' => 100]);
        $order = $this->order(['temperature' => 150]);

        $this->expectException(RuntimeException::class);
        $this->service->release($order);
    }

    public function test_release_rejects_invalid_choice(): void {
        $this->parameter('mode', 'choice', ['required' => true, 'options' => ['auto', 'manual']]);
        $order = $this->order(['mode' => 'turbo']);

        $this->expectException(RuntimeException::class);
        $this->service->release($order);
    }

    public function test_inactive_parameters_are_excluded_from_snapshot(): void {
        $this->parameter('mode', 'choice', ['options' => ['auto'], 'default' => 'auto']);
        ProcedureParameterDefinition::query()->create([
            'procedure_template_version_id' => $this->version->id,
            'code' => 'legacy', 'label' => 'Legacy', 'type' => 'text',
            'constraints' => ['default' => 'x'], 'position' => 9, 'active' => false,
        ]);

        $order = $this->order();
        $this->service->release($order);

        $snapshot = $order->fresh()->parameter_snapshot;
        $this->assertCount(1, $snapshot);
        $this->assertSame('mode', $snapshot[0]['code']);
    }
}
