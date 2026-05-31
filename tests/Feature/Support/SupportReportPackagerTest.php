<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportPackagerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Services\Support\SupportReportPackager;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class SupportReportPackagerTest extends TestCase {
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void {
        foreach ($this->tmpFiles as $path) {
            if (File::isFile($path)) {
                File::delete($path);
            }
        }
        parent::tearDown();
    }

    public function test_package_creates_zip_with_sha256_and_size(): void {
        $target = sys_get_temp_dir() . '/support-pkg-' . uniqid('', true) . '.zip';
        $this->tmpFiles[] = $target;

        $packager = new SupportReportPackager();
        $result = $packager->package(['generated_at' => '2026-05-24T10:00:00+00:00', 'foo' => 'bar'], null, $target);

        $this->assertFileExists($target);
        $this->assertSame($target, $result['path']);
        $this->assertGreaterThan(0, $result['bytes']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['sha256']);
        $this->assertFalse($result['password_set']);

        // Hash bestätigt Inhalt der Datei.
        $this->assertSame($result['sha256'], ToolkitFile::hash($target));
    }

    public function test_package_zip_contains_support_report_json(): void {
        $target = sys_get_temp_dir() . '/support-pkg-' . uniqid('', true) . '.zip';
        $this->tmpFiles[] = $target;

        $packager = new SupportReportPackager();
        $packager->package(['hello' => 'world'], null, $target);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($target) === true);
        $json = $zip->getFromName('support-report.json');
        $zip->close();

        $this->assertIsString($json);
        $decoded = JsonHelper::decode($json);
        $this->assertSame('world', $decoded['hello']);
    }

    public function test_preview_lists_top_sections_by_size(): void {
        $packager = new SupportReportPackager();
        $bundle = [
            'small' => ['a' => 1],
            'huge' => str_repeat('x', 4096),
            'medium' => str_repeat('m', 1024),
        ];

        $preview = $packager->preview($bundle);

        $this->assertGreaterThan(0, $preview['total_estimated_kb']);
        $this->assertSame('huge', $preview['top_sections'][0]['key']);
        $this->assertGreaterThanOrEqual($preview['top_sections'][1]['kb'], $preview['top_sections'][0]['kb']);
    }
}
