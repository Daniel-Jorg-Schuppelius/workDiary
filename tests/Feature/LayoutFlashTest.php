<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LayoutFlashTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * UI-Fuzz 2026-09-21: with('status') rendern jetzt das Layout statt einzelner
 * Views — vorher ging die Meldung auf allen Seiten ohne eigenen Block verloren
 * (Journal, Buchungsregeln, …), das Kundenportal zeigte sie doppelt.
 */
class LayoutFlashTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_app_layout_renders_status_flash_exactly_once(): void {
        $html = (string) $this->actingAs($this->orgAdmin())
            ->withSession(['status' => 'Statusmeldung 4711'])
            ->get(route('customers.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Statusmeldung 4711'));
    }
}
