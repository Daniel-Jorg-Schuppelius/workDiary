<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReleaseReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Release;

use App\Models\Auth\SecurityAdvisory;
use App\Services\Release\ReleaseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-946: Sicherheits- und Datenschutzbericht je Release. */
final class ReleaseReportTest extends TestCase {
    use RefreshDatabase;

    private const CHANGELOG = "# Changelog\n\n## [Unreleased]\n\n### Added\n\n- Neue Funktion A.\n\n## [2.1.0] - 2026-09-01\n\n### Added\n\n- Auskunft nach DSGVO als PDF.\n- Rechnungen\n  mit zwei Zeilen.\n\n### Fixed\n\n- Löschfrist der Bewerbungen korrigiert.\n\n## [2.0.0]\n\n- Alt.\n";

    public function test_report_collects_changes_privacy_and_advisories(): void {
        SecurityAdvisory::query()->create(['source' => 'osv', 'external_id' => 'GHSA-1', 'ecosystem' => 'Packagist', 'package' => 'acme/lib', 'installed_version' => '1.0.0', 'severity' => 'high', 'fixed_in' => '1.0.1']);
        $service = app(ReleaseReportService::class);

        $report = $service->collect('2.1.0', self::CHANGELOG);
        $this->assertSame(['Auskunft nach DSGVO als PDF.', 'Rechnungen mit zwei Zeilen.', 'Löschfrist der Bewerbungen korrigiert.'], $report['entries']);
        $this->assertSame(['Auskunft nach DSGVO als PDF.', 'Löschfrist der Bewerbungen korrigiert.'], $report['privacy']);
        $this->assertSame('acme/lib', $report['advisories'][0]['package']);
        $this->assertGreaterThan(0, $report['components']);
        $this->assertSame(['Neue Funktion A.'], $service->collect('9.9.9', self::CHANGELOG)['entries']);

        $markdown = $service->markdown($report);
        $this->assertStringContainsString('2.1.0', $markdown);
        $this->assertStringContainsString('acme/lib (high, GHSA-1) → 1.0.1', $markdown);
    }

    public function test_command_writes_the_report(): void {
        $path = storage_path('framework/testing/release-report-test.md');
        $this->artisan('release:report', ['--release' => '0.0.1', '--output' => $path])->assertSuccessful();
        $this->assertFileExists($path);
        @unlink($path);
    }
}
