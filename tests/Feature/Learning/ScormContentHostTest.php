<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormContentHostTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Console\Commands\SystemHealthCommand;
use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningScormPackage, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningScormService, ScormContentToken};
use App\Support\Learning\{ScormContentHost, ScormContentRoutes};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Route};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/**
 * SCORM vom eigenen Inhalts-Host (Sicherheitsaudit `files-1`).
 *
 * Fremder Kurscode darf nicht im Ursprung der Anwendung laufen. Mit konfiguriertem
 * Inhalts-Host bettet die Player-Seite nur eine Hülle von dort ein, die Dateien
 * kommen nur von dort, und die Anwendung selbst antwortet dort nicht.
 */
class ScormContentHostTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const APP = 'https://work.example.test';
    private const CONTENT = 'https://lerninhalte.example.test';

    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        config(['app.url' => self::APP, 'learning.scorm.content_url' => self::CONTENT]);
        ScormContentRoutes::register();
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    protected function tearDown(): void {
        CarbonImmutable::setTestNow();

        foreach ($this->cleanup as $path) {
            File::deleteDirectory(storage_path('app/' . $path));
        }

        parent::tearDown();
    }

    /** @return array{0: LearningEnrollment, 1: LearningUnit, 2: LearningScormPackage} */
    private function enrolledPackage(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'SCORM-Kurs']);
        $unit = $courses->addUnit($course, ['title' => 'Modul', 'kind' => LearningUnitKind::Scorm->value]);

        $zipPath = tempnam(sys_get_temp_dir(), 'scorm') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('imsmanifest.xml', '<?xml version="1.0"?><manifest xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">'
            . '<metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>'
            . '<organizations default="O"><organization identifier="O"><title>Brandschutz</title>'
            . '<item identifier="I" identifierref="R"><title>Teil</title></item></organization></organizations>'
            . '<resources><resource identifier="R" adlcp:scormtype="sco" href="index.html"/></resources></manifest>');
        $zip->addFromString('index.html', '<html><body>Inhalt</body></html>');
        $zip->addFromString('js/app.js', 'console.log(1);');
        $zip->close();

        $package = app(LearningScormService::class)->import($unit, $zipPath);
        @unlink($zipPath);
        $this->cleanup[] = $package->storage_path;

        /** @var LearningCourse $releasable */
        $releasable = $unit->course()->first();
        $courses->release($releasable, null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($releasable->refresh(), $learner);

        return [$enrollment, $unit->refresh(), $package];
    }

    public function test_the_token_is_signed_and_expires(): void {
        [$enrollment, $unit, $package] = $this->enrolledPackage();
        $tokens = app(ScormContentToken::class);
        $now = CarbonImmutable::parse('2026-09-14 10:00:00');

        $token = $tokens->issue($enrollment, $unit, $package, $now);

        $this->assertSame(['enrollment' => $enrollment->id, 'unit' => $unit->id, 'package' => $package->id], $tokens->verify($token, $now));
        $this->assertNull($tokens->verify($token, $now->addHours(9)), 'Nach Ablauf öffnet der Token nichts mehr.');

        [$body, $mac] = explode('.', $token);
        $forged = rtrim(strtr(base64_encode((string) json_encode(['e' => $enrollment->id, 'u' => $unit->id, 'p' => $package->id + 1, 'x' => $now->getTimestamp() + 3600])), '+/', '-_'), '=');
        $this->assertNull($tokens->verify($forged . '.' . $mac, $now), 'Ein verändertes Paket bricht die Signatur.');
        $this->assertNull($tokens->verify('kaputt', $now));
    }

    public function test_the_player_embeds_the_wrapper_from_the_content_host(): void {
        [$enrollment, $unit] = $this->enrolledPackage();

        $response = $this->actingAs($enrollment->user)
            ->get(route('learning.my.scorm.play', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]));

        $response->assertOk()->assertSee(self::CONTENT . '/scorm/', false)->assertSee('/huelle', false);
        $this->assertStringContainsString("frame-src 'self' " . self::CONTENT, (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_wrapper_and_files_are_served_only_by_the_content_host(): void {
        [$enrollment, $unit, $package] = $this->enrolledPackage();
        $token = app(ScormContentToken::class)->issue($enrollment, $unit, $package);

        $wrapper = $this->get(self::CONTENT . '/scorm/' . $token . '/huelle');
        // @json maskiert Schrägstriche — verglichen wird deshalb die JSON-Form.
        $wrapper->assertOk()->assertSee('API_1484_11', false)->assertSee((string) json_encode('/scorm/' . $token . '/inhalt/index.html'), false);
        $wrapperCsp = (string) $wrapper->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors ' . self::APP, $wrapperCsp, 'Nur die Anwendung darf die Hülle einbetten.');
        $this->assertStringContainsString("default-src 'none'", $wrapperCsp);
        $this->assertStringNotContainsString($enrollment->user->name, (string) $wrapper->getContent(), 'Personendaten kommen erst per Nachricht.');
        $this->assertEmpty($wrapper->headers->getCookies(), 'Der Inhalts-Host setzt keine Cookies.');

        $asset = $this->get(self::CONTENT . '/scorm/' . $token . '/inhalt/js/app.js');
        $asset->assertOk();
        $this->assertStringContainsString("frame-ancestors 'self' " . self::APP, (string) $asset->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("connect-src 'self'", (string) $asset->headers->get('Content-Security-Policy'));

        $this->get(self::CONTENT . '/scorm/' . $token . '/inhalt/../../../.env')->assertNotFound();
        $this->get(self::CONTENT . '/scorm/nicht.gueltig/inhalt/index.html')->assertNotFound();
    }

    public function test_the_same_origin_delivery_is_switched_off(): void {
        [$enrollment, $unit] = $this->enrolledPackage();

        $this->actingAs($enrollment->user)
            ->get(route('learning.my.scorm.asset', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]) . '/js/app.js')
            ->assertNotFound();
    }

    public function test_the_application_does_not_answer_on_the_content_host(): void {
        $this->get(self::CONTENT . '/login')->assertNotFound();
        $this->getJson(self::CONTENT . '/api/v1/me')->assertNotFound();
    }

    public function test_content_routes_are_throttled(): void {
        foreach (['learning.scorm-content.wrapper', 'learning.scorm-content.asset'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertTrue(collect($route->gatherMiddleware())->contains(static fn (string $m): bool => str_starts_with($m, 'throttle')), $name);
        }
    }

    public function test_same_origin_as_the_app_counts_as_not_configured(): void {
        config(['learning.scorm.content_url' => self::APP . '/lernen']);

        $this->assertFalse(ScormContentHost::isConfigured(), 'Sonst würde die Sperre die ganze Anwendung abschalten.');
    }

    public function test_session_cookie_gets_the_host_prefix_only_when_the_browser_accepts_it(): void {
        $this->assertSame('__Host-workdiary-session', ScormContentHost::sessionCookieName('workdiary-session', true, null, self::CONTENT));
        $this->assertSame('workdiary-session', ScormContentHost::sessionCookieName('workdiary-session', true, null, null));
        $this->assertSame('workdiary-session', ScormContentHost::sessionCookieName('workdiary-session', false, null, self::CONTENT), 'Ohne Secure verwirft der Browser __Host-.');
        $this->assertSame('workdiary-session', ScormContentHost::sessionCookieName('workdiary-session', true, '.example.test', self::CONTENT), 'Mit Domain verwirft der Browser __Host-.');
    }

    public function test_the_health_check_warns_while_scorm_runs_in_the_app_origin(): void {
        $this->enrolledPackage();

        $this->assertNotContains('SCORM-Inhalte', array_column(app(SystemHealthCommand::class)->runWarnings(), 0));

        config(['learning.scorm.content_url' => null]);
        $this->assertContains('SCORM-Inhalte', array_column(app(SystemHealthCommand::class)->runWarnings(), 0));
    }
}
