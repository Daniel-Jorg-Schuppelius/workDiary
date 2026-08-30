<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormPackageExtractorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Learning;

use App\Services\Learning\Scorm\ScormPackageExtractor;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * Sicheres Entpacken fremder SCORM-Pakete (Feature 149, MVP-743).
 *
 * Ein SCORM-Paket ist eine Datei von außerhalb. Der Test spielt die drei
 * Angriffe durch, die dabei zählen: Zip-Slip, ausführbarer Code und
 * übergroße Archive.
 */
class ScormPackageExtractorTest extends TestCase {
    private string $workDir;

    protected function setUp(): void {
        parent::setUp();
        $this->workDir = sys_get_temp_dir() . '/scorm-test-' . bin2hex(random_bytes(6));
        mkdir($this->workDir, 0775, true);
    }

    protected function tearDown(): void {
        $this->removeTree($this->workDir);
        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function makeZip(array $entries): string {
        $path = $this->workDir . '/package.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function manifest(): string {
        return '<?xml version="1.0"?><manifest xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">'
            . '<metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>'
            . '<organizations default="O"><organization identifier="O"><title>T</title>'
            . '<item identifier="I" identifierref="R"><title>Start</title></item></organization></organizations>'
            . '<resources><resource identifier="R" adlcp:scormtype="sco" href="start.html"/></resources></manifest>';
    }

    private function removeTree(string $dir): void {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function test_packt_ein_gueltiges_paket_aus(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            'start.html' => '<html>Hallo</html>',
            'assets/logo.png' => 'binär',
        ]);
        $target = $this->workDir . '/out';

        $result = (new ScormPackageExtractor())->extract($zip, $target);

        $this->assertStringContainsString('ADL SCORM', $result['manifest']);
        $this->assertSame(3, $result['files']);
        $this->assertFileExists($target . '/start.html');
        $this->assertFileExists($target . '/assets/logo.png');
    }

    public function test_weist_pfade_ausserhalb_des_ziels_ab(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            '../../boeser.txt' => 'nicht hierhin',
        ]);

        $this->expectException(RuntimeException::class);
        (new ScormPackageExtractor())->extract($zip, $this->workDir . '/out');
    }

    public function test_packt_ausfuehrbare_dateien_nicht_aus(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            'start.html' => '<html></html>',
            'shell.php' => '<?php system($_GET["cmd"]); ?>',
            '.htaccess' => 'php_flag engine on',
        ]);
        $target = $this->workDir . '/out';

        $result = (new ScormPackageExtractor())->extract($zip, $target);

        $this->assertFileDoesNotExist($target . '/shell.php', 'Ausführbarer Code gehört nicht in den Auslieferungspfad.');
        $this->assertFileDoesNotExist($target . '/.htaccess');
        $this->assertSame(2, $result['files']);
    }

    public function test_deckelt_die_entpackte_groesse(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            'gross.bin' => str_repeat('A', 5000),
        ]);

        $this->expectException(RuntimeException::class);
        (new ScormPackageExtractor(maxBytes: 1000))->extract($zip, $this->workDir . '/out');
    }

    public function test_paket_ohne_manifest_wird_abgewiesen(): void {
        $zip = $this->makeZip(['start.html' => '<html></html>']);

        $this->expectException(RuntimeException::class);
        (new ScormPackageExtractor())->extract($zip, $this->workDir . '/out');
    }
}
