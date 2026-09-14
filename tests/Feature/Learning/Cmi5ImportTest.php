<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5ImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Models\Learning\{LearningCmi5Package, LearningCmi5Unit, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningCmi5Service, LearningCourseService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/** cmi5-Kurse importieren (Feature 149). */
class Cmi5ImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        foreach ($this->cleanup as $path) {
            is_dir($path) ? File::deleteDirectory($path) : @unlink($path);
        }

        parent::tearDown();
    }

    private function unit(): LearningUnit {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'cmi5-Kurs']);

        return $courses->addUnit($course, ['title' => 'Modul', 'kind' => LearningUnitKind::Cmi5->value]);
    }

    private function structure(string $firstUrl = 'au1/index.html', string $masteryScore = '0.8'): string {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<courseStructure xmlns="https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd">
  <course id="https://example.org/kurse/arbeitsschutz">
    <title><langstring lang="de-DE">Arbeitsschutz</langstring></title>
  </course>
  <au id="https://example.org/au/einleitung" moveOn="Completed">
    <title><langstring lang="de-DE">Einleitung</langstring></title>
    <url>{$firstUrl}</url>
  </au>
  <block id="https://example.org/block/pruefung">
    <title><langstring lang="de-DE">Prüfung</langstring></title>
    <au id="https://example.org/au/test" moveOn="Passed" masteryScore="{$masteryScore}" launchMethod="OwnWindow">
      <title><langstring lang="de-DE">Test</langstring></title>
      <url>https://content.example.org/test/start.html</url>
      <launchParameters>Modus=Pruefung</launchParameters>
    </au>
  </block>
</courseStructure>
XML;
    }

    /** @param array<string, string> $entries */
    private function zip(array $entries): string {
        $path = tempnam(sys_get_temp_dir(), 'cmi5') . '.zip';
        $this->cleanup[] = $path;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    private function xmlFile(string $xml): string {
        $path = tempnam(sys_get_temp_dir(), 'cmi5') . '.xml';
        $this->cleanup[] = $path;
        file_put_contents($path, $xml);

        return $path;
    }

    private function track(LearningCmi5Package $package): LearningCmi5Package {
        if ($package->storage_path !== null) {
            $this->cleanup[] = storage_path('app/' . $package->storage_path);
        }

        return $package;
    }

    public function test_a_zip_package_is_imported_with_units_relative_to_the_structure(): void {
        $unit = $this->unit();

        $package = $this->track(app(LearningCmi5Service::class)->import($unit, $this->zip([
            'kurs/cmi5.xml' => $this->structure(),
            'kurs/au1/index.html' => '<html></html>',
            'kurs/shell.php' => '<?php echo 1;',
        ]), 'kurs.zip'));

        $this->assertSame('Arbeitsschutz', $package->title);
        $this->assertSame('https://example.org/kurse/arbeitsschutz', $package->course_id);
        $this->assertStringStartsWith('urn:uuid:', $package->activity_id, 'Auch die Kurs-ID vergibt das LMS.');
        $this->assertCount(1, $package->blocks ?? []);
        $block = ($package->blocks ?? [])[0];
        $this->assertSame('https://example.org/block/pruefung', $block['publisher_id']);
        $this->assertSame('Prüfung', $block['title']);
        $this->assertSame(['https://example.org/au/test'], $block['units']);
        $this->assertStringStartsWith('urn:uuid:', $block['activity_id']);
        $this->assertNotNull($package->storage_path);
        $this->assertFileExists(storage_path('app/' . $package->storage_path . '/kurs/au1/index.html'));
        $this->assertFileDoesNotExist(storage_path('app/' . $package->storage_path . '/kurs/shell.php'));

        $units = $package->units()->get();
        $this->assertCount(2, $units);
        /** @var LearningCmi5Unit $intro */
        $intro = $units[0];
        $this->assertSame('https://example.org/au/einleitung', $intro->publisher_id);
        $this->assertSame('kurs/au1/index.html', $intro->url, 'Relativ zur cmi5.xml, nicht zum Paketstamm.');
        $this->assertFalse($intro->isExternal());
        $this->assertStringStartsWith('urn:uuid:', $intro->activity_id);
        $this->assertNotSame($intro->publisher_id, $intro->activity_id, 'Die Aktivitäts-ID vergibt das LMS.');

        /** @var LearningCmi5Unit $test */
        $test = $units[1];
        $this->assertTrue($test->isExternal());
        $this->assertSame('Passed', $test->move_on);
        $this->assertSame(0.8, $test->masteryScore());
        $this->assertSame('OwnWindow', $test->launch_method);
        $this->assertSame('Modus=Pruefung', $test->launch_parameters);
    }

    public function test_a_single_structure_with_external_units_needs_no_package(): void {
        $unit = $this->unit();

        $package = app(LearningCmi5Service::class)->import($unit, $this->xmlFile($this->structure('https://content.example.org/einleitung/index.html')), 'cmi5.xml');

        $this->assertNull($package->storage_path);
        $this->assertSame(2, $package->units()->count());
    }

    public function test_a_single_structure_with_relative_units_is_rejected(): void {
        $this->expectException(ValidationException::class);

        app(LearningCmi5Service::class)->import($this->unit(), $this->xmlFile($this->structure()), 'cmi5.xml');
    }

    public function test_structural_errors_are_translated_and_leave_no_folder_behind(): void {
        $unit = $this->unit();
        $root = storage_path('app/learning/cmi5/' . $this->organization->id);
        $before = is_dir($root) ? (glob($root . '/*') ?: []) : [];

        try {
            app(LearningCmi5Service::class)->import($unit, $this->zip([
                'cmi5.xml' => $this->structure('au1/index.html', '1.5'),
                'au1/index.html' => '<html></html>',
            ]), 'kurs.zip');
            $this->fail('Ein ungültiger masteryScore darf nicht importiert werden.');
        } catch (ValidationException $e) {
            $this->assertSame([__('learning.errors.cmi5.invalid_mastery_score')], $e->errors()['package']);
        }

        $this->assertSame($before, is_dir($root) ? (glob($root . '/*') ?: []) : []);
        $this->assertSame(0, LearningCmi5Package::query()->count());
    }

    public function test_a_new_import_replaces_the_previous_course(): void {
        $unit = $this->unit();
        $service = app(LearningCmi5Service::class);

        $first = $this->track($service->import($unit, $this->zip(['cmi5.xml' => $this->structure(), 'au1/index.html' => 'x']), 'a.zip'));
        $firstFolder = storage_path('app/' . $first->storage_path);
        $second = $this->track($service->import($unit, $this->zip(['cmi5.xml' => $this->structure(), 'au1/index.html' => 'y']), 'b.zip'));

        $this->assertSame(1, LearningCmi5Package::query()->where('learning_unit_id', $unit->id)->count());
        $this->assertNotSame($first->id, $second->id);
        $this->assertDirectoryDoesNotExist($firstFolder, 'Die Dateien des abgelösten Kurses gehören weg.');
    }

    public function test_only_course_authors_may_import(): void {
        $unit = $this->unit();
        /** @var \App\Models\Learning\LearningCourse $course */
        $course = $unit->course()->first();
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($learner)
            ->post(route('learning.courses.units.cmi5.import', ['course' => $course->sqid, 'unit' => $unit->sqid]), [
                'package' => UploadedFile::fake()->createWithContent('cmi5.xml', $this->structure('https://content.example.org/a')),
            ])
            ->assertForbidden();

        $author = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($author)
            ->post(route('learning.courses.units.cmi5.import', ['course' => $course->sqid, 'unit' => $unit->sqid]), [
                'package' => UploadedFile::fake()->createWithContent('cmi5.xml', $this->structure('https://content.example.org/a')),
            ])
            ->assertRedirect(route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid]));

        $this->assertSame(1, LearningCmi5Package::query()->count());
    }
}
