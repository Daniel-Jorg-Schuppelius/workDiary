<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrectionServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Invoicing;

use App\Models\{Organization, TextCorrection};
use App\Services\Invoicing\TextCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wörterbuch-Anwendung: nur aktive Einträge der angefragten Organisation,
 * Map einmal je Request/Instanz (scoped Binding), null-durchlässig.
 */
class TextCorrectionServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_wendet_nur_aktive_eintraege_der_organisation_an(): void {
        TextCorrection::factory()->create(['organization_id' => $this->organization->id]);
        TextCorrection::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
            'wrong' => 'druchgeführt',
            'correct' => 'durchgeführt',
        ]);
        $foreignOrg = Organization::factory()->create();
        TextCorrection::factory()->create([
            'organization_id' => $foreignOrg->id,
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ]);

        $service = app(TextCorrectionService::class);
        $result = $service->apply('Serverwartunng druchgeführt und geprüfft', (int) $this->organization->id);

        $this->assertSame('Serverwartung druchgeführt und geprüfft', $result);
    }

    public function test_fremde_organisation_nutzt_ihre_eigene_map(): void {
        $foreignOrg = Organization::factory()->create();
        TextCorrection::factory()->create([
            'organization_id' => $foreignOrg->id,
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ]);

        $service = app(TextCorrectionService::class);

        $this->assertSame('geprüft', $service->apply('geprüfft', (int) $foreignOrg->id));
        $this->assertSame('geprüfft', $service->apply('geprüfft', (int) $this->organization->id));
    }

    public function test_null_und_leer_bleiben_unveraendert(): void {
        $service = app(TextCorrectionService::class);

        $this->assertNull($service->apply(null, (int) $this->organization->id));
        $this->assertSame('', $service->apply('', (int) $this->organization->id));
    }

    public function test_map_wird_je_instanz_nur_einmal_geladen(): void {
        $service = app(TextCorrectionService::class);
        $this->assertSame('unverändert', $service->apply('unverändert', (int) $this->organization->id));

        // Nach dem ersten Laden neu angelegte Einträge wirken erst im
        // nächsten Request (scoped Binding) — dokumentiertes Verhalten.
        TextCorrection::factory()->create(['organization_id' => $this->organization->id, 'wrong' => 'unverändert', 'correct' => 'geändert']);

        $this->assertSame('unverändert', $service->apply('unverändert', (int) $this->organization->id));
        $this->assertSame('geändert', (new TextCorrectionService)->apply('unverändert', (int) $this->organization->id));
    }
}
