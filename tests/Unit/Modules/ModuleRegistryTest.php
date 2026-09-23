<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Modules;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\ModuleRegistry;
use Tests\TestCase;

/** Modulregister (MVP-861): Discovery, Indizes, Routen-Zuordnung und Cache. */
class ModuleRegistryTest extends TestCase {
    private function registry(): ModuleRegistry {
        return $this->app->make(ModuleRegistry::class);
    }

    public function test_discovers_manifests_with_unique_codes_and_owners(): void {
        $all = $this->registry()->all();

        $this->assertGreaterThan(60, count($all));
        $this->assertSame(array_keys($all), array_map(static fn ($m): string => $m->code(), array_values($all)));
        $this->assertSame(ModuleKind::Feature, $all['club']->kind());
        $this->assertSame('module.club', $all['club']->licenseCode());
        $this->assertNull($all['customer']->licenseCode());
        $this->assertSame(ModuleKind::Platform, $all['audit']->kind());
    }

    public function test_indexes_tables_folders_permission_groups_and_plugins(): void {
        $registry = $this->registry();

        $this->assertSame('club', $registry->byTable('club_members')?->code());
        $this->assertSame('audit', $registry->byTable('audit_logs')?->code());
        $this->assertSame('framework', $registry->byTable('migrations')?->code());
        $this->assertNull($registry->byTable('gibt_es_nicht'));
        $this->assertSame('finance', $registry->byFolder('Accounting')?->code());
        $this->assertSame('club', $registry->byPermissionGroup(PermissionGroup::Club)?->code());
        $this->assertSame('finance', $registry->byPlugin('lexoffice')?->code());
    }

    public function test_shared_license_has_exactly_one_owner(): void {
        $registry = $this->registry();

        $this->assertSame('inventory', $registry->byLicenseCode('module.lager')?->code());
        $codes = array_map(static fn ($m): string => $m->code(), $registry->allByLicenseCode('module.lager'));
        $this->assertSame('inventory', $codes[0]);
        $this->assertContains('manufacturing', $codes);
        $this->assertContains('procurement', $codes);
        $this->assertArrayHasKey('module.lager', $registry->labels());
        $this->assertArrayNotHasKey('manufacturing', $registry->labels());
    }

    public function test_route_map_prefers_specific_patterns_and_understands_api_variants(): void {
        $registry = $this->registry();

        $this->assertSame('module.kanban', $registry->moduleForRoute('kanban.index'));
        $this->assertSame('module.kanban', $registry->moduleForRoute('api.kanban.index'));
        $this->assertSame('module.reselling', $registry->moduleForRoute('finance.resale.index'));
        $this->assertSame('module.finance', $registry->moduleForRoute('finance.transfers.index'));
        $this->assertNull($registry->moduleForRoute('diary.index'));
        $this->assertNull($registry->moduleForRoute(null));

        $patterns = array_keys($registry->routeMap());
        $this->assertLessThan(array_search('finance.*', $patterns, true), array_search('finance.resale.*', $patterns, true));
    }

    public function test_navigation_maps_and_license_requirements(): void {
        $registry = $this->registry();
        $maps = $registry->navigationMaps();

        $this->assertSame('module.planung', $maps['sections']['plan'] ?? null);
        $this->assertSame('module.kanban', $maps['items']['kanban.index'] ?? null);
        $this->assertSame('module.auswertungen_team', $maps['groups']['reports-team'] ?? null);
        $this->assertSame(['module.helpdesk'], $registry->licenseRequirements()['module.service_desk'] ?? null);
        $this->assertSame(['module.lager'], $registry->licenseRequirements()['module.b2b_katalog'] ?? null);
    }

    public function test_cache_file_round_trip(): void {
        $cache = sys_get_temp_dir() . '/workdiary-modules-' . uniqid() . '.php';
        $registry = new ModuleRegistry(app_path('Modules/Manifests'), $cache);
        $this->assertFalse($registry->isCached());

        $registry->cache();
        $this->assertTrue($registry->isCached());
        $this->assertSame(count($this->registry()->all()), count($registry->all()));

        $registry->clearCache();
        $this->assertFalse($registry->isCached());
    }
}
