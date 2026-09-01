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
use App\Services\UI\DateRangeContext;
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

    /** Der betrachtete Zeitraum kommt aus dem Kopfbereich, nicht aus der URL. */
    private function usePeriod(string $from, string $to): void {
        app(DateRangeContext::class)->set(DateRangeContext::PRESET_CUSTOM, $from, $to);
    }

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        // Fester Betrachtungstag: die Mappe hing frueher an `as_of` in der URL,
        // heute am globalen Kopf-Zeitraum. Ein Ein-Tages-Zeitraum bildet die
        // alte Stichtagsaussage exakt ab.
        $this->usePeriod('2026-06-01', '2026-06-01');
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

    /**
     * Es gibt genau EIN Zeit-Bedienelement — und zwar den Kopf-Zeitraum.
     *
     * Frueher stand hier ein eigener Stichtag NEBEN dem globalen Regler, der
     * nichts tat. Jetzt traegt der Kopfbereich die Aussage, und die Mappe hat
     * kein zweites Datumsfeld mehr.
     */
    public function test_zeitraum_kommt_aus_dem_kopfbereich(): void {
        $response = $this->actingAs($this->manager())->get(route('learning.dossier.index'));

        $response->assertOk();
        $response->assertSee('data-header-daterange', false);
        $response->assertDontSee('name="as_of"', false);
    }

    /**
     * Ein Nachweis, der mitten im Zeitraum ablaeuft, deckt ihn nur teilweise —
     * gelb, nicht gruen und nicht rot. Genau dafuer gibt es den Zeitraum:
     * der Stichtag haette „gueltig" gesagt und das Ablaufdatum verschwiegen.
     */
    public function test_ablauf_mitten_im_zeitraum_ist_teilweise_gedeckt(): void {
        $this->usePeriod('2026-06-01', '2026-08-31');
        $user = $this->workerWithProof('Mia Mittendrin', '2026-07-15');

        $summary = app(\App\Services\Learning\QualificationDossierService::class)
            ->coverageSummary(User::query()->whereKey($user->id)->get(), Carbon::parse('2026-06-01'), Carbon::parse('2026-08-31'));

        $this->assertSame(1, $summary['partial']);
        $this->assertSame(0, $summary['ready']);
        $this->assertSame(0, $summary['expired']);
        $this->assertSame('warning', $summary['tone']);
    }

    /** Reicht der Nachweis ueber das Ende hinaus, ist der Zeitraum durchgehend gedeckt. */
    public function test_nachweis_ueber_den_ganzen_zeitraum_ist_gruen(): void {
        $this->usePeriod('2026-06-01', '2026-08-31');
        $user = $this->workerWithProof('Dauerhaft Gueltig', '2027-01-01');

        $summary = app(\App\Services\Learning\QualificationDossierService::class)
            ->coverageSummary(User::query()->whereKey($user->id)->get(), Carbon::parse('2026-06-01'), Carbon::parse('2026-08-31'));

        $this->assertSame(1, $summary['ready']);
        $this->assertSame(0, $summary['partial']);
        $this->assertSame('success', $summary['tone']);
    }

    public function test_vorgabe_ist_die_aggregierte_auskunft_ohne_namen(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');

        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index'))
            ->assertOk()
            ->assertSee(__('learning.help.dossier_aggregated'))
            ->assertDontSee('Petra Meier');
    }

    public function test_namentlich_ohne_anlass_bleibt_aggregiert(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');

        // Der Schalter allein genügt nicht — ohne Anlass keine Namen.
        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index', ['named' => 1]))
            ->assertOk()
            ->assertDontSee('Petra Meier');

        $this->assertSame(0, AuditLog::query()->where('event', 'learning.dossierDisclosed')->count());
    }

    public function test_namentlich_mit_anlass_wird_protokolliert(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');

        $this->actingAs($this->manager())
            ->get(route('learning.dossier.index', [
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
            ->get(route('learning.dossier.index'))
            ->assertOk()
            ->assertSee(__('learning.field.uncovered'));

        $summary = app(\App\Services\Learning\QualificationDossierService::class)
            ->coverageSummary(User::query()->where('name', 'Kurt Alt')->get(), Carbon::parse('2026-06-01'));

        $this->assertSame('error', $summary['tone']);
        $this->assertSame(0, $summary['ready']);
    }

    public function test_pdf_und_json_kommen_als_download(): void {
        $this->workerWithProof('Petra Meier', '2027-01-01');
        $manager = $this->manager();

        $pdf = $this->actingAs($manager)->get(route('learning.dossier.pdf'));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $json = $this->actingAs($manager)->get(route('learning.dossier.json'));
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
                'named' => 1,
                'reason' => 'Prüfung',
            ]))
            ->assertOk()
            ->assertSee('Petra Meier')
            ->assertDontSee('Fremder Kollege');
    }
}
