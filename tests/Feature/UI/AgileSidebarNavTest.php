<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSidebarNavTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sidebar-Eintrag für agile.* (Feature 064 Restpunkt, B3/MVP-344):
 * Einstieg über die org-weite Management-Übersicht (P10), sichtbar nur
 * mit `agile.report.view` — dasselbe Gate wie die Route selbst.
 */
class AgileSidebarNavTest extends TestCase {
    use RefreshDatabase;

    public function test_sidebar_links_agile_overview_for_report_viewers(): void {
        $lead = User::factory()->teamleitung()->create();

        $this->actingAs($lead)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('agile.reports.overview'));
    }

    public function test_sidebar_hides_agile_overview_without_permission(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('agile.reports.overview'));
    }
}
