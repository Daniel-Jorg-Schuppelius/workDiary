<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseOptionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningAudience, LearningEnrollmentSource, LearningEnrollmentStatus, LearningProgressStatus, LearningSubmissionStatus, LearningUnitKind};
use App\Models\Communication\ExternalParticipant;
use App\Models\Customer\Customer;
use App\Models\Learning\{LearningAssignment, LearningCourse, LearningCourseCategory, LearningEnrollment, LearningSubmission, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningAccessService, LearningAssignmentService, LearningCoursePortabilityService, LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Kursoptionen (Feature 149, MVP-788): der Freischaltplan sperrt an allen
 * Abschlussstellen (Player, Externe, Portal, Offline-Sync), die
 * Mindestverweildauer zählt ab dem ersten Öffnen, Vorschau-Einheiten sind
 * ohne Einschreibung sichtbar, Kategorien filtern den Katalog, Fenster und
 * Teilnehmergrenze gelten für die Selbsteinschreibung, Aufgaben tragen
 * Dateiregeln und Auto-Freigabe, Export/Import nehmen alles mit.
 */
class LearningCourseOptionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @return array{0: LearningCourse, 1: LearningUnit} */
    private function course(array $courseAttributes = [], array $unitAttributes = []): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, array_merge(['title' => 'Brandschutz'], $courseAttributes));
        $courses->addUnit($course, array_merge(['title' => 'Grundlagen', 'content' => [['type' => 'text', 'text' => 'Feuer braucht Sauerstoff.']]], $unitAttributes));
        $courses->release($course->refresh(), null);

        return [$course->refresh(), $course->units()->firstOrFail()];
    }

    private function learner(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function portalUser(): User {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer);

        return User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create();
    }

    private function isCompleted(LearningEnrollment $enrollment, LearningUnit $unit): bool {
        return $enrollment->refresh()->progress()
            ->where('learning_unit_id', $unit->id)
            ->where('status', LearningProgressStatus::Completed->value)
            ->exists();
    }

    // ── Freischaltplan ───────────────────────────────────────────────────

    public function test_freischaltplan_sperrt_den_player_bis_zum_tag(): void {
        [$course, $unit] = $this->course([], ['release_rule' => ['after_days' => 3]]);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee(__('learning.badge.available_from', ['date' => now()->addDays(3)->translatedFormat('d.m.Y')]))
            ->assertDontSee('Feuer braucht Sauerstoff.');

        $this->actingAs($learner)
            ->post(route('learning.my.units.complete', [$enrollment, $unit]))
            ->assertSessionHasErrors('unit');
        $this->assertFalse($this->isCompleted($enrollment, $unit));

        Carbon::setTestNow(now()->addDays(3));
        $this->actingAs($learner)
            ->post(route('learning.my.units.complete', [$enrollment, $unit]))
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->isCompleted($enrollment, $unit));
    }

    public function test_freischaltplan_mit_datum_sperrt_auch_den_offline_sync(): void {
        [$course, $unit] = $this->course([], ['release_rule' => ['at' => now()->addDay()->toDateString()]]);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        $this->actingAs($learner)
            ->postJson(route('api.internal.sync.commands'), ['commands' => [[
                'client_uuid' => (string) Str::uuid(),
                'type' => 'learning.unit-complete',
                'payload' => ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid],
            ]]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'rejected');
        $this->assertFalse($this->isCompleted($enrollment, $unit));

        // Tage UND Datum: es gilt das spätere.
        $unit->update(['release_rule' => ['after_days' => 1, 'at' => now()->addDays(5)->toDateString()]]);
        $this->assertSame(now()->addDays(5)->toDateString(), $unit->refresh()->releaseDateFor($enrollment)?->toDateString());
    }

    public function test_freischaltplan_sperrt_externe_und_portal(): void {
        [$course, $unit] = $this->course(['audiences' => [LearningAudience::Customer->value, LearningAudience::External->value]], ['release_rule' => ['after_days' => 2]]);

        $external = ExternalParticipant::factory()->create(['organization_id' => $this->organization->id]);
        $externalEnrollment = app(LearningEnrollmentService::class)->enroll($course, $external);
        $token = app(LearningAccessService::class)->issue($externalEnrollment);
        $this->get(route('learning.external.enter', $token))->assertRedirect(route('learning.external.show'));
        $this->get(route('learning.external.show'))->assertOk()->assertDontSee('Feuer braucht Sauerstoff.');
        $this->post(route('learning.external.units.complete', $unit))->assertSessionHasErrors('unit');
        $this->assertFalse($this->isCompleted($externalEnrollment, $unit));

        $portalUser = $this->portalUser();
        $portalEnrollment = app(LearningEnrollmentService::class)->enroll($course, $portalUser, ['source' => 'self']);
        $this->actingAs($portalUser, 'customer')
            ->get(route('customer.learning.show', $portalEnrollment))
            ->assertOk()
            ->assertDontSee('Feuer braucht Sauerstoff.');
        $this->actingAs($portalUser, 'customer')
            ->post(route('customer.learning.units.complete', [$portalEnrollment, $unit]))
            ->assertSessionHasErrors('unit');
        $this->assertFalse($this->isCompleted($portalEnrollment, $unit));
    }

    // ── Mindestverweildauer ──────────────────────────────────────────────

    public function test_mindestverweildauer_zaehlt_ab_dem_ersten_oeffnen(): void {
        [$course, $unit] = $this->course([], ['completion_rule' => ['min_seconds' => 120]]);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        // Ohne Öffnen gibt es keinen Startzeitpunkt — zu früh.
        $this->actingAs($learner)
            ->post(route('learning.my.units.complete', [$enrollment, $unit]))
            ->assertSessionHasErrors('unit');

        $this->actingAs($learner)->get(route('learning.my.show', $enrollment))->assertOk();
        $this->assertSame(
            LearningProgressStatus::Started,
            $enrollment->refresh()->progress()->where('learning_unit_id', $unit->id)->firstOrFail()->status,
        );

        $this->actingAs($learner)
            ->post(route('learning.my.units.complete', [$enrollment, $unit]))
            ->assertSessionHasErrors('unit');

        Carbon::setTestNow(now()->addMinutes(3));
        $this->actingAs($learner)
            ->post(route('learning.my.units.complete', [$enrollment, $unit]))
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->isCompleted($enrollment, $unit));
    }

    // ── Vorschau ─────────────────────────────────────────────────────────

    public function test_vorschau_einheiten_sind_im_portal_ohne_einschreibung_sichtbar(): void {
        [$course] = $this->course(['audiences' => [LearningAudience::Customer->value]], ['is_preview' => true]);
        app(LearningCourseService::class)->reopen($course);
        app(LearningCourseService::class)->addUnit($course->refresh(), [
            'title' => 'Nur für Eingeschriebene',
            'content' => [['type' => 'text', 'text' => 'Geheimer Stoff.']],
        ]);
        app(LearningCourseService::class)->release($course->refresh(), null);
        $portalUser = $this->portalUser();

        $this->actingAs($portalUser, 'customer')
            ->get(route('customer.learning.index'))
            ->assertOk()
            ->assertSee(route('customer.learning.preview', $course));

        $this->actingAs($portalUser, 'customer')
            ->get(route('customer.learning.preview', $course))
            ->assertOk()
            ->assertSee('Grundlagen')
            ->assertSee('Feuer braucht Sauerstoff.')
            ->assertDontSee('Nur für Eingeschriebene')
            ->assertDontSee('Geheimer Stoff.');

        // Die Kursakte kennzeichnet die Vorschau-Einheit. (Nach dem Portal-Guard
        // ist `web` ausdrücklich zu nennen — actingAs() merkt sich den Guard.)
        $this->actingAs($this->manager(), 'web')
            ->get(route('learning.courses.show', $course))
            ->assertOk()
            ->assertSee(__('learning.badge.preview'));
    }

    // ── Kategorien ───────────────────────────────────────────────────────

    public function test_kategorien_aus_den_einstellungen_filtern_katalog_und_portal(): void {
        $manager = $this->manager();
        $this->actingAs($manager)
            ->put(route('learning.settings.update'), ['categories' => "Sicherheit\nVertrieb\n"])
            ->assertRedirect(route('learning.courses.index'));
        $this->assertSame(['Sicherheit', 'Vertrieb'], LearningCourseCategory::query()->orderBy('position')->pluck('name')->all());
        $safety = LearningCourseCategory::query()->where('name', 'Sicherheit')->firstOrFail();

        [$safetyCourse] = $this->course(['title' => 'Brandschutz', 'category_id' => $safety->id, 'audiences' => [LearningAudience::Customer->value]]);
        [$other] = $this->course(['title' => 'Verkaufstraining', 'audiences' => [LearningAudience::Customer->value]]);

        $this->actingAs($manager)
            ->get(route('learning.courses.index', ['category' => $safety->sqid]))
            ->assertOk()
            ->assertSee('Brandschutz')
            ->assertDontSee('Verkaufstraining');

        $this->actingAs($this->portalUser(), 'customer')
            ->get(route('customer.learning.index', ['category' => $safety->sqid]))
            ->assertOk()
            ->assertSee('Brandschutz')
            ->assertDontSee('Verkaufstraining');

        // Kategorie streichen: der Kurs bleibt, nur ohne Kategorie.
        $this->actingAs($manager, 'web')->put(route('learning.settings.update'), ['categories' => 'Vertrieb'])->assertSessionHasNoErrors();
        $this->assertNull($safetyCourse->refresh()->category_id);
        $this->assertSame(1, LearningCourseCategory::query()->count());
    }

    /** UI-Fuzz 2026-09-21: jede Zeile wird eine Kategorie (name varchar(120)) — eine lange Zeile endete in 1406. */
    public function test_ueberlange_kategoriezeile_ist_ein_feldfehler(): void {
        Exceptions::fake();

        $this->actingAs($this->manager())
            ->put(route('learning.settings.update'), ['categories' => "Sicherheit\n" . str_repeat('Kategorie ', 13)])
            ->assertSessionHasErrors('categories');

        $this->assertSame(0, LearningCourseCategory::query()->count());
        Exceptions::assertNothingReported();
    }

    // ── Fenster und Grenze ───────────────────────────────────────────────

    public function test_fenster_und_teilnehmergrenze_gelten_fuer_die_selbsteinschreibung(): void {
        [$closed] = $this->course(['title' => 'Abgelaufen', 'available_until' => now()->subDay()->toDateString(), 'audiences' => [LearningAudience::Customer->value]]);
        $portalUser = $this->portalUser();

        $this->actingAs($portalUser, 'customer')
            ->get(route('customer.learning.index'))
            ->assertOk()
            ->assertDontSee('Abgelaufen');

        try {
            app(LearningEnrollmentService::class)->enroll($closed, $portalUser, ['source' => 'self']);
            $this->fail('Selbsteinschreibung außerhalb des Fensters muss scheitern.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('course', $e->errors());
        }
        // Zuweisung durch die Verwaltung bleibt frei.
        $assigned = app(LearningEnrollmentService::class)->enroll($closed, $this->learner());
        $this->assertSame(LearningEnrollmentStatus::Assigned, $assigned->status);

        [$limited] = $this->course(['title' => 'Begrenzt', 'max_enrollments' => 1]);
        app(LearningEnrollmentService::class)->enroll($limited, $this->learner(), ['source' => 'self']);
        try {
            app(LearningEnrollmentService::class)->enroll($limited, $this->learner(), ['source' => 'self']);
            $this->fail('Die Teilnehmergrenze muss greifen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('course', $e->errors());
        }
        // Pflicht umgeht die Grenze.
        $required = app(LearningEnrollmentService::class)->enroll($limited, $this->learner(), ['source' => LearningEnrollmentSource::Requirement->value]);
        $this->assertSame(2, $limited->refresh()->activeEnrollmentsCount());
        $this->assertSame(LearningEnrollmentSource::Requirement, $required->source);
    }

    // ── Aufgabenregeln ───────────────────────────────────────────────────

    /** @return array{0: LearningEnrollment, 1: LearningAssignment, 2: User} */
    private function assignmentScenario(array $attributes): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Erste Hilfe']);
        $courses->addUnit($course, ['title' => 'Praxisbericht', 'kind' => LearningUnitKind::Assignment->value]);
        $unit = $course->refresh()->units()->firstOrFail();
        $assignment = LearningAssignment::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'learning_unit_id' => $unit->id,
            'title' => 'Praxisbericht',
            'submission_kind' => 'file',
            'points' => 10,
            'pass_percent' => 50,
        ], $attributes));
        $courses->release($course->refresh(), null);
        $learner = $this->learner();
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        return [$enrollment, $assignment->refresh(), $learner];
    }

    public function test_dateiregeln_der_aufgabe_greifen_zusaetzlich_zur_systemregel(): void {
        [$enrollment, $assignment, $learner] = $this->assignmentScenario(['allowed_extensions' => ['pdf'], 'max_files' => 1, 'max_file_mb' => 1]);
        $route = route('learning.my.assignments.submit', [$enrollment->sqid, $assignment->sqid]);

        $this->actingAs($learner)
            ->post($route, ['files' => [UploadedFile::fake()->create('bericht.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($learner)
            ->post($route, ['files' => [UploadedFile::fake()->create('a.pdf', 40, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 40, 'application/pdf')]])
            ->assertSessionHasErrors('files');

        $this->actingAs($learner)
            ->post($route, ['files' => [UploadedFile::fake()->create('gross.pdf', 2048, 'application/pdf')]])
            ->assertSessionHasErrors('files.0');

        $this->actingAs($learner)
            ->post($route, ['files' => [UploadedFile::fake()->create('bericht.pdf', 40, 'application/pdf')]])
            ->assertSessionHasNoErrors();
        $this->assertSame(LearningSubmissionStatus::Submitted, LearningSubmission::query()->where('learning_enrollment_id', $enrollment->id)->firstOrFail()->status);
    }

    public function test_auto_freigabe_schliesst_die_einheit_sofort(): void {
        [$enrollment, $assignment] = $this->assignmentScenario(['submission_kind' => 'text', 'auto_approve' => true]);

        $submission = app(LearningAssignmentService::class)->submit($enrollment, $assignment, 'Mein Bericht.');

        $this->assertSame(LearningSubmissionStatus::Graded, $submission->status);
        $this->assertTrue($submission->passed);
        $this->assertSame(10, $submission->points_awarded);
        $this->assertNull($submission->graded_by_user_id);
        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_auto_freigabe_und_vier_augen_schliessen_sich_aus(): void {
        [$enrollment, $assignment] = $this->assignmentScenario([]);
        $course = $enrollment->course;
        $unit = $assignment->unit;
        app(LearningCourseService::class)->reopen($course);

        $this->actingAs($this->manager())
            ->put(route('learning.courses.units.assignment.update', [$course, $unit]), [
                'title' => 'Praxisbericht',
                'submission_kind' => 'file',
                'points' => 10,
                'pass_percent' => 50,
                'requires_second_opinion' => '1',
                'auto_approve' => '1',
            ])
            ->assertSessionHasErrors('auto_approve');

        $this->actingAs($this->manager())
            ->put(route('learning.courses.units.assignment.update', [$course, $unit]), [
                'title' => 'Praxisbericht',
                'submission_kind' => 'file',
                'points' => 10,
                'pass_percent' => 50,
                'allowed_extensions' => 'PDF, .docx',
                'max_files' => 2,
                'max_file_mb' => 5,
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(['pdf', 'docx'], $assignment->refresh()->allowedExtensions());
        $this->assertSame(2, $assignment->maxFiles());
        $this->assertSame(5 * 1024, $assignment->maxFileKb(25600));
    }

    // ── Editor und Portabilität ──────────────────────────────────────────

    public function test_einheiten_editor_speichert_datum_verweildauer_und_vorschau(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Editor']);
        $unit = $courses->addUnit($course, ['title' => 'Einheit']);

        $this->actingAs($this->manager())
            ->put(route('learning.courses.units.update', [$course, $unit]), [
                'title' => 'Einheit',
                'is_mandatory' => '1',
                'is_preview' => '1',
                'release_after_days' => 2,
                'release_at' => '2027-01-15',
                'min_seconds' => 90,
            ])
            ->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertTrue($unit->is_preview);
        $this->assertSame(['after_days' => 2, 'at' => '2027-01-15'], $unit->release_rule);
        $this->assertSame(90, $unit->minSeconds());
    }

    public function test_export_und_import_tragen_kategorie_fenster_und_regeln(): void {
        $courses = app(LearningCourseService::class);
        $category = $courses->categoryByName($this->organization, 'Sicherheit', true);
        [$course, $unit] = $this->course(
            ['category_id' => $category?->id, 'available_from' => '2027-01-01', 'max_enrollments' => 20],
            ['is_preview' => true, 'release_rule' => ['after_days' => 1], 'completion_rule' => ['min_seconds' => 60]],
        );

        $payload = app(LearningCoursePortabilityService::class)->export($course);
        $this->assertSame('Sicherheit', $payload['course']['category']);
        $this->assertSame('2027-01-01', $payload['course']['available_from']);
        $this->assertTrue($payload['units'][0]['is_preview']);

        $payload['course']['category'] = 'Neu aus Import';
        $imported = app(LearningCoursePortabilityService::class)->import($this->organization, $payload);
        $this->assertSame('Neu aus Import', $imported->category?->name);
        $this->assertSame(20, $imported->max_enrollments);
        $importedUnit = $imported->units()->firstOrFail();
        $this->assertTrue($importedUnit->is_preview);
        $this->assertSame(['after_days' => 1], $importedUnit->release_rule);
        $this->assertSame(60, $importedUnit->minSeconds());
    }
}
