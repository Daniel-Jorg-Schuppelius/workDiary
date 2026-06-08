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

use Tests\TestCase;

class CspNonceTest extends TestCase {
    public function test_csp_uses_unsafe_inline_by_default(): void {
        config(['security.csp_script_nonce' => false]);

        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'nonce-", $csp);
    }

    public function test_csp_uses_nonce_when_enabled_and_no_unsafe_inline_for_scripts(): void {
        config(['security.csp_script_nonce' => true]);

        $response = $this->get('/login');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        // script-src nutzt jetzt ein Nonce statt 'unsafe-inline'.
        $this->assertStringContainsString("'nonce-", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        // Stufe 1: 'unsafe-eval' bleibt (Standard-Alpine-Build); entfällt erst mit @alpinejs/csp.

        // Das gerenderte Inline-Script trägt exakt diesen Nonce (sonst würde es blockiert).
        preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m[1] ?? null);
        $response->assertSee('nonce="' . $m[1] . '"', false);
    }
}
