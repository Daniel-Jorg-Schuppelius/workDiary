<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAdvisoryImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\{AdvisoryFormat, Exploitability, IncidentSeverity, VulnerabilitySource, VulnerabilityStatus};
use App\Models\Isms\{IsmsAdvisory, IsmsSoftwareProduct, IsmsVulnerability};
use App\Models\User;
use App\Services\Isms\AdvisoryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IsmsAdvisoryImportTest extends TestCase {
    use RefreshDatabase;

    private function fixture(string $name): string {
        return (string) file_get_contents(base_path('tests/Fixtures/Isms/' . $name));
    }

    public function test_csaf_known_affected_creates_vulnerability_not_auto_exploitable_and_links_inventory(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $product = IsmsSoftwareProduct::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'libfoo',
            'product_version' => '1.0.0',
        ]);

        $advisory = app(AdvisoryImportService::class)->importCsaf(
            $this->fixture('csaf-known-affected.json'),
            $admin->organization,
            $admin,
            AdvisoryFormat::Csaf,
        );

        $this->assertSame(1, $advisory->vuln_count);

        $vuln = IsmsVulnerability::query()->where('identifier', 'CVE-2026-50001')->firstOrFail();
        $this->assertSame(IncidentSeverity::Critical, $vuln->severity);
        $this->assertSame(VulnerabilityStatus::Open, $vuln->status);
        // 044-Kernregel: NICHT automatisch „ausnutzbar".
        $this->assertSame(Exploitability::UnderInvestigation, $vuln->exploitability);
        $this->assertSame(VulnerabilitySource::AdvisoryImport, $vuln->source);
        // Inventar-Verknüpfung über name/version.
        $this->assertSame($product->id, $vuln->isms_software_product_id);

        // Original-Advisory mit Hash gespeichert.
        $this->assertNotEmpty($advisory->file_hash);
        Storage::disk('local')->assertExists($advisory->file_path);
    }

    public function test_vex_known_not_affected_sets_not_affected_with_reason(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $advisory = app(AdvisoryImportService::class)->importCsaf(
            $this->fixture('vex-not-affected.json'),
            $admin->organization,
            $admin,
            AdvisoryFormat::Vex,
        );

        $this->assertSame(1, $advisory->vuln_count);

        $vuln = IsmsVulnerability::query()->where('identifier', 'CVE-2026-50002')->firstOrFail();
        $this->assertSame(VulnerabilityStatus::NotAffected, $vuln->status);
        $this->assertSame(Exploitability::NotExploitable, $vuln->exploitability);
        // VEX-Begründung als Pflichtnotiz übernommen.
        $this->assertNotNull($vuln->exploitability_note);
        $this->assertStringContainsString('Parser', (string) $vuln->exploitability_note);
    }

    public function test_reimport_is_idempotent(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $service = app(AdvisoryImportService::class);
        $content = $this->fixture('csaf-known-affected.json');

        $first = $service->importCsaf($content, $admin->organization, $admin);
        $second = $service->importCsaf($content, $admin->organization, $admin);

        // Dieselbe Datei legt keine zweite Advisory-Zeile und keine Dubletten an.
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, IsmsAdvisory::query()->count());
        $this->assertSame(1, IsmsVulnerability::query()->count());
    }

    public function test_invalid_json_is_rejected(): void {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AdvisoryImportService::class)->importCsaf('{ not json', $admin->organization, $admin);
    }
}
