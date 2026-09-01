<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityTxtTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Support\DatabaseHealth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * CVD-Meldekanal /.well-known/security.txt (RFC 9116, CRA-Welle 1):
 * öffentlich, config-getrieben, 404 ohne konfigurierten Kontakt.
 */
class SecurityTxtTest extends TestCase {
    public function test_returns_404_without_configured_contact(): void {
        config(['security.txt.contact' => null]);

        $this->get('/.well-known/security.txt')->assertNotFound();
    }

    public function test_serves_rfc9116_fields_with_mailto_normalization(): void {
        config(['security.txt.contact' => 'security@example.org']);

        $response = $this->get('/.well-known/security.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $body = $response->getContent();
        $this->assertStringContainsString('Contact: mailto:security@example.org', $body);
        $this->assertStringContainsString('Preferred-Languages: de, en', $body);
        $this->assertStringContainsString('Canonical: ' . url('/.well-known/security.txt'), $body);

        // Expires muss vorhanden, RFC-3339-formatiert und in der Zukunft sein.
        $this->assertMatchesRegularExpression('/^Expires: (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)$/m', $body);
        preg_match('/^Expires: (.+)$/m', $body, $matches);
        $this->assertTrue(now()->lt(\Illuminate\Support\Carbon::parse($matches[1])));
    }

    public function test_uri_contact_and_policy_are_passed_through(): void {
        config([
            'security.txt.contact' => 'https://example.org/security-form',
            'security.txt.policy' => 'https://example.org/cvd-policy',
        ]);

        $body = $this->get('/.well-known/security.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Contact: https://example.org/security-form', $body);
        $this->assertStringContainsString('Policy: https://example.org/cvd-policy', $body);
    }

    /**
     * Der Meldekanal muss auch dann antworten, wenn die Datenbank weg ist
     * (CRA-Tabletop 2026-09-01).
     *
     * Er lag im `web`-Stack: `HandleDatabaseUnavailable` hätte ihn mit 503
     * beantwortet und `StartSession` (SESSION_DRIVER=database) ihn ohne
     * Datenbank gar nicht erst zustande kommen lassen. Wer die Datenbank
     * lahmlegt, hätte damit nebenbei den Weg gekappt, auf dem man ihm das
     * meldet. Der Endpunkt liest nur `config()` — er braucht nichts davon.
     */
    public function test_stays_reachable_while_the_database_is_marked_down(): void {
        config(['security.txt.contact' => 'security@example.org']);
        DatabaseHealth::markUnavailable(DatabaseHealth::defaultConnection(), 'Tabletop-Probe');

        try {
            $this->get('/.well-known/security.txt')
                ->assertOk()
                ->assertSee('Contact: mailto:security@example.org', false);
        } finally {
            DatabaseHealth::reset(DatabaseHealth::defaultConnection());
        }
    }

    /** Kein Gruppen-Stack: keine Sitzung, kein Mandant, kein Torwächter. */
    public function test_route_carries_no_blocking_middleware(): void {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r): bool => $r->getName() === 'security.txt');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();
        foreach ([
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\RedirectIfNotInstalled::class,
            \App\Http\Middleware\HandleDatabaseUnavailable::class,
            \App\Http\Middleware\EnsureValidLicense::class,
            \App\Http\Middleware\SetOrganizationContext::class,
        ] as $blocking) {
            $this->assertNotContains($blocking, $middleware, $blocking . ' darf den Meldekanal nicht sperren.');
        }
    }

    public function test_sets_nosniff_and_stays_cacheable(): void {
        config(['security.txt.contact' => 'security@example.org']);

        $this->get('/.well-known/security.txt')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=3600, public');
    }

    public function test_legacy_toplevel_path_redirects_to_well_known(): void {
        config(['security.txt.contact' => 'security@example.org']);

        $this->get('/security.txt')->assertRedirect('/.well-known/security.txt');
    }
}
