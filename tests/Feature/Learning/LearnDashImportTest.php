<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearnDashImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningCourseStatus, LearningEnrollmentSource, LearningEnrollmentStatus, LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCertificate, LearningCourse, LearningEnrollment, LearningQuestion, LearningQuestionCategory};
use App\Models\Platform\User;
use App\Services\Learning\LearnDashImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/**
 * LearnDash-Importer (Feature 149, MVP-792) gegen ein synthetisches
 * Export-ZIP in der Struktur von LearnDash 5.x (`post_type_*.ld` als JSONL,
 * `proquiz.ld` mit „WPQ"-Kopf + Base64-JSON, `user_activity.ld`, `user.ld`) —
 * ohne echte Personendaten.
 */
class LearnDashImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $zip;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $settings = (array) ($this->organization->settings ?? []);
        data_set($settings, 'learning.embed_hosts', ['www.youtube-nocookie.com']);
        $this->organization->update(['settings' => $settings]);
        $this->zip = $this->buildZip();
    }

    protected function tearDown(): void {
        @unlink($this->zip);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function jsonl(array $rows): string {
        return implode('', array_map(static fn (array $r): string => json_encode($r, JSON_UNESCAPED_UNICODE) . "\n", $rows));
    }

    private function ldPost(int $id, string $type, string $title, string $content = '', array $meta = [], int $order = 0): array {
        return ['wp_post' => ['ID' => $id, 'post_title' => $title, 'post_content' => $content, 'post_type' => $type, 'post_status' => 'publish', 'menu_order' => $order, 'post_excerpt' => ''], 'wp_post_permalink' => 'https://lms.example/' . $id, 'wp_post_meta' => $meta, 'wp_post_terms' => []];
    }

    private function buildZip(): string {
        $path = tempnam(sys_get_temp_dir(), 'ld') . '.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('post_type_course.ld', $this->jsonl([
            $this->ldPost(10, 'sfwd-courses', 'Brandschutz <em>kompakt</em>', '<!-- wp:paragraph --><p>Grundlagen des betrieblichen Brandschutzes.</p><!-- /wp:paragraph -->'),
        ]));
        $zip->addFromString('post_type_lesson.ld', $this->jsonl([
            $this->ldPost(20, 'sfwd-lessons', 'Lektion 1: Feuer', '<h2>Was brennt?</h2><p>Feuer braucht Sauerstoff.</p><ul><li>Brennstoff</li><li>Sauerstoff</li><li>Zündquelle</li></ul><img src="https://lms.example/wp-content/uploads/dreieck.png" alt="Branddreieck">', ['course_id' => 10, '_sfwd-lessons' => ['sfwd-lessons_lesson_video_enabled' => 'on', 'sfwd-lessons_lesson_video_url' => 'https://www.youtube-nocookie.com/embed/abc']], 1),
            $this->ldPost(21, 'sfwd-lessons', 'Lektion 2: Löschen', '<p>Löscher richtig einsetzen.</p>', ['course_id' => 10, '_sfwd-lessons' => ['sfwd-lessons_lesson_video_enabled' => 'on', 'sfwd-lessons_lesson_video_url' => 'https://video.example/x']], 2),
        ]));
        $zip->addFromString('post_type_topic.ld', $this->jsonl([
            $this->ldPost(30, 'sfwd-topic', 'Thema: Brandklassen', '<p>A, B, C, D, F.</p>', ['course_id' => 10, 'lesson_id' => 20]),
        ]));
        $zip->addFromString('post_type_quiz.ld', $this->jsonl([
            $this->ldPost(40, 'sfwd-quiz', 'Abschlussprüfung', '', ['course_id' => 10, 'lesson_id' => 0, 'quiz_pro_id' => 7, '_sfwd-quiz' => ['sfwd-quiz_passingpercentage' => '75', 'sfwd-quiz_repeats' => '2']]),
        ]));

        $proquiz = [
            'version' => '0.9.x', 'exportVersion' => 1,
            'master' => [7 => ['_id' => 7, '_name' => 'Abschlussprüfung', '_text' => '<p>Viel Erfolg!</p>', '_questionRandom' => true, '_answerRandom' => false, '_timeLimit' => 600, '_showMaxQuestion' => true, '_showMaxQuestionValue' => 3, '_quizModus' => 3, '_forcingQuestionSolve' => true, '_skipQuestionDisabled' => true, '_showReviewQuestion' => true]],
            'question' => [7 => [
                ['_id' => 1, '_title' => 'Notruf', '_question' => '<p>Welche Nummer hat die Feuerwehr?</p>', '_points' => 2, '_answerType' => 'single', '_categoryName' => 'Brandschutz', '_tipEnabled' => true, '_tipMsg' => 'Drei Ziffern.', '_correctMsg' => 'Richtig!', '_incorrectMsg' => 'Leider nein.', '_answerData' => [['_answer' => '112', '_correct' => true, '_points' => 1], ['_answer' => '110', '_correct' => false, '_points' => 0]]],
                ['_id' => 2, '_title' => 'Brandklassen', '_question' => 'Welche gehören dazu?', '_points' => 3, '_answerType' => 'multiple', '_categoryName' => 'Brandschutz', '_answerPointsActivated' => true, '_answerData' => [['_answer' => 'A', '_correct' => true], ['_answer' => 'B', '_correct' => true], ['_answer' => 'Z', '_correct' => false]]],
                ['_id' => 3, '_title' => 'Reihenfolge', '_question' => 'Sortiere die Schritte.', '_points' => 3, '_answerType' => 'sort_answer', '_answerData' => [['_answer' => 'Alarmieren'], ['_answer' => 'Retten'], ['_answer' => 'Löschen']]],
                ['_id' => 4, '_title' => 'Zuordnung', '_question' => 'Ordne zu.', '_points' => 2, '_answerType' => 'matrix_sort_answer', '_answerData' => [['_sortString' => 'Holz', '_answer' => 'Klasse A'], ['_sortString' => 'Benzin', '_answer' => 'Klasse B']]],
                ['_id' => 5, '_title' => 'Lücke', '_question' => 'Fülle aus.', '_points' => 2, '_answerType' => 'cloze_answer', '_answerData' => [['_answer' => 'Feuer braucht {[Sauerstoff][O2]} und {Brennstoff}.']]],
                ['_id' => 6, '_title' => 'Frei', '_question' => 'Wie heißt das Löschmittel für Fettbrände?', '_points' => 1, '_answerType' => 'free_answer', '_answerData' => [['_answer' => "Fettbrandlöscher\nKlasse F"]]],
                ['_id' => 7, '_title' => 'Aufsatz', '_question' => 'Beschreibe den Fluchtweg.', '_points' => 5, '_answerType' => 'essay', '_answerData' => []],
                ['_id' => 8, '_title' => 'Selbsteinschätzung', '_question' => 'Wie sicher fühlst du dich?', '_points' => 5, '_answerType' => 'assessment_answer', '_answerData' => [['_answer' => '{ [1] [2] [3] }']]],
            ]],
        ];
        $zip->addFromString('proquiz.ld', $this->jsonl([
            ['proquiz_post_id' => 40, 'proquiz_data' => 'WPQ0000900001' . base64_encode(json_encode($proquiz)), 'proquiz_statistics' => []],
        ]));
        $zip->addFromString('user.ld', $this->jsonl([
            ['user' => ['data' => ['ID' => 5, 'user_email' => 'lisa@example.test', 'display_name' => 'Lisa']]],
            ['user' => ['data' => ['ID' => 6, 'user_email' => 'unbekannt@example.test', 'display_name' => 'Fremd']]],
        ]));
        $zip->addFromString('user_activity.ld', $this->jsonl([
            ['user_id' => 5, 'post_id' => 10, 'course_id' => 10, 'activity_type' => 'course', 'activity_status' => 1, 'activity_started' => 1700000000, 'activity_completed' => 1700100000, 'activity_meta' => []],
            ['user_id' => 5, 'post_id' => 20, 'course_id' => 10, 'activity_type' => 'lesson', 'activity_status' => 1, 'activity_meta' => []],
            ['user_id' => 6, 'post_id' => 10, 'course_id' => 10, 'activity_type' => 'course', 'activity_status' => 1, 'activity_meta' => []],
        ]));
        $zip->close();

        return $path;
    }

    public function test_import_legt_kurs_struktur_pruefung_fragen_und_abschluss_an(): void {
        $lisa = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id, 'email' => 'Lisa@Example.test']);

        $report = app(LearnDashImportService::class)->import($this->organization, $this->zip, $this->orgAdmin());

        $this->assertSame(1, $report['courses']);
        $this->assertSame(2, $report['sections']);
        $this->assertSame(4, $report['units'], '2 Lektionen + 1 Thema + 1 Prüfung');
        $this->assertSame(1, $report['quizzes']);
        $this->assertSame(8, $report['questions'], 'inkl. Selbsteinschätzung (MVP-793)');
        $this->assertSame(1, $report['enrollments']);
        $reasons = array_column($report['skipped'], 'reason');
        $this->assertNotContains('unsupported_type:assessment_answer', $reasons);
        $this->assertContains('host_not_allowed', $reasons);
        $this->assertContains('user_not_found', $reasons);
        $this->assertTrue(collect($reasons)->contains(fn (string $r) => str_starts_with($r, 'media_not_imported:')));

        $course = LearningCourse::query()->firstOrFail();
        $this->assertSame('Brandschutz kompakt', $course->title);
        $this->assertSame(LearningCourseStatus::Draft, $course->status);
        $this->assertSame('Grundlagen des betrieblichen Brandschutzes.', $course->description);

        $units = $course->units()->orderBy('position')->get();
        $this->assertSame(['Lektion 1: Feuer', 'Thema: Brandklassen', 'Lektion 2: Löschen', 'Abschlussprüfung'], $units->pluck('title')->all());
        $lesson = $units[0];
        $this->assertSame('Lektion 1: Feuer', $lesson->section?->title);
        $types = array_column($lesson->blocks(), 'type');
        $this->assertSame(['video', 'heading', 'text', 'checklist', 'text'], $types);
        $this->assertSame(['Brennstoff', 'Sauerstoff', 'Zündquelle'], $lesson->blocks()[3]['items']);
        $this->assertStringContainsString('Branddreieck', $lesson->blocks()[4]['text']);
        $this->assertSame('text', $units[2]->blocks()[0]['type'], 'Fremder Video-Host wird zum Textvermerk.');

        $quizUnit = $units[3];
        $this->assertSame(LearningUnitKind::Quiz, $quizUnit->kind);
        $quiz = $quizUnit->quiz;
        $this->assertSame(75, $quiz->pass_percent);
        $this->assertSame(3, $quiz->max_attempts, '2 Wiederholungen = 3 Versuche');
        $this->assertSame(10, $quiz->time_limit_minutes);
        $this->assertSame(3, $quiz->questions_per_attempt);
        $this->assertTrue($quiz->shuffle_questions);
        $this->assertSame('single', $quiz->display_mode);
        $this->assertTrue($quiz->require_all_answered);
        $this->assertSame(8, $quiz->questions()->count());

        $this->assertSame(1, LearningQuestionCategory::query()->where('name', 'Brandschutz')->count());
        $single = LearningQuestion::query()->where('title', 'Notruf')->firstOrFail();
        $this->assertSame(LearningQuestionKind::Single, $single->kind);
        $this->assertSame('Welche Nummer hat die Feuerwehr?', $single->prompt);
        $this->assertSame('Drei Ziffern.', $single->settings['hint']);
        $this->assertSame('Richtig!', $single->settings['feedback_correct']);
        $this->assertSame(['112' => true, '110' => false], $single->options()->orderBy('position')->pluck('is_correct', 'label')->map(fn ($v) => (bool) $v)->all());
        $cloze = LearningQuestion::query()->where('title', 'Lücke')->firstOrFail();
        $this->assertSame([['Sauerstoff', 'O2'], ['Brennstoff']], $cloze->settings['gaps']);
        $matrix = LearningQuestion::query()->where('title', 'Zuordnung')->firstOrFail();
        $this->assertSame(LearningQuestionKind::Matrix, $matrix->kind);
        $free = LearningQuestion::query()->where('title', 'Frei')->firstOrFail();
        $this->assertSame(['Fettbrandlöscher', 'Klasse F'], $free->settings['answers']);
        $assessment = LearningQuestion::query()->where('title', 'Selbsteinschätzung')->firstOrFail();
        $this->assertSame(LearningQuestionKind::Assessment, $assessment->kind);
        $this->assertSame(['1', '2', '3'], $assessment->settings['scale']);
        $multiple = LearningQuestion::query()->where('title', 'Brandklassen')->firstOrFail();
        $this->assertTrue($multiple->settings['partial_credit']);

        $enrollment = LearningEnrollment::query()->firstOrFail();
        $this->assertSame($lisa->id, (int) $enrollment->user_id);
        $this->assertSame(LearningEnrollmentStatus::Completed, $enrollment->status);
        $this->assertSame(LearningEnrollmentSource::Import, $enrollment->source);
        $this->assertSame('2023-11-16', $enrollment->completed_at?->toDateString());
        // Kein Nachweis aus einem Import: weder Zertifikat noch Unterweisung.
        $this->assertSame(0, LearningCertificate::query()->count());
    }

    public function test_dry_run_schreibt_nichts_und_kommando_liefert_bericht(): void {
        $report = app(LearnDashImportService::class)->import($this->organization, $this->zip, null, dryRun: true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['courses']);
        $this->assertSame(0, LearningCourse::query()->count());
        $this->assertSame(0, LearningQuestion::query()->count());

        $this->artisan('learning:import-learndash', ['zip' => $this->zip, '--org' => (string) $this->organization->id, '--dry-run' => true])
            ->expectsOutputToContain('"courses": 1')
            ->assertExitCode(0);
        $this->assertSame(0, LearningCourse::query()->count());
    }

    public function test_dialog_und_upload_ueber_die_oberflaeche(): void {
        $manager = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($manager)
            ->get(route('learning.courses.import-learndash.create'))
            ->assertOk()
            ->assertSee(__('learning.field.learndash_zip'));

        $upload = new UploadedFile($this->zip, 'learndash-export.zip', 'application/zip', null, true);
        $this->actingAs($manager)
            ->post(route('learning.courses.import-learndash'), ['file' => $upload])
            ->assertRedirect(route('learning.courses.index'))
            ->assertSessionHas('success');
        $this->assertSame(1, LearningCourse::query()->count());

        // Ohne Kurse im Archiv: klare Fehlermeldung statt leerem Import.
        $empty = tempnam(sys_get_temp_dir(), 'ld') . '.zip';
        $zip = new ZipArchive;
        $zip->open($empty, ZipArchive::CREATE);
        $zip->addFromString('post_type_course.ld', '');
        $zip->close();
        try {
            app(LearnDashImportService::class)->import($this->organization, $empty, null);
            $this->fail('Ein Archiv ohne Kurse muss abgelehnt werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        } finally {
            @unlink($empty);
        }
    }

    /** Toolkit-Audit 2026-09: Ausbruchspfade im Archiv werden abgelehnt, nicht gelesen. */
    public function test_archive_with_path_traversal_entry_is_rejected(): void {
        $path = tempnam(sys_get_temp_dir(), 'ld') . '.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('../post_type_course.ld', $this->jsonl([$this->ldPost(1, 'sfwd-courses', 'Kurs')]));
        $zip->close();

        try {
            app(LearnDashImportService::class)->import($this->organization, $path, null);
            $this->fail('Ein Archiv mit Ausbruchspfad muss abgelehnt werden.');
        } catch (ValidationException $e) {
            $this->assertSame([__('learning.errors.learndash_zip')], $e->errors()['file']);
        } finally {
            @unlink($path);
        }
    }
}
