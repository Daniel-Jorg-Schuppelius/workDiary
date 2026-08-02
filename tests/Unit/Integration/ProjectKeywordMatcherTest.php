<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectKeywordMatcherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Integration;

use App\Enums\Project\ProjectStatus;
use App\Models\{Customer, ForeignCustomer, Organization, Project};
use App\Services\Integration\ProjectKeywordMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Schlüsselwort-Zuordnung importierter Zeiten (MVP-483).
 */
class ProjectKeywordMatcherTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private ProjectKeywordMatcher $matcher;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->matcher = app(ProjectKeywordMatcher::class);
    }

    /** @param array<string, mixed> $attributes */
    private function project(string $name, array $attributes = []): Project {
        return Project::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => $name,
            'is_default' => false,
        ], $attributes));
    }

    public function test_projektname_im_text_ordnet_zu(): void {
        $datev = $this->project('DATEV');
        $this->project('Netzwerk');

        $hit = $this->matcher->match($this->organization, $this->customer, 'Installation DATEV-Updates');

        $this->assertNotNull($hit);
        $this->assertSame($datev->id, $hit->project->id);
        $this->assertSame('datev', $hit->keyword);
        $this->assertFalse($hit->explicit);
    }

    public function test_projektname_trifft_auch_ohne_worttrennung(): void {
        $datev = $this->project('DATEV');

        $hit = $this->matcher->match($this->organization, $this->customer, 'DATEV Hotfixinstallation');
        $this->assertSame($datev->id, $hit?->project->id);

        $hit = $this->matcher->match($this->organization, $this->customer, 'installation datevupdates');
        $this->assertSame($datev->id, $hit?->project->id);
    }

    public function test_gepflegtes_synonym_trifft(): void {
        $lodas = $this->project('LODAS', ['keywords' => ['lohn', 'gehaltsabrechnung']]);
        $this->project('Netzwerk');

        $hit = $this->matcher->match($this->organization, $this->customer, 'Monatliche Lohnbuchhaltung geprüft');

        $this->assertSame($lodas->id, $hit?->project->id);
        $this->assertTrue($hit?->explicit);
    }

    public function test_zwei_gleich_gute_treffer_bleiben_ohne_zuordnung(): void {
        $this->project('Umbau');
        $this->project('Anbau');

        $hit = $this->matcher->match($this->organization, $this->customer, 'Umbau und Anbau besprochen');

        $this->assertNull($hit);
    }

    public function test_laengerer_treffer_gewinnt(): void {
        $this->project('Netz');
        $netzwerk = $this->project('Netzwerkumbau');

        $hit = $this->matcher->match($this->organization, $this->customer, 'Arbeiten am Netzwerkumbau');

        $this->assertSame($netzwerk->id, $hit?->project->id);
    }

    public function test_stoppwort_und_mindestlaenge_greifen_nicht(): void {
        $this->project('Support');
        $this->project('SQL');

        $this->assertNull($this->matcher->match($this->organization, $this->customer, 'Support geleistet'));
        $this->assertNull($this->matcher->match($this->organization, $this->customer, 'SQL-Abfrage geprüft'));
    }

    public function test_standardprojekt_und_archivierte_bleiben_aussen_vor(): void {
        $this->project('DATEV', ['is_default' => true]);
        $this->assertNull($this->matcher->match($this->organization, $this->customer, 'DATEV Update'));

        $this->project('Archivprojekt', ['archived_at' => now()]);
        $this->assertNull($this->matcher->match($this->organization, $this->customer, 'Arbeiten am Archivprojekt'));

        $this->project('Ruheprojekt', ['status' => ProjectStatus::Archived]);
        $this->assertNull($this->matcher->match($this->organization, $this->customer, 'Arbeiten am Ruheprojekt'));
    }

    public function test_projekt_eines_anderen_kunden_trifft_nicht(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
            'name' => 'DATEV',
            'is_default' => false,
        ]);

        $this->assertNull($this->matcher->match($this->organization, $this->customer, 'DATEV Update eingespielt'));
    }

    public function test_endkunde_scopet_auf_seine_projekte(): void {
        $foreign = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $foreignProject = $this->project('DATEV', ['foreign_customer_id' => $foreign->id]);
        $companyProject = $this->project('DATEV Firma');

        $this->assertSame($foreignProject->id, $this->matcher->match($this->organization, $foreign, 'DATEV Update')?->project->id);
        // Ohne Endkunden-Kontext bleiben dessen Projekte außen vor.
        $this->assertSame($companyProject->id, $this->matcher->match($this->organization, $this->customer, 'DATEV Firma Update')?->project->id);
    }

    public function test_ohne_kunden_kontext_kein_treffer(): void {
        $this->project('DATEV');

        $this->assertNull($this->matcher->match($this->organization, null, 'DATEV Update'));
    }

    public function test_abgeschalteter_schalter_verhindert_zuordnung(): void {
        $this->project('DATEV');
        $this->organization->update(['settings' => ['project' => ['keyword_matching' => ['enabled' => false]]]]);

        $organization = Organization::query()->findOrFail($this->organization->id);

        $this->assertNull($this->matcher->match($organization, $this->customer, 'DATEV Update'));
    }
}
