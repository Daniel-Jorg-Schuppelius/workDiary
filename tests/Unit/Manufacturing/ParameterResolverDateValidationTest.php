<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParameterResolverDateValidationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Manufacturing;

use App\Models\{ProcedureParameterDefinition, ProcedureTemplateVersion};
use App\Services\Manufacturing\ParameterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * A3 (Toolkit-Konsolidierung): Date-Parameter validieren über
 * DateHelper::isDate — nur echte Datumsangaben, keine relativen
 * strtotime-Ausdrücke wie "tomorrow" oder "+1 week".
 */
final class ParameterResolverDateValidationTest extends TestCase {
    use RefreshDatabase;

    private ParameterResolver $resolver;

    private ProcedureTemplateVersion $version;

    protected function setUp(): void {
        parent::setUp();
        $this->resolver = new ParameterResolver;
        $this->version = ProcedureTemplateVersion::factory()->create();

        ProcedureParameterDefinition::query()->create([
            'procedure_template_version_id' => $this->version->id,
            'code' => 'due_date',
            'label' => 'Fälligkeit',
            'type' => 'date',
            'constraints' => ['required' => true],
            'position' => 0,
        ]);
    }

    public function test_accepts_iso_date(): void {
        $snapshot = $this->resolver->snapshot($this->version, ['due_date' => '2026-07-02']);

        $this->assertSame('2026-07-02', $snapshot[0]['value']);
    }

    public function test_accepts_german_date(): void {
        $snapshot = $this->resolver->snapshot($this->version, ['due_date' => '02.07.2026']);

        $this->assertSame('02.07.2026', $snapshot[0]['value']);
    }

    public function test_rejects_relative_expression_tomorrow(): void {
        $this->expectException(RuntimeException::class);
        $this->resolver->snapshot($this->version, ['due_date' => 'tomorrow']);
    }

    public function test_rejects_relative_expression_plus_one_week(): void {
        $this->expectException(RuntimeException::class);
        $this->resolver->snapshot($this->version, ['due_date' => '+1 week']);
    }

    public function test_rejects_garbage(): void {
        $this->expectException(RuntimeException::class);
        $this->resolver->snapshot($this->version, ['due_date' => 'kein Datum']);
    }

    public function test_rejects_non_scalar_value(): void {
        $this->expectException(RuntimeException::class);
        $this->resolver->snapshot($this->version, ['due_date' => ['2026-07-02']]);
    }
}
