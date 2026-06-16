<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RegisterExportServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Models\Isms\{IsmsRisk, IsmsRiskAssessment};
use App\Models\{Organization, User};
use App\Services\Isms\RegisterExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * ISMS-Registerexporte (Feature 044): maßgeblich ist die jüngste FREIGEGEBENE
 * Netto-Bewertung (Entwürfe/Brutto bleiben außen vor), strikte Mandantengrenze
 * (Org-Scope), und kanonische Serialisierung (JSON-meta + row_count, CSV mit
 * UTF-8-BOM, Semikolon und kommentiertem Kopf).
 */
final class RegisterExportServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private RegisterExportService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->service = new RegisterExportService;
    }

    public function test_risk_register_uses_only_approved_net_assessment_and_scopes_to_org(): void {
        $owner = User::factory()->create(['organization_id' => $this->organization->id]);

        $risk = IsmsRisk::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'OrgA-Serverausfall',
        ]);

        // Maßgeblich: freigegebene Netto-Bewertung mit Gültigkeit.
        IsmsRiskAssessment::factory()->net()->approved($owner->id)->create([
            'organization_id' => $this->organization->id,
            'isms_risk_id' => $risk->id,
            'valid_until' => '2027-01-01',
        ]);
        // Entwurf (Netto) mit anderer Gültigkeit ⇒ darf NICHT durchschlagen.
        IsmsRiskAssessment::factory()->net()->create([
            'organization_id' => $this->organization->id,
            'isms_risk_id' => $risk->id,
            'valid_until' => '2099-12-31',
        ]);

        // Fremd-Org-Risiko ⇒ darf im Export nicht erscheinen.
        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        IsmsRisk::factory()->create(['organization_id' => $orgB->id, 'title' => 'OrgB-Phishing']);
        app()->instance('currentOrganization', $this->organization);

        $register = $this->service->riskRegister();
        $titles = array_column($register['rows'], 'title');

        $this->assertContains('OrgA-Serverausfall', $titles);
        $this->assertNotContains('OrgB-Phishing', $titles, 'Mandantengrenze: Fremd-Org darf nicht exportiert werden');

        $row = collect($register['rows'])->firstWhere('title', 'OrgA-Serverausfall');
        $this->assertSame('2027-01-01', $row['assessment_valid_until'], 'nur die freigegebene Netto-Bewertung zählt');
    }

    public function test_to_json_carries_meta_and_row_count(): void {
        $actor = User::factory()->create(['organization_id' => $this->organization->id]);
        $register = [
            'columns' => ['no' => 'Nr.', 'title' => 'Titel'],
            'rows' => [
                ['no' => 'B-1', 'title' => 'Serverausfall'],
                ['no' => 'B-2', 'title' => 'Phishing'],
            ],
        ];

        $json = $this->service->toJson('risks', $actor, null, $register);
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(2, $data['meta']['row_count']);
        $this->assertSame($actor->name, $data['meta']['generated_by']);
        $this->assertArrayHasKey('generated_at', $data['meta']);
        $this->assertCount(2, $data['rows']);
        $this->assertSame('B-1', $data['rows'][0]['no']);
    }

    public function test_to_csv_has_bom_comment_header_and_rows(): void {
        $actor = User::factory()->create(['organization_id' => $this->organization->id]);
        $register = [
            'columns' => ['no' => 'Nr.', 'title' => 'Titel'],
            'rows' => [['no' => 'B-1', 'title' => 'Serverausfall']],
        ];

        $csv = $this->service->toCsv('risks', $actor, null, $register);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'CSV muss mit UTF-8-BOM beginnen');
        $this->assertStringContainsString('# ', $csv, 'kommentierter Kopf (Datenstand)');
        $this->assertStringContainsString('Nr.', $csv);
        $this->assertStringContainsString('Titel', $csv);
        $this->assertStringContainsString('B-1', $csv);
        $this->assertStringContainsString('Serverausfall', $csv);
        $this->assertStringEndsWith("\r\n", $csv);
    }

    public function test_filename_pattern(): void {
        $this->assertMatchesRegularExpression(
            '/^isms-risks-\d{8}_\d{6}\.csv$/',
            $this->service->filename('risks', 'csv'),
        );
    }
}
