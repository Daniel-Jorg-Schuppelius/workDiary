<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsExpansionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\{Exploitability, VulnerabilityStatus};
use App\Models\Isms\{IsmsAudit, IsmsAuditProgram, IsmsRequirement, IsmsVulnerability};
use App\Models\User;
use App\Services\Isms\{CsafFeedService, RequirementService, VexExportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Nachtrag 044: OSCAL-Katalog-Import (a), CSAF-Feed-Pull über ROLIE und
 * changes.csv (b), CSAF-VEX-Generator (c) und mehrjähriges Auditprogramm (d).
 * (e — SBOM 1.6 + Graph — deckt SbomGeneratorTest ab.)
 */
class IsmsExpansionTest extends TestCase {
    use RefreshDatabase;

    private function fixture(string $name): string {
        return (string) file_get_contents(base_path('tests/Fixtures/Isms/' . $name));
    }

    private function admin(): User {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        return $admin;
    }

    public function test_oscal_import_creates_requirements_with_prose(): void {
        $admin = $this->admin();

        $result = app(RequirementService::class)->importOscalCatalog($admin, $this->fixture('oscal-catalog.json'));

        $this->assertSame('BSI Stand der Technik', $result['norm']);
        $this->assertSame('2025-09', $result['edition']);
        $this->assertSame(3, $result['created']); // SdT-01 + verschachteltes SdT-01.1 + SdT-99

        $requirement = IsmsRequirement::query()->where('ref_no', 'SdT-01')->firstOrFail();
        $this->assertStringContainsString('TLS 1.2', (string) $requirement->getAttribute('description'));

        // Idempotenz: erneuter Import legt nichts doppelt an.
        $again = app(RequirementService::class)->importOscalCatalog($admin, $this->fixture('oscal-catalog.json'));
        $this->assertSame(0, $again['created']);
    }

    public function test_oscal_import_rejects_invalid_document(): void {
        $admin = $this->admin();

        $this->expectException(ValidationException::class);
        app(RequirementService::class)->importOscalCatalog($admin, '{"catalog": {"groups": []}}');
    }

    public function test_csaf_feed_pull_via_rolie_and_directory(): void {
        Storage::fake('local');
        $admin = $this->admin();

        $csafDocument = $this->fixture('csaf-known-affected.json');

        FakePluginHttp::fake(['*' => function (RequestInterface $request) use ($csafDocument) {
            $url = (string) $request->getUri();

            if (str_contains($url, 'provider-metadata.json')) {
                return FakePluginHttp::response([
                    'distributions' => [
                        ['rolie' => ['feeds' => [['url' => 'https://provider.test/feed.json']]]],
                        ['directory_url' => 'https://provider.test/dir'],
                    ],
                ]);
            }
            if (str_contains($url, 'feed.json')) {
                return FakePluginHttp::response([
                    'feed' => ['entry' => [[
                        'updated' => '2026-07-01T00:00:00Z',
                        'content' => ['src' => 'https://provider.test/docs/adv-rolie.json'],
                    ]]],
                ]);
            }
            if (str_contains($url, 'changes.csv')) {
                return FakePluginHttp::response("\"2026/adv-dir.json\",\"2026-06-30T00:00:00Z\"\n");
            }
            if (str_contains($url, 'adv-rolie.json') || str_contains($url, 'adv-dir.json')) {
                return FakePluginHttp::response($csafDocument);
            }

            return FakePluginHttp::response([], 404);
        }]);

        $result = app(CsafFeedService::class)->pull('https://provider.test/.well-known/csaf/provider-metadata.json', $admin->organization, $admin);

        // Beide Verteilungswege gefunden; identischer Inhalt → 1 Import + 1 Duplikat (file_hash).
        $this->assertSame(2, $result['documents']);
        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['errors']);
        $this->assertSame(1, \App\Models\Isms\IsmsAdvisory::query()->count());
    }

    public function test_vex_export_meets_profile_requirements(): void {
        $admin = $this->admin();

        IsmsVulnerability::query()->create([
            'organization_id' => $admin->organization_id,
            'vuln_no' => 1,
            'severity' => 'high',
            'identifier' => 'CVE-2026-11111',
            'title' => 'Parser-Schwachstelle',
            'affected_component' => 'libfoo 1.0.0',
            'status' => VulnerabilityStatus::NotAffected->value,
            'exploitability' => Exploitability::NotExploitable->value,
            'exploitability_note' => 'Verwundbarer Parser wird nicht ausgeliefert.',
            'source' => 'manual',
        ]);
        IsmsVulnerability::query()->create([
            'organization_id' => $admin->organization_id,
            'vuln_no' => 2,
            'severity' => 'medium',
            'identifier' => 'WID-2026-0001',
            'title' => 'Offene Plattformlücke',
            'affected_component' => 'libbar 2.0.0',
            'status' => VulnerabilityStatus::Open->value,
            'exploitability' => Exploitability::Unknown->value,
            'source' => 'manual',
        ]);

        $document = app(VexExportService::class)->generate($admin->organization);

        $this->assertSame('csaf_vex', $document['document']['category']);
        $this->assertNotEmpty($document['product_tree']['full_product_names']);

        $byId = collect($document['vulnerabilities'])->keyBy(fn(array $v): string => $v['cve'] ?? $v['ids'][0]['text']);
        // known_not_affected ⇒ Impact-Statement Pflicht.
        $notAffected = $byId['CVE-2026-11111'];
        $this->assertArrayHasKey('known_not_affected', $notAffected['product_status']);
        $this->assertSame('impact', $notAffected['threats'][0]['category']);
        // known_affected ⇒ Action-Statement Pflicht.
        $affected = $byId['WID-2026-0001'];
        $this->assertArrayHasKey('known_affected', $affected['product_status']);
        $this->assertNotEmpty($affected['remediations']);
    }

    public function test_norm_status_transition_freezes_profile_version(): void {
        $admin = $this->admin();
        $scope = app(\App\Services\Isms\ScopeService::class)->ensureDefaultScope((int) $admin->organization_id);

        $status = app(\App\Services\Isms\ConformityService::class)->create($admin, $scope, [
            'norm' => 'ISO/IEC 27001',
            'edition' => '2022',
        ]);
        $this->assertNull($status->profile_version);

        app(\App\Services\Isms\ConformityService::class)->transition(
            $status,
            \App\Enums\Isms\NormConformityStatus::GapAnalysisDone,
            $admin,
        );

        $status->refresh();
        $this->assertSame('1.0', $status->profile_version);
        $this->assertSame('2022-10-25', $status->profile_as_of?->toDateString());
    }

    public function test_audit_program_groups_audits_by_cycle_year(): void {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('isms.audit-programs.store'), [
            'name' => 'ISO-27001-Zyklus 2026–2028',
            'isms_scope_id' => app(\App\Services\Isms\ScopeService::class)->ensureDefaultScope((int) $admin->organization_id)->sqid,
            'norm' => 'ISO/IEC 27001',
            'edition' => '2022',
            'cycle_years' => 3,
            'starts_on' => '2026-01-01',
        ])->assertRedirect(route('isms.audit-programs.index'));

        $program = IsmsAuditProgram::query()->firstOrFail();

        // Audit im Jahr 2 anlegen und zuordnen.
        $audit = IsmsAudit::query()->create([
            'organization_id' => $admin->organization_id,
            'isms_scope_id' => $program->isms_scope_id,
            'audit_no' => 1,
            'title' => 'Überwachungsaudit',
            'kind' => 'internal',
            'status' => 'planned',
            'planned_on' => '2027-03-01',
        ]);
        $this->actingAs($admin)->put(route('isms.audit-programs.update', $program), [
            'attach_audit_id' => $audit->sqid,
        ])->assertRedirect();

        $byYear = $program->refresh()->auditsByCycleYear();
        $this->assertArrayHasKey(2, $byYear);
        $this->assertSame('Überwachungsaudit', $byYear[2][0]->title);

        // Seite rendert.
        $this->actingAs($admin)->get(route('isms.audit-programs.index'))
            ->assertOk()
            ->assertSee('ISO-27001-Zyklus 2026–2028');
    }
}
