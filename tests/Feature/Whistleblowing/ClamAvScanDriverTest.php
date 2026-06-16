<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClamAvScanDriverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Services\Whistleblowing\Scanning\ClamAvScanDriver;
use Tests\TestCase;

/**
 * Verifiziert die Exit-Code-Auswertung des ClamAV-Treibers OHNE echtes ClamAV:
 * `clamav_binary` zeigt auf kleine Shell-Skripte mit definiertem Exit-Code.
 * 0 = clean, 1 = Schadcode → rejected, sonst Fehler → null (fail-safe,
 * Anhang bleibt in Quarantäne).
 */
final class ClamAvScanDriverTest extends TestCase {
    /** @var list<string> */
    private array $tmp = [];

    private string $sample;

    protected function setUp(): void {
        parent::setUp();
        $this->sample = $this->tempFile('to-scan', 'irgendein Inhalt');
    }

    protected function tearDown(): void {
        foreach ($this->tmp as $path) {
            @unlink($path);
        }
        $this->tmp = [];
        parent::tearDown();
    }

    private function tempFile(string $prefix, string $content, bool $executable = false): string {
        $path = sys_get_temp_dir() . '/wb-' . $prefix . '-' . uniqid() . ($executable ? '.sh' : '.bin');
        file_put_contents($path, $content);
        if ($executable) {
            chmod($path, 0755);
        }
        $this->tmp[] = $path;

        return $path;
    }

    private function fakeBinary(int $exitCode): string {
        return $this->tempFile('clamav', "#!/bin/sh\nexit {$exitCode}\n", executable: true);
    }

    private function scanWith(string $binary): ?AttachmentScanStatus {
        config(['whistleblowing.clamav_binary' => $binary]);

        return (new ClamAvScanDriver)->scan($this->sample, 'text/plain');
    }

    public function test_exit_zero_is_clean(): void {
        $this->assertSame(AttachmentScanStatus::Clean, $this->scanWith($this->fakeBinary(0)));
    }

    public function test_exit_one_is_rejected(): void {
        $this->assertSame(AttachmentScanStatus::Rejected, $this->scanWith($this->fakeBinary(1)));
    }

    public function test_other_exit_code_is_failsafe_null(): void {
        // Exit 2 (ClamAV-Fehler) ⇒ unklar ⇒ null, Anhang bleibt in Quarantäne.
        $this->assertNull($this->scanWith($this->fakeBinary(2)));
    }

    public function test_missing_binary_is_failsafe_null(): void {
        // Nicht existierendes Binary ⇒ kein Clean/Rejected ⇒ null (fail-safe).
        $this->assertNull($this->scanWith('/nonexistent/path/to/clamdscan-' . uniqid()));
    }
}
