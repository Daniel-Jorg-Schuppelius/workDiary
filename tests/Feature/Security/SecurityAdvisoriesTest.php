<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityAdvisoriesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{SecurityAdvisory, User};
use App\Services\Diagnostics\DiagnosticsService;
use App\Services\Security\OsvAdvisoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 70: OSV-Sicherheitslage — Pull (querybatch + Detail-Nachladung mit
 * modified-Cache), resolved-Übergang, Gate-Command, Diagnose-Warnung und
 * Admin-Seite inkl. VEX-Statement.
 */
final class SecurityAdvisoriesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const VULN_ID = 'GHSA-test-1234';

    /** Steuert das Fake-Verhalten — Http::fake-Callbacks stapeln sich, daher
     *  EIN Callback, der diese Properties zur Laufzeit liest. */
    private bool $osvAffected = true;

    private string $osvModified = '2026-07-01T00:00:00Z';

    /** Fake: laravel/framework ist betroffen, alle anderen Pakete sauber. */
    private function fakeOsv(): void {
        Http::fake(function (ClientRequest $request) {
            if (str_contains($request->url(), '/querybatch')) {
                /** @var array<int, array{package: array{purl: string}}> $queries */
                $queries = (array) ($request->data()['queries'] ?? []);
                $results = array_map(function (array $query): array {
                    if ($this->osvAffected && str_contains($query['package']['purl'], 'laravel/framework')) {
                        return ['vulns' => [['id' => self::VULN_ID, 'modified' => $this->osvModified]]];
                    }

                    return [];
                }, $queries);

                return Http::response(['results' => $results]);
            }

            if (str_contains($request->url(), '/vulns/' . self::VULN_ID)) {
                return Http::response([
                    'id' => self::VULN_ID,
                    'summary' => 'Testlücke in laravel/framework',
                    'database_specific' => ['severity' => 'HIGH'],
                    'severity' => [['type' => 'CVSS_V3', 'score' => 'CVSS:3.1/AV:N/AC:L']],
                    'affected' => [[
                        'package' => ['ecosystem' => 'Packagist', 'name' => 'laravel/framework'],
                        'ranges' => [['type' => 'ECOSYSTEM', 'events' => [['introduced' => '0'], ['fixed' => '99.9.9']]]],
                    ]],
                ]);
            }

            return Http::response([], 404);
        });
    }

    public function test_pull_creates_updates_and_resolves(): void {
        $this->fakeOsv();

        $result = app(OsvAdvisoryService::class)->pull();

        $this->assertGreaterThan(0, $result['checked']);
        $this->assertSame(1, $result['new']);

        $advisory = SecurityAdvisory::query()->firstOrFail();
        $this->assertSame(self::VULN_ID, $advisory->external_id);
        $this->assertSame('laravel/framework', $advisory->package);
        $this->assertSame('high', $advisory->severity);
        $this->assertSame('99.9.9', $advisory->fixed_in);
        $this->assertNull($advisory->resolved_at);

        // Unverändertes modified → keine erneute Detail-Nachladung.
        $detailCalls = fn(): int => count(Http::recorded(fn(ClientRequest $r): bool => str_contains($r->url(), '/vulns/')));
        $before = $detailCalls();
        app(OsvAdvisoryService::class)->pull();
        $this->assertSame($before, $detailCalls());

        // Nicht mehr gemeldet → als behoben markiert.
        $this->osvAffected = false;
        $result = app(OsvAdvisoryService::class)->pull();
        $this->assertSame(1, $result['resolved']);
        $this->assertNotNull($advisory->refresh()->resolved_at);
    }

    public function test_check_command_signals_open_high(): void {
        $this->artisan('security:advisories-check')->assertExitCode(0);

        SecurityAdvisory::query()->create([
            'external_id' => self::VULN_ID,
            'ecosystem' => 'composer',
            'package' => 'laravel/framework',
            'installed_version' => '11.0.0',
            'severity' => 'high',
        ]);

        $this->artisan('security:advisories-check')->assertExitCode(1);
    }

    public function test_diagnostics_security_section_warns(): void {
        SecurityAdvisory::query()->create([
            'external_id' => self::VULN_ID,
            'ecosystem' => 'composer',
            'package' => 'laravel/framework',
            'installed_version' => '11.0.0',
            'severity' => 'critical',
        ]);

        $section = app(DiagnosticsService::class)->runSafe('security');

        $this->assertSame(1, $section->metrics['advisories_high_or_critical'] ?? null);
        $this->assertNotEmpty(array_filter($section->messages, fn(string $m): bool => str_contains($m, 'Sicherheitshinweise')));
    }

    public function test_security_page_lists_advisories_and_saves_statement(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $advisory = SecurityAdvisory::query()->create([
            'external_id' => self::VULN_ID,
            'ecosystem' => 'composer',
            'package' => 'laravel/framework',
            'installed_version' => '11.0.0',
            'severity' => 'high',
            'summary' => 'Testlücke',
        ]);

        $this->actingAs($admin)->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee(self::VULN_ID);

        $this->actingAs($admin)
            ->put(route('admin.security.advisories.statement', $advisory), ['statement' => 'Nicht ausnutzbar.'])
            ->assertRedirect(route('admin.security.index'));

        $this->assertSame('Nicht ausnutzbar.', $advisory->refresh()->statement);
    }
}
