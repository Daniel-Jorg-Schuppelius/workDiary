<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5ContentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Console\Commands\SystemHealthCommand;
use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningCmi5Unit, LearningEnrollment, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningCmi5Service, LearningCourseService, LearningEnrollmentService, ScormContentToken};
use App\Support\Learning\ScormContentRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Route};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/**
 * Auslieferung paketinterner cmi5-AUs (Feature 149).
 *
 * Eine AU aus dem Paket ist fremder Code wie ein SCORM-Kurs: mit Inhalts-Host
 * kommt sie nur von dort, ohne ihn vom Anwendungs-Ursprung — und der Systemcheck
 * sagt, dass das so ist.
 */
class Cmi5ContentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const APP = 'https://work.example.test';

    private const CONTENT = 'https://lerninhalte.example.test';

    /** @var list<string> */
    private array $files = [];

    /** @var list<string> */
    private array $folders = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        foreach ($this->folders as $folder) {
            File::deleteDirectory(storage_path('app/' . $folder));
        }

        parent::tearDown();
    }

    public function test_without_content_host_a_packaged_au_comes_from_the_app_origin(): void {
        [$enrollment, $learner, $unit, $au] = $this->scenario();

        $location = $this->launch($enrollment, $learner, $unit, $au);
        $base = rtrim(route('learning.my.cmi5.asset', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]), '/');
        $this->assertStringStartsWith($base . '/kurs/au1/index.html?kapitel=1&', $location, 'Relativ zur cmi5.xml, Abfrage der AU bleibt.');

        $file = $this->actingAs($learner)->get($base . '/kurs/au1/js/app.js');
        $file->assertOk();
        $this->assertStringContainsString("connect-src 'self';", (string) $file->headers->get('Content-Security-Policy'));

        $this->actingAs($learner)->get($base . '/../../../../.env')->assertNotFound();

        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)->get($base . '/kurs/au1/js/app.js')->assertNotFound();
    }

    public function test_with_content_host_the_au_comes_only_from_there_and_may_reach_the_lrs(): void {
        $this->useContentHost();
        [$enrollment, $learner, $unit, $au] = $this->scenario();

        $location = $this->launch($enrollment, $learner, $unit, $au);
        $this->assertStringStartsWith(self::CONTENT . '/cmi5/', $location);
        $token = explode('/', substr($location, strlen(self::CONTENT . '/cmi5/')))[0];

        $file = $this->get(self::CONTENT . '/cmi5/' . $token . '/inhalt/kurs/au1/js/app.js');
        $file->assertOk();
        $csp = (string) $file->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self' " . self::APP, $csp, 'Die AU muss das LRS der Anwendung erreichen.');
        $this->assertStringContainsString("frame-ancestors 'self' " . self::APP, $csp);
        $this->assertEmpty($file->headers->getCookies(), 'Der Inhalts-Host setzt keine Cookies.');

        $this->actingAs($learner)
            ->get(rtrim(route('learning.my.cmi5.asset', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]), '/') . '/kurs/au1/js/app.js')
            ->assertNotFound();

        // Getrennte Schlüsselzwecke: Ein cmi5-Token öffnet keinen SCORM-Inhalt und umgekehrt.
        $tokens = app(ScormContentToken::class);
        $this->assertNotNull($tokens->verify($token, null, ScormContentToken::KIND_CMI5));
        $this->assertNull($tokens->verify($token));
        $this->get(self::CONTENT . '/scorm/' . $token . '/inhalt/kurs/au1/js/app.js')->assertNotFound();
    }

    public function test_the_learner_page_lists_each_au_with_its_state_and_start(): void {
        [$enrollment, $learner, $unit, $au] = $this->scenario();

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee('Grundlagen')
            ->assertSee((string) __('learning.cmi5.status.open'))
            ->assertSee(route('learning.my.cmi5.launch', [$enrollment, $unit, $au]), false)
            ->assertDontSee(route('learning.my.units.complete', [$enrollment, $unit]), false);
    }

    public function test_the_health_check_warns_while_packaged_au_run_on_the_app_origin(): void {
        $this->scenario();
        $warnings = static fn (): array => array_column(app(SystemHealthCommand::class)->runWarnings(), 0);

        $this->assertContains('SCORM-Inhalte', $warnings());

        $this->useContentHost();
        $this->assertNotContains('SCORM-Inhalte', $warnings());
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    private function useContentHost(): void {
        config(['app.url' => self::APP, 'learning.scorm.content_url' => self::CONTENT]);
        ScormContentRoutes::register();
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    /** @return array{LearningEnrollment, User, LearningUnit, LearningCmi5Unit} */
    private function scenario(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Erste Hilfe']);
        $unit = $courses->addUnit($course, ['title' => 'Grundkurs', 'kind' => LearningUnitKind::Cmi5->value]);

        $base = (string) tempnam(sys_get_temp_dir(), 'cmi5');
        $zipPath = $base . '.zip';
        $this->files[] = $base;
        $this->files[] = $zipPath;

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('kurs/cmi5.xml', <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<courseStructure xmlns="https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd">
  <course id="https://example.org/kurse/erste-hilfe">
    <title><langstring lang="de-DE">Erste Hilfe</langstring></title>
  </course>
  <au id="https://example.org/au/grundlagen" moveOn="Completed">
    <title><langstring lang="de-DE">Grundlagen</langstring></title>
    <url>au1/index.html?kapitel=1</url>
  </au>
</courseStructure>
XML);
        $zip->addFromString('kurs/au1/index.html', '<html><body>AU</body></html>');
        $zip->addFromString('kurs/au1/js/app.js', 'console.log(1);');
        $zip->close();

        $package = app(LearningCmi5Service::class)->import($unit, $zipPath, 'kurs.zip');
        $this->folders[] = (string) $package->storage_path;

        $courses->release($course, null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        $au = $package->units()->first();
        $this->assertInstanceOf(LearningCmi5Unit::class, $au);

        return [$enrollment, $learner, $unit->refresh(), $au];
    }

    private function launch(LearningEnrollment $enrollment, User $learner, LearningUnit $unit, LearningCmi5Unit $au): string {
        $response = $this->actingAs($learner)->post(route('learning.my.cmi5.launch', [
            'enrollment' => $enrollment->sqid,
            'unit' => $unit->sqid,
            'au' => $au->sqid,
        ]));
        $response->assertRedirect();

        return (string) $response->headers->get('Location');
    }
}
