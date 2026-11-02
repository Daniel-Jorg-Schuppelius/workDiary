<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavMenuCacheTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Models\Platform\User;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Leistungsanalyse 2026-11-02, perf-13: Das Menü wird je Nutzer gecacht; die
 * aktive Markierung bleibt je Seite, Rechte-, Ausblende- und Plugin-Änderungen
 * wechseln den Schlüssel sofort.
 */
final class NavMenuCacheTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_menu_is_cached_as_plain_arrays_and_reused(): void {
        $registry = app(NavigationRegistry::class);
        $key = $registry->menuCacheKey($this->admin, false, 'dashboard', null);

        $first = $registry->build(false, 'dashboard');
        $this->assertTrue(Cache::has($key));
        $this->assertNotEmpty($first['sidebarSections']);

        $queries = 0;
        \Illuminate\Support\Facades\DB::listen(static function () use (&$queries): void {
            $queries++;
        });
        $second = $registry->build(false, 'dashboard');
        $this->assertSame(0, $queries, 'aus dem Cache ohne Datenbank');
        $this->assertSame($first['mainNavItems'], $second['mainNavItems']);
        $this->assertSame($first['sidebarSections'], $second['sidebarSections']);
    }

    public function test_active_item_is_marked_per_request_not_cached(): void {
        $registry = app(NavigationRegistry::class);
        $this->get(route('customers.index'));
        $onCustomers = $registry->build(false, 'dashboard');
        $this->get(route('dashboard'));
        $onDashboard = $registry->build(false, 'dashboard');

        $this->assertNotSame(
            json_encode($onCustomers['sidebarSections']),
            json_encode($onDashboard['sidebarSections']),
            'aktive Markierung folgt der Seite, obwohl das Menü aus dem Cache kommt',
        );
    }

    public function test_hiding_an_item_and_changing_permissions_change_the_key(): void {
        $registry = app(NavigationRegistry::class);
        $before = $registry->menuCacheKey($this->admin, false, 'dashboard', null);

        NavigationRegistry::bumpUserStamp($this->admin);
        $afterHide = $registry->menuCacheKey($this->admin, false, 'dashboard', null);
        $this->assertNotSame($before, $afterHide);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $afterPermissions = $registry->menuCacheKey($this->admin, false, 'dashboard', null);
        $this->assertNotSame($afterHide, $afterPermissions);

        NavigationRegistry::bumpGlobalStamp();
        $this->assertNotSame($afterPermissions, $registry->menuCacheKey($this->admin, false, 'dashboard', null));
    }

    public function test_customizing_the_navigation_takes_effect_immediately(): void {
        $registry = app(NavigationRegistry::class);
        $registry->build(false, 'dashboard');
        $before = $registry->menuCacheKey($this->admin, false, 'dashboard', null);

        $this->post(route('me.navigation.customize.save'), ['visible' => []])->assertRedirect();

        $this->assertNotSame($before, $registry->menuCacheKey($this->admin, false, 'dashboard', null), 'Stempel des Nutzers ist neu');
    }
}
