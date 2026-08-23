<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavActiveItemTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Navigation;

use App\Services\Navigation\NavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Genau ein Sidebar-Eintrag ist aktiv (Navigation).
 *
 * Aktiv-Muster überdecken einander zwangsläufig — `quotes.*` (Belegfluss)
 * trifft auch `quotes.follow-ups.index`. Ohne Rangfolge leuchten zwei Punkte,
 * und keiner sagt, wo man ist.
 */
class NavActiveItemTest extends TestCase {
    use RefreshDatabase;

    /** @return list<array<string, mixed>> */
    private function sections(): array {
        return [[
            'key' => 'sales',
            'label' => 'Vertrieb',
            'groups' => [[
                'key' => 'sales-billing',
                'label' => 'Abrechnung',
                'items' => [
                    ['route' => 'billing.feed', 'label' => 'Belegfluss', 'matches' => ['billing.feed', 'invoices.*', 'quotes.*']],
                    ['route' => 'quotes.follow-ups.index', 'label' => 'Angebote nachfassen', 'matches' => ['quotes.follow-ups.*']],
                ],
            ]],
        ]];
    }

    /** @param list<array<string, mixed>> $sections */
    private function activeLabels(array $sections): array {
        $labels = [];
        foreach ($sections as $section) {
            foreach ($section['groups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    if ($item['active'] ?? false) {
                        $labels[] = $item['label'];
                    }
                }
            }
        }

        return $labels;
    }

    private function registry(): NavigationRegistry {
        return app(NavigationRegistry::class);
    }

    /** Das spezifischere Muster gewinnt — nicht beide. */
    public function test_only_the_most_specific_item_is_active(): void {
        $this->get(route('quotes.follow-ups.index'));

        $marked = $this->registry()->markActiveItem($this->sections());

        $this->assertSame(['Angebote nachfassen'], $this->activeLabels($marked));
    }

    /** Auf der Sammelseite gewinnt der Sammeleintrag. */
    public function test_the_broad_entry_wins_on_its_own_page(): void {
        $this->get(route('billing.feed'));

        $marked = $this->registry()->markActiveItem($this->sections());

        $this->assertSame(['Belegfluss'], $this->activeLabels($marked));
    }

    /** Passt nichts, leuchtet nichts. */
    public function test_nothing_is_active_on_an_unrelated_page(): void {
        $this->get(route('customers.index'));

        $marked = $this->registry()->markActiveItem($this->sections());

        $this->assertSame([], $this->activeLabels($marked));
    }

    /**
     * Über die echte Registry: keine Seite darf zwei Einträge markieren.
     *
     * Geprüft an den bekannten Überdeckungen — dort standen bis 2026-08-23
     * jeweils zwei Punkte gleichzeitig hell.
     */
    public function test_no_real_page_lights_up_two_entries(): void {
        $user = \App\Models\User::factory()->admin()->create();
        $this->actingAs($user);

        foreach ([
            'quotes.follow-ups.index',
            'billing.feed',
            'tenders.cockpit',
            'inventory.scan',
            'inventory.lots',
            'finance.reconciliation.index',
        ] as $routeName) {
            if (! \Illuminate\Support\Facades\Route::has($routeName)) {
                continue;
            }

            $this->get(route($routeName));

            $sections = $this->registry()->build(false, 'home')['sidebarSections'];
            $active = 0;
            foreach ($sections as $section) {
                foreach ($section['items'] ?? [] as $item) {
                    $active += ($item['active'] ?? false) ? 1 : 0;
                }
                foreach ($section['groups'] ?? [] as $group) {
                    foreach ($group['items'] ?? [] as $item) {
                        $active += ($item['active'] ?? false) ? 1 : 0;
                    }
                }
            }

            $this->assertLessThanOrEqual(1, $active, $routeName . ' markiert ' . $active . ' Einträge');
        }
    }
}
