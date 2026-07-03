<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvFacadeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Support\Toolkit;

use App\Support\Toolkit\CsvFacade;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsvFacadeTest extends TestCase {
    private string $tmpFile;

    protected function setUp(): void {
        parent::setUp();
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'csv_facade_test_');
        ToolkitFile::write($this->tmpFile, "name;email;number\nAlice;alice@example.com;K-001\nBob;bob@example.com;K-002\n");
    }

    protected function tearDown(): void {
        @unlink($this->tmpFile);
        parent::tearDown();
    }

    #[Test]
    public function it_streams_assoc_rows(): void {
        $rows = iterator_to_array(CsvFacade::streamAssoc($this->tmpFile), false);

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('alice@example.com', $rows[0]['email']);
        self::assertSame('K-001', $rows[0]['number']);
        self::assertSame('Bob', $rows[1]['name']);
        self::assertSame('K-002', $rows[1]['number']);
    }

    #[Test]
    public function it_builds_csv(): void {
        $csv = CsvFacade::buildCsv(
            ['name', 'value'],
            [['name' => 'Alpha', 'value' => '1'], ['name' => 'Beta', 'value' => '2']],
        );

        self::assertStringContainsString('name', $csv);
        self::assertStringContainsString('Alpha', $csv);
        self::assertStringContainsString('Beta', $csv);
    }
}
