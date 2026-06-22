<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleCatalogConsistencyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Services\Licensing\ModuleCatalog;
use Tests\TestCase;

/**
 * MVP-052 Akzeptanz 9: zentrale Abdeckungsprüfung des Modulkatalogs. Erkennt
 * Modulrouten ohne Katalogzuordnung sowie inkonsistente Modulcodes (Label,
 * Beschreibung, Tier-Zuordnung müssen zusammenpassen).
 */
class ModuleCatalogConsistencyTest extends TestCase {
    private ModuleCatalog $catalog;

    protected function setUp(): void {
        parent::setUp();
        $this->catalog = app(ModuleCatalog::class);
    }

    public function test_every_gated_route_maps_to_a_known_module(): void {
        $enterprise = (array) config('plans.tiers.enterprise', []);

        foreach ($this->catalog->routeMap() as $pattern => $module) {
            $this->assertStringStartsWith('module.', (string) $module, "Route-Muster {$pattern} zeigt auf keinen Modulcode.");
            $this->assertTrue(
                $this->catalog->has($module),
                "Route-Muster {$pattern} verweist auf Modul {$module} ohne Katalog-Label.",
            );
            $this->assertContains(
                $module,
                $enterprise,
                "Modul {$module} (Route {$pattern}) ist in keinem Tier lizenzierbar.",
            );
        }
    }

    public function test_every_catalog_module_has_label_and_description(): void {
        foreach ($this->catalog->codes() as $code) {
            $this->assertNotSame('', trim($this->catalog->label($code)), "Modul {$code} ohne Label.");
            $this->assertNotSame('', trim($this->catalog->description($code)), "Modul {$code} ohne Beschreibung.");
        }
    }

    public function test_enterprise_tier_modules_are_all_catalogued(): void {
        foreach ((array) config('plans.tiers.enterprise', []) as $code) {
            $code = (string) $code;
            if (! str_starts_with($code, 'module.')) {
                continue; // technische Feature-Flags (z. B. protocols.signed) sind keine Module.
            }
            $this->assertTrue($this->catalog->has($code), "Tier-Modul {$code} fehlt im Katalog (Label/Beschreibung).");
        }
    }

    public function test_pro_tier_is_subset_of_enterprise(): void {
        $enterprise = (array) config('plans.tiers.enterprise', []);
        foreach ((array) config('plans.tiers.pro', []) as $code) {
            $this->assertContains((string) $code, $enterprise, "Pro-Modul {$code} fehlt im Enterprise-Superset.");
        }
    }
}
