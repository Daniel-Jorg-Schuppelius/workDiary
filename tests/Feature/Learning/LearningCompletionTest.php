<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCompletionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Models\Learning\{LearningCertificate, LearningCourse, LearningEnrollment};
use App\Models\{Qualification, User, UserQualification};
use App\Models\Safety\{SafetyInstruction, SafetyInstructionParticipant};
use App\Models\Training\{TrainingAssignment, TrainingCourse};
use App\Services\Learning\{LearningCompletionService, LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rückfluss eines Kursabschlusses (Feature 149, MVP-740): Zertifikat,
 * Unterweisungsnachweis im Register (132), Soll-Erfüllung (145) und
 * Qualifikation (013) — alles über die vorhandenen Wege, keine zweite
 * Nachweiswelt.
 */
class LearningCompletionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array{0: LearningEnrollment, 1: LearningCourse, 2: User} */
    private function scenario(array $courseAttributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, array_merge([
            'title' => 'Brandschutzunterweisung',
            'validity_months' => 12,
        ], $courseAttributes));
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->release($course->refresh(), null);

        $user = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $user);

        return [$enrollment, $course->refresh(), $user];
    }

    private function complete(LearningEnrollment $enrollment): LearningEnrollment {
        $unit = $enrollment->course->units()->firstOrFail();
        app(LearningEnrollmentService::class)->completeUnit($enrollment, $unit);

        return $enrollment->refresh();
    }

    public function test_abschluss_stellt_ein_zertifikat_mit_pruefcode_aus(): void {
        [$enrollment] = $this->scenario(['certificate_enabled' => true]);

        $this->complete($enrollment);

        $certificate = LearningCertificate::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertNotEmpty($certificate->number);
        $this->assertSame(32, mb_strlen($certificate->verification_code));
        $this->assertTrue($certificate->isValid());
        $this->assertSame(
            Carbon::today()->addMonths(12)->toDateString(),
            $certificate->valid_until?->toDateString(),
            'Die Gültigkeit folgt der Kursgültigkeit.'
        );
    }

    public function test_ohne_zertifikatsoption_entsteht_kein_zertifikat(): void {
        [$enrollment] = $this->scenario();

        $this->complete($enrollment);

        $this->assertSame(0, LearningCertificate::query()->count());
    }

    public function test_abschluss_schreibt_unterweisungsnachweis_und_erfuellt_das_soll(): void {
        $trainingCourse = TrainingCourse::factory()->create([
            'organization_id' => $this->organization->id,
            'validity_months' => 12,
        ]);
        [$enrollment, , $user] = $this->scenario([
            'training_course_id' => $trainingCourse->id,
            'creates_instruction_proof' => true,
        ]);

        // Soll-Eintrag wie aus der Pflichtmatrix.
        TrainingAssignment::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'training_course_id' => $trainingCourse->id,
            'source' => 'manual',
            'due_at' => Carbon::today()->addDays(10)->toDateString(),
            'notify_from' => Carbon::today()->toDateString(),
        ]);

        $this->complete($enrollment);

        $instruction = SafetyInstruction::query()->where('training_course_id', $trainingCourse->id)->firstOrFail();
        $participant = SafetyInstructionParticipant::query()
            ->where('safety_instruction_id', $instruction->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertNotNull($participant->signed_at, 'Der Kursabschluss ist die Bestätigung der Person selbst.');
        $this->assertNotEmpty($participant->hash, 'Der Nachweis trägt den Hash des regulären Registers.');

        $assignment = TrainingAssignment::query()
            ->where('user_id', $user->id)
            ->where('training_course_id', $trainingCourse->id)
            ->firstOrFail();
        $this->assertNotNull($assignment->fulfilled_at, 'Das Soll aus Feature 145 ist erfüllt.');
    }

    public function test_abschluss_verlaengert_die_qualifikation(): void {
        $qualification = Qualification::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Brandschutzhelfer',
            'is_active' => true,
        ]);
        [$enrollment, , $user] = $this->scenario(['qualification_id' => $qualification->id]);

        $this->complete($enrollment);

        $granted = UserQualification::query()
            ->where('user_id', $user->id)
            ->where('qualification_id', $qualification->id)
            ->firstOrFail();
        $this->assertSame(Carbon::today()->addMonths(12)->toDateString(), $granted->valid_until?->toDateString());
    }

    public function test_verlaengerung_kuerzt_eine_laengere_gueltigkeit_nicht(): void {
        $qualification = Qualification::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Brandschutzhelfer',
            'is_active' => true,
        ]);
        [$enrollment, , $user] = $this->scenario(['qualification_id' => $qualification->id]);

        $longer = Carbon::today()->addMonths(30)->toDateString();
        UserQualification::query()->create([
            'user_id' => $user->id,
            'qualification_id' => $qualification->id,
            'valid_from' => Carbon::today()->toDateString(),
            'valid_until' => $longer,
        ]);

        $this->complete($enrollment);

        $granted = UserQualification::query()
            ->where('user_id', $user->id)
            ->where('qualification_id', $qualification->id)
            ->firstOrFail();
        $this->assertSame($longer, $granted->valid_until?->toDateString(), 'Eine Verlängerung darf nie kürzen.');
    }

    public function test_zertifikat_wird_nur_einmal_ausgestellt(): void {
        [$enrollment] = $this->scenario(['certificate_enabled' => true]);
        $this->complete($enrollment);

        app(LearningCompletionService::class)->apply($enrollment->refresh());

        $this->assertSame(1, LearningCertificate::query()->where('learning_enrollment_id', $enrollment->id)->count());
    }

    public function test_pruefseite_zeigt_gueltigkeit_ohne_vollen_namen(): void {
        [$enrollment, , $user] = $this->scenario(['certificate_enabled' => true]);
        $this->complete($enrollment);
        $certificate = LearningCertificate::query()->firstOrFail();

        $this->get(route('learning.certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee(__('learning.verify.valid'))
            ->assertSee($certificate->number)
            ->assertDontSee($user->name, false);
    }

    public function test_pruefseite_zeigt_den_widerruf(): void {
        [$enrollment] = $this->scenario(['certificate_enabled' => true]);
        $this->complete($enrollment);
        $certificate = LearningCertificate::query()->firstOrFail();

        app(LearningCompletionService::class)->revoke($certificate, 'Nachweis fehlerhaft');

        $this->get(route('learning.certificates.verify', $certificate->verification_code))
            ->assertOk()
            ->assertSee(__('learning.verify.revoked'));
    }

    public function test_unbekannter_code_verraet_nichts(): void {
        $this->get(route('learning.certificates.verify', str_repeat('a', 32)))
            ->assertOk()
            ->assertSee(__('learning.verify.unknown'));
    }

    public function test_zertifikat_kommt_als_pdf_mit_pruefadresse(): void {
        [$enrollment, , $user] = $this->scenario(['certificate_enabled' => true]);
        $this->complete($enrollment);

        $response = $this->actingAs($user)->get(route('learning.my.certificate', $enrollment->sqid));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_fremdes_zertifikat_ist_nicht_abrufbar(): void {
        [$enrollment] = $this->scenario(['certificate_enabled' => true]);
        $this->complete($enrollment);
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)
            ->get(route('learning.my.certificate', $enrollment->sqid))
            ->assertNotFound();
    }

    public function test_ohne_zertifikat_gibt_es_keinen_ausdruck(): void {
        // Kein Zertifikat ausgestellt ⇒ 404 statt eines leeren Blattes.
        [$enrollment, , $user] = $this->scenario();
        $this->complete($enrollment);

        $this->actingAs($user)
            ->get(route('learning.my.certificate', $enrollment->sqid))
            ->assertNotFound();
    }

    // ── Geräteeinweisung (MVP-740) ──────────────────────────────────────

    public function test_einweisung_traegt_das_geraet_im_nachweis(): void {
        $asset = \App\Models\Asset::factory()->create(['organization_id' => $this->organization->id]);

        [$enrollment, , $user] = $this->scenario([
            'creates_instruction_proof' => true,
            'asset_id' => $asset->id,
        ]);
        $this->complete($enrollment);

        // Ohne den Zeiger wäre nur dokumentiert, DASS unterwiesen wurde,
        // nicht WORAN — für die Betreiberpflicht zu wenig.
        $instruction = \App\Models\Safety\SafetyInstruction::query()->firstOrFail();
        $this->assertSame($asset->id, $instruction->asset_id);
    }

    public function test_zwei_geraete_ergeben_zwei_unterweisungen(): void {
        $first = \App\Models\Asset::factory()->create(['organization_id' => $this->organization->id]);
        $second = \App\Models\Asset::factory()->create(['organization_id' => $this->organization->id]);

        [$enrollmentA, , $userA] = $this->scenario(['creates_instruction_proof' => true, 'asset_id' => $first->id]);
        $this->complete($enrollmentA);

        [$enrollmentB] = $this->scenario(['creates_instruction_proof' => true, 'asset_id' => $second->id]);
        $this->complete($enrollmentB);

        // Nachweise verschiedener Geräte sind verschiedene Unterweisungen,
        // auch am selben Tag.
        $this->assertSame(2, \App\Models\Safety\SafetyInstruction::query()->count());

        unset($userA);
    }

    // ── Subunternehmer-Nachweis (Konzept 11 Nr. 6, Feature 117) ─────────

    /** @return array{0: \App\Models\Learning\LearningEnrollment, 1: \App\Models\Supplier} */
    private function externalSubcontractorEnrollment(\App\Enums\ExternalParticipant\ExternalParty $party): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, [
            'title' => 'Sicherheitsunterweisung Fremdfirmen',
            'certificate_enabled' => true,
            'validity_months' => 12,
        ]);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->release($course->refresh(), null);

        $supplier = \App\Models\Supplier::factory()->create(['organization_id' => $this->organization->id]);

        $participant = \App\Models\ExternalParticipant::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => (new \App\Models\Supplier())->getMorphClass(),
            'subject_id' => $supplier->id,
            'party' => $party->value,
            'name' => 'Kolja Fremd',
        ]);

        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $participant);

        return [$enrollment, $supplier];
    }

    public function test_abschluss_eines_subunternehmers_landet_in_den_pflichtnachweisen(): void {
        [$enrollment, $supplier] = $this->externalSubcontractorEnrollment(
            \App\Enums\ExternalParticipant\ExternalParty::Subcontractor
        );

        $this->complete($enrollment);

        $credential = \App\Models\Supplier\SupplierCredential::query()
            ->where('supplier_id', $supplier->id)
            ->first();

        // Dort wirkt der Nachweis — nicht im LMS.
        $this->assertNotNull($credential);
        $this->assertStringContainsString('Kolja Fremd', (string) $credential->note);
        $this->assertNotNull($credential->valid_until);
    }

    public function test_pruefer_bekommt_keinen_pflichtnachweis(): void {
        // Ein Prüfer oder Gutachter hat keine Pflichtnachweisakte.
        [$enrollment, $supplier] = $this->externalSubcontractorEnrollment(
            \App\Enums\ExternalParticipant\ExternalParty::Inspector
        );

        $this->complete($enrollment);

        $this->assertSame(0, \App\Models\Supplier\SupplierCredential::query()
            ->where('supplier_id', $supplier->id)->count());
    }
}
