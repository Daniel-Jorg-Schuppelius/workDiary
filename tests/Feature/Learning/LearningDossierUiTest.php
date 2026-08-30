<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDossierUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\{AuditLog, Qualification, User, UserQualification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachweismappe als Oberfläche und Ausgabe (Feature 149, MVP-750).
 *
 * Die Regel, die hier geprüft wird: **aggregiert ist die Vorgabe.** Eine
 * namentliche Auskunft ist eine Weitergabe personenbezogener Daten — sie
 * braucht einen Anlass und wird protokolliert.
 */
class LearningDossierUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function workerWithProof(string $name, ?string $validUntil): User {
        $user = User::factory()->aussendienst()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
        ]);

        $qualification = Qualification::query()->firstOrCreate(
            ['organization_id' => $this->organization->id, 'name' => 'PSA gegen Absturz'],
            ['is_active' => true]
        );

        UserQualification::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'qualification_id' => $qualification->id,
            'valid_from' => '2026-01-01',
            'valid_until' => $validUntil,
        ]);

        return $user;
    }

    public function test_vorgabe_ist_die_aggregierte_auskunft_ohne_namen(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');

        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index', ['as_of' => '2026-06-01']))
            ->assertOk()
            ->assertSee(__('learning.help.dossier_aggregated'))
            ->assertDontSee('Petra Meier');
    }

    public function test_namentlich_ohne_anlass_bleibt_aggregiert(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');

        // Der Schalter allein genügt nicht — ohne Anlass keine Namen.
        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index', ['as_of' => '2026-06-01', 'named' => 1]))
            ->assertOk()
            ->assertDontSee('Petra Meier');

        $this->assertSame(0, AuditLog::query()->where('event', 'learning.dossierDisclosed')->count());
    }

    public function test_namentlich_mit_anlass_wird_protokolliert(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');

        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index', [
                'as_of' => '2026-06-01',
                'named' => 1,
                'reason' => 'Vergabeunterlage Los 3',
            ]))
            ->assertOk()
            ->assertSee('Petra Meier');

        $log = AuditLog::query()->where('event', 'learning.dossierDisclosed')->first();

        $this->assertNotNull($log);
        $this->assertSame('Vergabeunterlage Los 3', $log->changes['reason'] ?? null);
        $this->assertSame('2026-06-01', $log->changes['as_of'] ?? null);
    }

    public function test_ampel_schlaegt_bei_abgelaufenem_nachweis_aus(): void {
        // Zum Stichtag abgelaufen — die Person ist nicht einsatzbereit.
        $this->workerWithProof('Kurt Alt', '2026-03-01');

        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index', ['as_of' => '2026-06-01']))
            ->assertOk()
            ->assertSee(__('learning.field.expired'));

        $summary = app(\App\Services\Learning\QualificationDossierService::class)
            ->coverageSummary(User::query()->where('name', 'Kurt Alt')->get(), Carbon::parse('2026-06-01'));

        $this->assertSame('error', $summary['tone']);
        $this->assertSame(0, $summary['ready']);
    }

    public function test_pdf_und_json_kommen_als_download(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');
        $manager = $this->manager();

        $pdf = $this->actingAs($manager)->get(route('learning.dossier.pdf', ['as_of' => '2026-06-01']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $json = $this->actingAs($manager)->get(route('learning.dossier.json', ['as_of' => '2026-06-01']));
        $json->assertOk();
        $payload = json_decode($json->streamedContent(), true);
        $this->assertSame('2026-06-01', $payload['as_of'] ?? null);
        $this->assertNotEmpty($payload['hash'] ?? null);
    }

    public function test_ohne_verwaltungsrecht_kein_zugriff(): void {
        $outsider = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($outsider)
            ->get(route('learning.dossier.index'))
            ->assertForbidden();
    }

    public function test_fremde_organisation_zaehlt_nicht_mit(): void {
        // Eine Nachweismappe, die fremde Belegschaft mitzählt, wäre nicht
        // nur falsch, sondern ein Datenabfluss.
        $this->workerWithProof('Petra Meier', '2027-01-01');

        $foreign = \App\Models\Organization::factory()->create();
        User::factory()->aussendienst()->create([
            'organization_id' => $foreign->id,
            'name' => 'Fremder Kollege',
        ]);

        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index', [
                'as_of' => '2026-06-01',
                'named' => 1,
                'reason' => 'Prüfung',
            ]))
            ->assertOk()
            ->assertSee('Petra Meier')
            ->assertDontSee('Fremder Kollege');
    }
}
