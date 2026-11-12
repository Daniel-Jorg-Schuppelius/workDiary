<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLinkedUnitKindsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningProgressStatus, LearningUnitKind};
use App\Enums\Procedure\ProcedureRunStatus;
use App\Events\Procedure\ProcedureRunProgressed;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningUnit, LearningUnitProgress};
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureRun, ProcedureTemplate, ProcedureTemplateVersion};
use App\Models\Survey\{Survey, SurveyInvitation, SurveyQuestion};
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Einheitenarten Prozedur, Umfrage und Extern (MVP-1600): Verweis im Dialog, Abschluss über das Ziel. */
final class LearningLinkedUnitKindsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{0: LearningCourse, 1: LearningUnit}
     */
    private function releasedCourse(LearningUnitKind $kind, array $rule): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Einarbeitung']);
        $courses->addUnit($course, ['title' => 'Praxis', 'kind' => $kind->value, 'completion_rule' => $rule]);
        $courses->release($course->refresh(), null);

        return [$course->refresh(), $course->units()->firstOrFail()];
    }

    private function progressStatus(LearningEnrollment $enrollment, LearningUnit $unit): ?LearningProgressStatus {
        return LearningUnitProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('learning_unit_id', $unit->id)
            ->first()?->status;
    }

    public function test_unit_dialog_requires_and_stores_the_reference_of_each_kind(): void {
        $course = app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => 'Einarbeitung']);
        $template = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Leiter prüfen']);
        $foreign = ProcedureTemplate::factory()->create();
        $survey = Survey::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Selbsteinschätzung']);
        $author = $this->author();

        $this->actingAs($author)->get(route('learning.courses.units.create', $course))
            ->assertOk()
            ->assertSee('Leiter prüfen')
            ->assertSee('Selbsteinschätzung')
            ->assertSee(__('learning.field.unit_external_url'));

        // Ohne Verweis wäre die Art funktionslos.
        $this->actingAs($author)->post(route('learning.courses.units.store', $course), ['title' => 'Lauf', 'kind' => 'procedure'])
            ->assertSessionHasErrors('procedure_template');
        $this->actingAs($author)->post(route('learning.courses.units.store', $course), ['title' => 'Lauf', 'kind' => 'procedure', 'procedure_template' => $foreign->sqid])
            ->assertSessionHasErrors('procedure_template');
        $this->actingAs($author)->post(route('learning.courses.units.store', $course), ['title' => 'Bogen', 'kind' => 'survey'])
            ->assertSessionHasErrors('survey');
        $this->actingAs($author)->post(route('learning.courses.units.store', $course), ['title' => 'Kurs', 'kind' => 'external'])
            ->assertSessionHasErrors('external_url');

        $this->actingAs($author)->post(route('learning.courses.units.store', $course), ['title' => 'Lauf', 'kind' => 'procedure', 'procedure_template' => $template->sqid])
            ->assertSessionHasNoErrors();
        $this->actingAs($author)->post(route('learning.courses.units.store', $course), ['title' => 'Bogen', 'kind' => 'survey', 'survey' => $survey->sqid])
            ->assertSessionHasNoErrors();
        $this->actingAs($author)->post(route('learning.courses.units.store', $course), [
            'title' => 'Kurs', 'kind' => 'external', 'external_url' => 'https://akademie.example.test/kurs', 'proof_required' => '1',
        ])->assertSessionHasNoErrors();

        $units = $course->refresh()->units()->get()->keyBy('title');
        $this->assertSame(['procedure_template_id' => $template->id], $units['Lauf']->completion_rule);
        $this->assertSame(['survey_id' => $survey->id], $units['Bogen']->completion_rule);
        $this->assertSame(['external_url' => 'https://akademie.example.test/kurs', 'proof_required' => true], $units['Kurs']->completion_rule);
        $this->assertTrue($units['Lauf']->reportsOwnResult());

        // Bearbeiten behält die Mindestverweildauer und ersetzt nur den Verweis.
        $this->actingAs($author)->put(route('learning.courses.units.update', [$course, $units['Kurs']]), [
            'title' => 'Kurs', 'min_seconds' => 60, 'external_url' => 'https://akademie.example.test/neu',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['min_seconds' => 60, 'external_url' => 'https://akademie.example.test/neu'], $units['Kurs']->refresh()->completion_rule);
    }

    public function test_completed_procedure_run_completes_the_unit_of_the_acting_person(): void {
        $template = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id]);
        $other = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id]);
        [$course, $unit] = $this->releasedCourse(LearningUnitKind::Procedure, ['procedure_template_id' => $template->id]);
        $learner = $this->learner();
        $colleague = $this->learner();
        $enrollments = app(LearningEnrollmentService::class);
        $enrollment = $enrollments->enroll($course, $learner);
        $colleagueEnrollment = $enrollments->enroll($course, $colleague);

        // Von Hand lässt sich die Einheit nicht abhaken.
        $this->actingAs($learner)->post(route('learning.my.units.complete', [$enrollment, $unit]))->assertForbidden();
        $this->actingAs($learner)->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee(__('learning.badge.awaits_procedure'))
            ->assertSee(__('learning.help.unit_procedure', ['procedure' => $template->name]));

        $versions = [
            $template->id => ProcedureTemplateVersion::factory()->published()->create(['procedure_template_id' => $template->id]),
            $other->id => ProcedureTemplateVersion::factory()->published()->create(['procedure_template_id' => $other->id]),
        ];
        $run = fn (ProcedureTemplate $of, ProcedureRunStatus $status): ProcedureRun => ProcedureRun::factory()->create([
            'organization_id' => $this->organization->id,
            'procedure_template_version_id' => $versions[$of->id]->id,
            'status' => $status->value,
            'created_by_user_id' => $learner->id,
        ]);

        // Ein offener Lauf und ein Lauf einer anderen Vorlage zählen nicht.
        ProcedureRunProgressed::dispatch($run($template, ProcedureRunStatus::InProgress), $learner);
        ProcedureRunProgressed::dispatch($run($other, ProcedureRunStatus::Completed), $learner);
        $this->assertNotSame(LearningProgressStatus::Completed, $this->progressStatus($enrollment, $unit));

        ProcedureRunProgressed::dispatch($run($template, ProcedureRunStatus::Completed), $learner);

        $this->assertSame(LearningProgressStatus::Completed, $this->progressStatus($enrollment, $unit));
        $this->assertTrue($enrollment->refresh()->status->isFinal());
        $this->assertNull($this->progressStatus($colleagueEnrollment, $unit), 'Der Lauf einer anderen Person schließt nichts ab.');
    }

    public function test_submitted_questionnaire_completes_the_survey_unit(): void {
        $survey = Survey::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Selbsteinschätzung', 'anonymous' => true]);
        $question = SurveyQuestion::factory()->create(['organization_id' => $this->organization->id, 'survey_id' => $survey->id]);
        [$course, $unit] = $this->releasedCourse(LearningUnitKind::Survey, ['survey_id' => $survey->id]);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        $this->actingAs($learner)->post(route('learning.my.units.complete', [$enrollment, $unit]))->assertForbidden();

        $response = $this->actingAs($learner)->post(route('learning.my.units.survey', [$enrollment, $unit]));
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/umfrage/', $target);

        $invitation = SurveyInvitation::query()->where('survey_id', $survey->id)->firstOrFail();
        $this->assertNull($invitation->sent_at, 'Selbst angefordert: zählt nicht gegen den Ermüdungsschutz.');
        $this->assertSame(LearningProgressStatus::Started, $this->progressStatus($enrollment, $unit));

        // Die Teilnahme läuft ohne Anmeldung.
        $this->app['auth']->forgetGuards();
        $this->post($target, ['q' . $question->id => 9])->assertOk();

        $this->assertSame(LearningProgressStatus::Completed, $this->progressStatus($enrollment, $unit));
        $this->assertTrue($enrollment->refresh()->status->isFinal());
    }

    public function test_external_unit_is_confirmed_with_proof(): void {
        [$course, $unit] = $this->releasedCourse(LearningUnitKind::External, ['external_url' => 'https://akademie.example.test/kurs', 'proof_required' => true]);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        $this->actingAs($learner)->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee('https://akademie.example.test/kurs', false)
            ->assertSee(__('learning.action.confirm_external'));

        // Der allgemeine Knopf und eine Bestätigung ohne Nachweis greifen nicht.
        $this->actingAs($learner)->post(route('learning.my.units.complete', [$enrollment, $unit]))->assertForbidden();
        $this->actingAs($learner)->post(route('learning.my.units.external-complete', [$enrollment, $unit]))
            ->assertSessionHasErrors('proof');
        $this->assertNull($this->progressStatus($enrollment, $unit));

        $this->actingAs($learner)->post(route('learning.my.units.external-complete', [$enrollment, $unit]), [
            'proof' => UploadedFile::fake()->create('teilnahme.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('learning.my.show', $enrollment));

        $progress = LearningUnitProgress::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame(LearningProgressStatus::Completed, $progress->status);
        $proof = $progress->attachments()->firstOrFail();
        $this->assertSame('teilnahme.pdf', $proof->original_name);

        // Die Person selbst und die Kursverwaltung kommen an den Nachweis, Dritte nicht.
        $this->actingAs($learner)->get(route('learning.my.units.proof', [$enrollment->sqid, $unit->sqid, $proof->sqid]))->assertOk();
        $author = $this->author();
        $this->actingAs($author)->get(route('learning.courses.enrollments.index', $course))
            ->assertOk()
            ->assertSee(route('learning.courses.enrollments.proofs.show', [$course->sqid, $enrollment->sqid, $proof->sqid]), false);
        $this->actingAs($author)->get(route('learning.courses.enrollments.proofs.show', [$course->sqid, $enrollment->sqid, $proof->sqid]))->assertOk();
        $this->actingAs($this->learner())->get(route('learning.my.units.proof', [$enrollment->sqid, $unit->sqid, $proof->sqid]))->assertNotFound();
    }
}
