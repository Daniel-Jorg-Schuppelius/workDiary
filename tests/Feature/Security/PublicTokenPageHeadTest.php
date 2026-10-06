<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicTokenPageHeadTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Öffentliche Token-Seiten teilen einen Dokumentkopf (`<x-public-page>`,
 * Konsolidierungs-Audit 2026-10, k4-06). Die vier Signaturseiten trugen
 * `lang="de"` fest und kein `noindex`.
 */
final class PublicTokenPageHeadTest extends TestCase {
    use RefreshDatabase;

    public function test_signature_page_carries_request_language_and_noindex(): void {
        $response = $this->get(route('timesheets.public-thanks'))->assertOk();

        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $response->assertSee('<html lang="' . str_replace('_', '-', app()->getLocale()) . '"', false);
        $response->assertDontSee('name="referrer"', false);
    }

    public function test_language_follows_the_locale_instead_of_a_fixed_value(): void {
        app()->setLocale('fr');

        $html = view('public.timesheet-thanks')->render();

        $this->assertStringContainsString('<html lang="fr"', $html);
    }
}
