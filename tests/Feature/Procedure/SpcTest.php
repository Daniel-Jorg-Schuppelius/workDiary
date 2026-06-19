<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SpcTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureStepRunStatus, ProcedureStepType};
use App\Models\{ProcedureRun, ProcedureStepDef, ProcedureStepRun, ProcedureTemplateVersion};
use App\Services\Procedure\SpcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * SPC für Mess-Schritte (Feature 047/048, E7): Aggregation der Messreihen-Werte
 * zu Kennzahlen, Prozessfähigkeit (Cp/Cpk) und Toleranzverletzungen.
 */
final class SpcTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_analyzes_measurement_series(): void {
        $this->setUpOrganization();
        $def = $this->measurementStep(['lsl' => 9, 'usl' => 11, 'nominal' => 10]);
        foreach ([[10, 10.5], [9.5, 10.2, 9.8]] as $series) {
            $this->record($def, $series);
        }

        $result = app(SpcService::class)->analyzeStep($def);

        $this->assertNotNull($result);
        $this->assertSame(5, $result['count']);
        $this->assertSame(10.0, $result['mean']);
        $this->assertSame(9.5, $result['min']);
        $this->assertSame(10.5, $result['max']);
        $this->assertSame(0, $result['out_of_spec']);
        $this->assertNotNull($result['cpk']);
        $this->assertGreaterThan(0.9, $result['cpk']);
    }

    public function test_counts_out_of_spec(): void {
        $this->setUpOrganization();
        $def = $this->measurementStep(['lsl' => 9, 'usl' => 11]);
        $this->record($def, [10, 10, 12]); // 12 > USL

        $result = app(SpcService::class)->analyzeStep($def);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['out_of_spec']);
    }

    /** @param array<string, mixed> $config */
    private function measurementStep(array $config): ProcedureStepDef {
        return ProcedureStepDef::query()->create([
            'procedure_template_version_id' => ProcedureTemplateVersion::factory()->create()->id,
            'sort_order' => 1, 'code' => 'M1', 'step_type' => ProcedureStepType::Messreihe->value,
            'label' => 'Durchmesser', 'config' => $config,
        ]);
    }

    /** @param list<float|int> $values Eine Ausführung (eigener ProcedureRun) mit Messwerten. */
    private function record(ProcedureStepDef $def, array $values): void {
        ProcedureStepRun::query()->create([
            'procedure_run_id' => ProcedureRun::factory()->create()->id,
            'procedure_step_def_id' => $def->id,
            'status' => ProcedureStepRunStatus::Done->value, 'value_json' => ['values' => $values],
        ]);
    }
}
