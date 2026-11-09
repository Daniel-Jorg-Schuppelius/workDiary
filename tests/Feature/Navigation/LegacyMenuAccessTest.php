<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyMenuAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Models\Platform\User;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Die Legacy-Verwaltungspunkte („Mitarbeiter“, Support-Portal) folgen der
 * Seite: Legacy-Admin oder Plattformbetrieb — ein Org-Admin sah sie und
 * landete auf 403 (MVP-1106).
 */
class LegacyMenuAccessTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return list<string> */
    private function manageRoutes(User $user): array {
        $this->actingAs($user);

        return array_column(app(NavigationRegistry::class)->build(true, 'duties.index')['manageNavItems'], 'route');
    }

    /** @return list<string> */
    private function mainRoutes(User $user): array {
        $this->actingAs($user);

        return array_column(app(NavigationRegistry::class)->build(true, 'duties.index')['mainNavItems'], 'route');
    }

    public function test_org_admin_does_not_see_the_legacy_staff_page_he_cannot_open(): void {
        $orgAdmin = $this->orgAdmin();

        $this->assertNotContains('legacy.users.index', $this->manageRoutes($orgAdmin));
    }

    public function test_platform_admin_keeps_the_legacy_staff_page(): void {
        $platform = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);

        $this->assertContains('legacy.users.index', $this->manageRoutes($platform));
    }

    /** Support-Portal: wie die übrige Altsystem-Verwaltung nur für Bürokonten und Plattformbetrieb. */
    public function test_support_portal_entries_follow_the_legacy_admin_boundary(): void {
        $legacyAdmin = User::factory()->create(['organization_id' => $this->organization->id, 'legacy_user_id' => 2]);
        $this->assertContains('legacy.support.orders.index', $this->mainRoutes($legacyAdmin));
        $this->assertContains('legacy.support.customers.index', $this->manageRoutes($legacyAdmin));

        $platform = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->assertContains('legacy.support.orders.index', $this->mainRoutes($platform));

        $orgAdmin = $this->orgAdmin();
        $this->assertNotContains('legacy.support.orders.index', $this->mainRoutes($orgAdmin));
        $this->assertNotContains('legacy.support.customers.index', $this->manageRoutes($orgAdmin));

        $mechanic = User::factory()->create(['organization_id' => $this->organization->id, 'legacy_user_id' => 7]);
        $this->assertNotContains('legacy.support.orders.index', $this->mainRoutes($mechanic));
    }
}
