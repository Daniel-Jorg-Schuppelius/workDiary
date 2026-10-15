<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompactFocusRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Models\Platform\{Organization, User};
use App\Services\Licensing\{FeatureFlagResolver, ModuleScopeService};
use App\Services\Navigation\{NavFocusService, NavigationRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kompakte Arbeitsbereiche (Feature 165, MVP-1190/1191): jedes Ziel ist ein
 * echter, wählbarer Registry-Eintrag; höchstens sechs Ziele und drei
 * Schnellaktionen; ein leerer Bereich leert die Sidebar nicht.
 */
class CompactFocusRegistryTest extends TestCase {
    use RefreshDatabase;

    /** @return list<string> */
    private function compactKeys(): array {
        return array_values(array_filter(array_keys(app(NavFocusService::class)->all()), static fn (string $k): bool => str_starts_with($k, 'compact_')));
    }

    public function test_every_compact_target_is_a_selectable_registry_key_for_an_enterprise_admin(): void {
        // Das Anwesenheitsboard (Team & Planung) ist ein Org-Opt-in (MVP-524).
        $org = Organization::factory()->enterprise()->create(['settings' => ['presence' => ['board_enabled' => true]]]);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $this->be($admin);
        app()->instance('currentOrganization', $org);
        app(FeatureFlagResolver::class)->flush();

        $registry = app(NavigationRegistry::class);
        $focus = app(NavFocusService::class);
        $selectable = $registry->selectableKeys();
        $createRoutes = $registry->selectableCreateRoutes();

        $this->assertNotEmpty($this->compactKeys());
        foreach ($this->compactKeys() as $key) {
            $keep = $focus->keepKeys($key) ?? [];
            $create = $focus->createKeep($key) ?? [];
            $this->assertNotEmpty($keep, $key);
            $this->assertLessThanOrEqual(6, count($keep), $key);
            $this->assertLessThanOrEqual(3, count($create), $key);
            foreach ($keep as $ref) {
                $this->assertContains($ref, $selectable, "{$key}: {$ref} ist kein wählbarer Registry-Schlüssel.");
            }
            foreach ($create as $ref) {
                $this->assertContains(substr($ref, strlen(NavigationRegistry::KEY_CREATE)), $createRoutes, "{$key}: {$ref} ist keine Schnellaktion der Registry.");
            }
        }
    }

    public function test_compact_focus_limits_sidebar_and_quick_actions(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $this->be($admin);
        app()->instance('currentOrganization', $org);
        app(FeatureFlagResolver::class)->flush();

        $nav = app(NavigationRegistry::class)->build(false, 'duties.index', 'compact_orders');

        $items = 0;
        foreach ($nav['sidebarSections'] as $section) {
            $items += count((array) ($section['items'] ?? []));
            foreach ((array) ($section['groups'] ?? []) as $group) {
                $items += count((array) ($group['items'] ?? []));
            }
        }
        $this->assertGreaterThan(0, $items);
        $this->assertLessThanOrEqual(6, $items);

        $createRoutes = [];
        foreach ($nav['createGroups'] as $group) {
            foreach ((array) $group['items'] as $item) {
                $createRoutes[] = $item['route'];
            }
        }
        $this->assertEqualsCanonicalizing(['diary.create', 'customers.create', 'projects.create'], $createRoutes);
        $this->assertSame([], $nav['emptyFocuses']);
    }

    public function test_focus_without_any_reachable_target_falls_back_to_full_sidebar(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        // Persönliche Ausblendung nimmt „Angebote & Rechnungen" alle Ziele (Vertrieb und Auswertungen).
        $admin->setPreference(NavigationRegistry::PREFERENCE_HIDDEN, ['section:sales', 'section:reports']);
        $this->be($admin->fresh());
        app()->instance('currentOrganization', $org);
        app(FeatureFlagResolver::class)->flush();

        $registry = app(NavigationRegistry::class);
        $full = $registry->build(false, 'duties.index', null);
        $billing = $registry->build(false, 'duties.index', 'compact_billing');

        $this->assertContains('compact_billing', $billing['emptyFocuses']);
        $this->assertNotContains('compact_day', $billing['emptyFocuses']);
        $this->assertSame(count($full['sidebarSections']), count($billing['sidebarSections']));
        $this->assertSame(count($full['createGroups']), count($billing['createGroups']));
    }

    public function test_start_bundle_keeps_only_chosen_module_targets(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $this->be($admin);
        app()->instance('currentOrganization', $org);
        app(ModuleScopeService::class)->applyPreset($org, 'start', $admin);
        app(FeatureFlagResolver::class)->flush();

        $nav = app(NavigationRegistry::class)->build(false, 'duties.index', 'compact_team');

        // Team & Planung ist ohne Planungsmodul leer → volle Sidebar, Bereich nicht angeboten.
        $this->assertContains('compact_team', $nav['emptyFocuses']);
        $routes = [];
        foreach ($nav['sidebarSections'] as $section) {
            foreach ((array) ($section['groups'] ?? []) as $group) {
                foreach ((array) ($group['items'] ?? []) as $item) {
                    $routes[] = $item['route'];
                }
            }
            foreach ((array) ($section['items'] ?? []) as $item) {
                $routes[] = $item['route'];
            }
        }
        $this->assertContains('customers.index', $routes);
        $this->assertNotContains('duty-plans.index', $routes);
    }
}
