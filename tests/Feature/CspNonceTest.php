<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CspNonceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * CSP-Härtungs-Gate (B4/B5, MVP-345/346):
 *
 * Stufe 1 (Nonce): Mit aktivem Flag `security.csp_script_nonce` ersetzt ein
 * Pro-Request-Nonce das 'unsafe-inline' in script-src. Damit dann nichts
 * bricht, erzwingt dieses Gate auf repräsentativen Seiten (Login, Home,
 * Dashboard, Listen-Seite, Modal-Seite Schichtplan):
 *   1. JEDES <script>-Tag trägt das Nonce-Attribut (Ausnahme: reine
 *      Daten-Blöcke type="application/json" — kein Skript, CSP-neutral),
 *   2. KEINE Inline-Event-Handler-Attribute (onclick= etc.) — die sind unter
 *      Nonce-CSP grundsätzlich blockiert und nicht nonce-fähig.
 *
 * Stufe 2 (Alpine-CSP-Build): 'unsafe-eval' fällt NUR, wenn zusätzlich
 * ALPINE_CSP_BUILD aktiv ist (gleiches Flag steuert den Vite-Build-Switch,
 * siehe vite.config.js) — nie hart, solange der Standard-Build läuft.
 */
class CspNonceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    // ---- CSP-Header (Stufe 1 + Stufe 2) --------------------------------

    public function test_csp_uses_unsafe_inline_by_default(): void {
        config(['security.csp_script_nonce' => false]);
        config(['security.csp_alpine_csp_build' => false]);

        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'nonce-", $csp);
    }

    public function test_csp_uses_nonce_when_enabled_and_no_unsafe_inline_for_scripts(): void {
        config(['security.csp_script_nonce' => true]);
        config(['security.csp_alpine_csp_build' => false]);

        $response = $this->get('/login');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        // script-src nutzt jetzt ein Nonce statt 'unsafe-inline'.
        $this->assertStringContainsString("'nonce-", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        // Stufe 1: 'unsafe-eval' bleibt (Standard-Alpine-Build); entfällt erst mit @alpinejs/csp.
        $this->assertStringContainsString("'unsafe-eval'", $csp);

        // Das gerenderte Inline-Script trägt exakt diesen Nonce (sonst würde es blockiert).
        preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m[1] ?? null);
        $response->assertSee('nonce="' . $m[1] . '"', false);
    }

    public function test_unsafe_eval_removed_only_with_alpine_csp_build_flag(): void {
        // Stufe 2: Alpine-CSP-Build aktiv (ALPINE_CSP_BUILD) → 'unsafe-eval' entfällt.
        config(['security.csp_script_nonce' => true]);
        config(['security.csp_alpine_csp_build' => true]);

        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'nonce-", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function test_unsafe_eval_removed_in_compat_mode_with_alpine_csp_build_flag(): void {
        // Auch ohne Nonce-Flag: läuft der CSP-Build, braucht niemand mehr eval.
        config(['security.csp_script_nonce' => false]);
        config(['security.csp_alpine_csp_build' => true]);

        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    // ---- Nonce-Abdeckungs-Gate (Stufe 1, B4) ---------------------------

    public function test_guest_pages_render_all_scripts_with_nonce(): void {
        config(['security.csp_script_nonce' => true]);

        $this->assertPageFullyNonced($this->get('/login'));
        $this->assertPageFullyNonced($this->get('/'));
    }

    public function test_app_pages_render_all_scripts_with_nonce(): void {
        config(['security.csp_script_nonce' => true]);

        $this->setUpOrganization();
        $admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
        // Ein Datensatz, damit die Kundenliste (inkl. Zeilen-Aktionen) rendert.
        \App\Models\Customer::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Dashboard (Widgets), Listen-Seite (Tabelle + Dialog-Trigger) und
        // Schichtplan (statisch gerenderte <x-modal>-Dialoge + Inline-Scripts).
        $this->assertPageFullyNonced($this->actingAs($admin)->get(route('dashboard')));
        $this->assertPageFullyNonced($this->actingAs($admin)->get(route('customers.index')));
        $this->assertPageFullyNonced($this->actingAs($admin)->get(route('schedule.index')));
    }

    /**
     * Regex-Sweep über den HTML-Response: jedes <script>-Tag muss das
     * Nonce-Attribut tragen; Inline-Event-Handler-Attribute sind verboten.
     */
    private function assertPageFullyNonced(TestResponse $response): void {
        $response->assertOk();
        $html = $response->getContent();

        preg_match_all('/<script\b[^>]*>/i', $html, $matches);
        $this->assertNotEmpty($matches[0], 'Seite ohne einziges <script>-Tag — Testaufbau prüfen.');

        foreach ($matches[0] as $tag) {
            if (preg_match('/type\s*=\s*"application\/(?:json|ld\+json)"/i', $tag) === 1) {
                continue; // Daten-Block, kein Skript — CSP wertet ihn nicht aus.
            }
            $this->assertMatchesRegularExpression(
                '/\bnonce\s*=\s*"[^"]+"/i',
                $tag,
                "Script-Tag ohne Nonce gefunden (unter CSP Stufe 1 blockiert): {$tag}",
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/\son(?:click|change|submit|input|keyup|keydown|keypress|load|error|focus|blur|dragstart|dragover|dragend|drop|dblclick|mouseover|mouseout|mousedown|mouseup|paste|wheel|contextmenu)\s*=\s*["\']/i',
            $html,
            'Inline-Event-Handler-Attribut gefunden — unter Nonce-CSP blockiert; auf data-Attribut + Delegation (resources/js/inline-actions.js) umstellen.',
        );
    }
}
