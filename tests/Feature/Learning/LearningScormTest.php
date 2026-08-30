<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningScormTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\{LearningProgressStatus, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningScormPackage, LearningUnit, LearningXapiStatement};
use App\Models\User;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService, LearningScormService};
use App\Services\Learning\Scorm\ScormManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/**
 * SCORM und xAPI (Feature 149, MVP-743).
 *
 * Die drei Punkte, an denen es schiefgehen kann: das Entpacken fremder
 * Archive, die Auslieferung der Inhaltsdateien und die Deutung dessen, was
 * der Inhalt zurückmeldet. Vor allem der dritte — „abgeschlossen" ist nicht
 * „bestanden".
 */
class LearningScormTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @var list<string> */
    private array $tempFiles = [];

    /** @var list<string> */
    private array $extractedPaths = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        // Der Import legt echte Dateien ab — die bleiben sonst liegen.
        foreach ($this->extractedPaths as $path) {
            File::deleteDirectory(storage_path('app/' . $path));
        }

        $this->tempFiles = [];
        $this->extractedPaths = [];

        parent::tearDown();
    }

    // ── Hilfen ──────────────────────────────────────────────────────────

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    private function courses(): LearningCourseService {
        return app(LearningCourseService::class);
    }

    private function scorm(): LearningScormService {
        return app(LearningScormService::class);
    }

    private function manifest12(): string {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <manifest identifier="M1" version="1"
                  xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
                  xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
          <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
          <organizations default="ORG"><organization identifier="ORG">
            <title>Brandschutz</title>
            <item identifier="I1" identifierref="R1"><title>Teil 1</title></item>
          </organization></organizations>
          <resources><resource identifier="R1" adlcp:scormtype="sco" href="index.html">
            <file href="index.html"/>
          </resource></resources>
        </manifest>
        XML;
    }

    private function manifest2004(): string {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <manifest identifier="M2" version="1"
                  xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
                  xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3">
          <metadata><schema>ADL SCORM</schema><schemaversion>2004 4th Edition</schemaversion></metadata>
          <organizations default="ORG"><organization identifier="ORG">
            <title>Ladungssicherung</title>
            <item identifier="I1" identifierref="R1"><title>Teil 1</title></item>
          </organization></organizations>
          <resources><resource identifier="R1" adlcp:scormType="sco" href="start.html">
            <file href="start.html"/>
          </resource></resources>
        </manifest>
        XML;
    }

    /** @param array<string, string> $files */
    private function makeZip(array $files): string {
        $path = tempnam(sys_get_temp_dir(), 'scorm') . '.zip';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function scormUnit(string $manifest, string $launchFile = 'index.html'): LearningUnit {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'SCORM-Kurs']);
        $unit = $this->courses()->addUnit($course, ['title' => 'Modul', 'kind' => LearningUnitKind::Scorm->value]);

        $zip = $this->makeZip([
            'imsmanifest.xml' => $manifest,
            $launchFile => '<html><body>Inhalt</body></html>',
            'js/app.js' => 'console.log(1);',
        ]);

        $this->extractedPaths[] = $this->scorm()->import($unit, $zip)->storage_path;

        return $unit->refresh();
    }

    private function enrolledLearner(LearningUnit $unit): LearningEnrollment {
        /** @var LearningCourse $course */
        $course = $unit->course()->first();
        $this->courses()->release($course, null);

        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        return app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);
    }

    // ── Import ──────────────────────────────────────────────────────────

    public function test_import_liest_manifest_und_legt_paket_an(): void {
        $unit = $this->scormUnit($this->manifest12());
        $package = $unit->scormPackage;

        $this->assertNotNull($package);
        $this->assertSame(ScormManifest::VERSION_12, $package->version);
        $this->assertSame('index.html', $package->launch_href);
        $this->assertSame('Brandschutz', $package->title);
        $this->assertSame('API', $package->apiObjectName());
        $this->assertFileExists(storage_path('app/' . $package->storage_path . '/index.html'));
    }

    public function test_import_erkennt_scorm_2004(): void {
        $unit = $this->scormUnit($this->manifest2004(), 'start.html');
        $package = $unit->scormPackage;

        $this->assertNotNull($package);
        $this->assertSame(ScormManifest::VERSION_2004, $package->version);
        $this->assertSame('API_1484_11', $package->apiObjectName());
    }

    public function test_import_ohne_manifest_wird_abgelehnt(): void {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'SCORM-Kurs']);
        $unit = $this->courses()->addUnit($course, ['title' => 'Modul', 'kind' => LearningUnitKind::Scorm->value]);
        $zip = $this->makeZip(['index.html' => '<html></html>']);

        $this->expectException(ValidationException::class);
        $this->scorm()->import($unit, $zip);
    }

    public function test_import_packt_ausfuehrbare_dateien_nicht_aus(): void {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'SCORM-Kurs']);
        $unit = $this->courses()->addUnit($course, ['title' => 'Modul', 'kind' => LearningUnitKind::Scorm->value]);

        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest12(),
            'index.html' => '<html></html>',
            'shell.php' => '<?php system($_GET["c"]); ?>',
            '.htaccess' => 'php_flag engine on',
        ]);

        $package = $this->scorm()->import($unit, $zip);
        $this->extractedPaths[] = $package->storage_path;
        $base = storage_path('app/' . $package->storage_path);

        $this->assertFileExists($base . '/index.html');
        $this->assertFileDoesNotExist($base . '/shell.php');
        $this->assertFileDoesNotExist($base . '/.htaccess');
    }

    public function test_erneuter_import_ersetzt_das_alte_paket(): void {
        $unit = $this->scormUnit($this->manifest12());
        $first = $unit->scormPackage?->id;
        $firstPath = (string) $unit->scormPackage?->storage_path;

        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest2004(),
            'start.html' => '<html></html>',
        ]);
        $this->extractedPaths[] = $this->scorm()->import($unit, $zip)->storage_path;

        $this->assertSame(1, LearningScormPackage::query()->where('learning_unit_id', $unit->id)->count());
        $this->assertNotSame($first, $unit->refresh()->scormPackage?->id);
        // Die Dateien des abgelösten Pakets bleiben nicht liegen.
        $this->assertDirectoryDoesNotExist(storage_path('app/' . $firstPath));
    }

    public function test_autor_laedt_das_paket_ueber_die_oberflaeche_hoch(): void {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'SCORM-Kurs']);
        $unit = $this->courses()->addUnit($course, ['title' => 'Modul', 'kind' => LearningUnitKind::Scorm->value]);

        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest12(),
            'index.html' => '<html></html>',
        ]);

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.scorm.import', ['course' => $course->sqid, 'unit' => $unit->sqid]), [
                'package' => new UploadedFile($zip, 'kurs.zip', 'application/zip', null, true),
            ])
            ->assertRedirect();

        $package = $unit->refresh()->scormPackage;
        $this->assertNotNull($package);
        $this->extractedPaths[] = $package->storage_path;
    }

    public function test_ohne_autorenrecht_kein_import(): void {
        $course = $this->courses()->createCourse($this->organization, null, ['title' => 'SCORM-Kurs']);
        $unit = $this->courses()->addUnit($course, ['title' => 'Modul', 'kind' => LearningUnitKind::Scorm->value]);
        $outsider = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $zip = $this->makeZip(['imsmanifest.xml' => $this->manifest12(), 'index.html' => '<html></html>']);

        $this->actingAs($outsider)
            ->post(route('learning.courses.units.scorm.import', ['course' => $course->sqid, 'unit' => $unit->sqid]), [
                'package' => new UploadedFile($zip, 'kurs.zip', 'application/zip', null, true),
            ])
            ->assertForbidden();

        $this->assertNull($unit->refresh()->scormPackage);
    }

    // ── Auslieferung ────────────────────────────────────────────────────

    public function test_player_und_inhaltsdatei_sind_fuer_den_eingeschriebenen_erreichbar(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);

        $this->actingAs($enrollment->user)
            ->get(route('learning.my.scorm.play', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]))
            ->assertOk()
            ->assertSee('scorm/inhalt/index.html', false);

        $response = $this->actingAs($enrollment->user)->get(
            route('learning.my.scorm.asset', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]) . '/js/app.js'
        );

        $response->assertOk();
        // Fremder Code bekommt eine eigene, enge CSP ohne Netzwerkziele.
        $this->assertStringContainsString("connect-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_pfad_ausserhalb_des_pakets_liefert_404(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);

        $this->actingAs($enrollment->user)
            ->get(route('learning.my.scorm.asset', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]) . '/../../../.env')
            ->assertNotFound();
    }

    public function test_fremde_einschreibung_ist_nicht_erreichbar(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)
            ->get(route('learning.my.scorm.play', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]))
            ->assertNotFound();
    }

    // ── Rückmeldung des Inhalts ─────────────────────────────────────────

    public function test_completed_schliesst_die_einheit_ab(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);

        $this->actingAs($enrollment->user)
            ->postJson(route('learning.my.scorm.commit', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]), [
                'lesson_status' => 'completed',
                'score_scaled' => 0.9,
                'suspend_data' => 'seite=3',
                'session_seconds' => 120,
            ])
            ->assertOk()
            ->assertJson(['passed' => true]);

        $progress = $enrollment->refresh()->progress()->where('learning_unit_id', $unit->id)->first();
        $this->assertSame(LearningProgressStatus::Completed, $progress?->status);
        $this->assertSame('seite=3', $unit->scormPackage?->states()->first()?->suspend_data);
    }

    public function test_abgeschlossen_und_durchgefallen_ist_kein_nachweis(): void {
        $unit = $this->scormUnit($this->manifest2004(), 'start.html');
        $enrollment = $this->enrolledLearner($unit);

        $this->actingAs($enrollment->user)
            ->postJson(route('learning.my.scorm.commit', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]), [
                'lesson_status' => 'completed',
                'success_status' => 'failed',
                'score_scaled' => 0.2,
            ])
            ->assertOk()
            ->assertJson(['passed' => false]);

        $this->assertNull($enrollment->refresh()->progress()->where('learning_unit_id', $unit->id)->first());
    }

    public function test_lernzeit_summiert_sich_ueber_die_sitzungen(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);
        $route = route('learning.my.scorm.commit', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]);

        $this->actingAs($enrollment->user)->postJson($route, ['session_seconds' => 60])->assertOk();
        $this->actingAs($enrollment->user)->postJson($route, ['session_seconds' => 30])->assertOk();

        $this->assertSame(90, $unit->scormPackage?->states()->first()?->session_seconds);
    }

    // ── xAPI ────────────────────────────────────────────────────────────

    public function test_xapi_statement_wird_abgelegt_und_schliesst_ab(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);

        $this->actingAs($enrollment->user)
            ->postJson(route('learning.my.xapi.store', ['enrollment' => $enrollment->sqid]), [
                'statement' => [
                    'id' => (string) Str::uuid(),
                    'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
                    'object' => ['id' => 'https://example.test/kurs/1'],
                ],
            ])
            ->assertOk();

        $this->assertSame(1, LearningXapiStatement::query()->count());
        $this->assertSame(
            LearningProgressStatus::Completed,
            $enrollment->refresh()->progress()->where('learning_unit_id', $unit->id)->first()?->status
        );
    }

    public function test_xapi_speichert_dieselbe_id_nicht_zweimal(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);
        $id = (string) Str::uuid();

        $payload = [
            'statement' => [
                'id' => $id,
                'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/experienced'],
                'object' => ['id' => 'https://example.test/kurs/1'],
            ],
        ];

        $route = route('learning.my.xapi.store', ['enrollment' => $enrollment->sqid]);
        $this->actingAs($enrollment->user)->postJson($route, $payload)->assertOk();
        $this->actingAs($enrollment->user)->postJson($route, $payload)->assertOk();

        $this->assertSame(1, LearningXapiStatement::query()->where('statement_id', $id)->count());
    }

    public function test_xapi_ordnet_bei_mehreren_einheiten_nichts_zu(): void {
        $unit = $this->scormUnit($this->manifest12());
        /** @var LearningCourse $course */
        $course = $unit->course()->first();
        // Zweite SCORM-Einheit: welche das Statement meint, wäre jetzt
        // geraten — also wird nichts gesetzt, das Statement bleibt aber
        // archiviert. (Eine Textseite daneben macht es nicht mehrdeutig.)
        $this->courses()->addUnit($course, ['title' => 'Zweites Modul', 'kind' => LearningUnitKind::Scorm->value]);
        $enrollment = $this->enrolledLearner($unit);

        $this->actingAs($enrollment->user)
            ->postJson(route('learning.my.xapi.store', ['enrollment' => $enrollment->sqid]), [
                'statement' => [
                    'id' => (string) Str::uuid(),
                    'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
                    'object' => ['id' => 'https://example.test/kurs/1'],
                ],
            ])
            ->assertOk();

        $this->assertSame(1, LearningXapiStatement::query()->count());
        $this->assertSame(0, $enrollment->refresh()->progress()
            ->where('status', LearningProgressStatus::Completed->value)->count());
    }

    public function test_xapi_completed_mit_misserfolg_schliesst_nicht_ab(): void {
        $unit = $this->scormUnit($this->manifest12());
        $enrollment = $this->enrolledLearner($unit);

        $this->actingAs($enrollment->user)
            ->postJson(route('learning.my.xapi.store', ['enrollment' => $enrollment->sqid]), [
                'statement' => [
                    'id' => (string) Str::uuid(),
                    'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
                    'object' => ['id' => 'https://example.test/kurs/1'],
                    'result' => ['success' => false],
                ],
            ])
            ->assertOk();

        $this->assertSame(1, LearningXapiStatement::query()->count());
        $this->assertNull($enrollment->refresh()->progress()->where('learning_unit_id', $unit->id)->first());
    }
}
