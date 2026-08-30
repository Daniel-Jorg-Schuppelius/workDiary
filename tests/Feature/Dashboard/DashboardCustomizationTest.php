<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardCustomizationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\Dashboard\WidgetWidth;
use App\Models\{User, UserDashboardWidget};
use App\Services\Dashboard\DashboardLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DashboardCustomizationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_customize_page_lists_available_widgets(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->get(route('dashboard.customize'));

        $response->assertOk();
        $response->assertSee(__('Lesezeichen'));
    }

    public function test_save_persists_order_and_visibility(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'widgets' => [
                ['key' => 'bookmarks', 'hidden' => '1'],
            ],
        ]);

        $response->assertRedirect(route('dashboard.customize'));

        $rows = UserDashboardWidget::where('user_id', $user->id)->orderBy('sort_order')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('bookmarks', $rows[0]->widget_key);
        $this->assertTrue($rows[0]->hidden);
    }

    public function test_save_ignores_unknown_widget_keys(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'widgets' => [
                ['key' => 'does-not-exist', 'hidden' => '0'],
                ['key' => 'bookmarks', 'hidden' => '0'],
            ],
        ])->assertRedirect();

        $this->assertSame(1, UserDashboardWidget::where('user_id', $user->id)->count());
        $this->assertSame('bookmarks', UserDashboardWidget::where('user_id', $user->id)->first()->widget_key);
    }

    public function test_save_persists_width_per_widget(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'widgets' => [
                ['key' => 'bookmarks', 'hidden' => '0', 'width' => 'full'],
                ['key' => 'attendance-clock', 'hidden' => '0'],
            ],
        ])->assertRedirect();

        $rows = UserDashboardWidget::where('user_id', $user->id)->get()->keyBy('widget_key');
        $this->assertSame(WidgetWidth::Full, $rows['bookmarks']->width);
        // Ohne Angabe bleibt die Breite NULL — die Vorgabe der Kachel greift.
        $this->assertNull($rows['attendance-clock']->width);

        $resolved = app(DashboardLayoutService::class)->resolveFor($user)->keyBy(fn ($i) => $i->key());
        $this->assertSame(WidgetWidth::Full, $resolved['bookmarks']->width);
        $this->assertSame(WidgetWidth::Half, $resolved['attendance-clock']->width);
    }

    public function test_organization_default_applies_to_users_without_own_layout(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('dashboard.customize.save'), [
            'scope' => 'organization',
            'widgets' => [
                ['key' => 'bookmarks', 'hidden' => '1', 'width' => 'full'],
                ['key' => 'attendance-clock', 'hidden' => '0'],
            ],
        ])->assertRedirect();

        $member->refresh();
        $this->assertSame(0, $member->dashboardWidgets()->count());

        $resolved = app(DashboardLayoutService::class)->resolveFor($member)->keyBy(fn ($i) => $i->key());
        $this->assertTrue($resolved['bookmarks']->hidden);
        $this->assertSame('organization', $resolved['bookmarks']->source);
        $this->assertSame(WidgetWidth::Full, $resolved['bookmarks']->width);
    }

    public function test_organization_default_is_refused_without_permission(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'scope' => 'organization',
            'widgets' => [
                ['key' => 'bookmarks', 'hidden' => '1'],
            ],
        ])->assertForbidden();

        $this->assertFalse(app(DashboardLayoutService::class)->hasOrgDefault($this->organization->fresh()));
    }

    public function test_reset_drops_own_layout_and_falls_back_to_default(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'widgets' => [['key' => 'bookmarks', 'hidden' => '1']],
        ])->assertRedirect();
        $this->assertSame(1, $user->dashboardWidgets()->count());

        $this->actingAs($user)->post(route('dashboard.customize.reset'))->assertRedirect();

        $this->assertSame(0, $user->dashboardWidgets()->count());
        $resolved = app(DashboardLayoutService::class)->resolveFor($user)->keyBy(fn ($i) => $i->key());
        $this->assertFalse($resolved['bookmarks']->hidden);
        $this->assertSame('default', $resolved['bookmarks']->source);
    }

    public function test_hidden_widget_is_not_rendered_on_the_dashboard(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        // Auf einen Text prüfen, den nur die Kachel trägt — „Lesezeichen"
        // allein steht auch im Kopfmenü des Layouts.
        $marker = __('Noch keine Lesezeichen gespeichert.');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee($marker);

        UserDashboardWidget::create([
            'user_id' => $user->id,
            'widget_key' => 'bookmarks',
            'sort_order' => 0,
            'hidden' => true,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee($marker);
    }

    public function test_tabs_group_tiles_on_the_dashboard(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'tabs' => [
                ['key' => 'tab-1', 'label' => 'Alltag'],
                ['key' => 'tab-2', 'label' => 'Zahlen'],
            ],
            'widgets' => [
                ['key' => 'bookmarks', 'hidden' => '0', 'tab' => 'tab-2'],
                ['key' => 'attendance-clock', 'hidden' => '0', 'tab' => 'tab-1'],
            ],
        ])->assertRedirect();

        $layout = app(DashboardLayoutService::class);
        $this->assertSame(
            [
                ['key' => 'tab-1', 'label' => 'Alltag', 'icon' => null],
                ['key' => 'tab-2', 'label' => 'Zahlen', 'icon' => null],
            ],
            $layout->tabsFor($user->refresh()),
        );

        $resolved = $layout->resolveFor($user)->keyBy(fn ($i) => $i->key());
        $this->assertSame('tab-2', $resolved['bookmarks']->tabKey);
        $this->assertSame('tab-1', $resolved['attendance-clock']->tabKey);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Alltag')
            ->assertSee('Zahlen');
    }

    public function test_tile_without_a_section_stays_visible_above_the_bar(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        // Zuordnung auf einen Bereich, den die Bereichsliste gar nicht kennt.
        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'tabs' => [['key' => 'tab-1', 'label' => 'Alltag']],
            'widgets' => [
                ['key' => 'bookmarks', 'hidden' => '0', 'tab' => 'tab-9'],
            ],
        ])->assertRedirect();

        $resolved = app(DashboardLayoutService::class)->resolveFor($user)->keyBy(fn ($i) => $i->key());
        $this->assertNull($resolved['bookmarks']->tabKey);

        // Ohne gültigen Bereich steht die Kachel über der Leiste — sie darf
        // weder verschwinden noch in einem einzelnen Bereich landen.
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $response->assertSee(__('Noch keine Lesezeichen gespeichert.'));

        $html = $response->getContent() ?: '';
        $this->assertLessThan(
            strpos($html, 'role="tablist"') ?: PHP_INT_MAX,
            strpos($html, __('Noch keine Lesezeichen gespeichert.')) ?: 0,
            'Kachel ohne Bereich muss vor der Bereichsleiste stehen',
        );
    }

    public function test_section_symbol_is_stored_and_rendered(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'tabs' => [['key' => 'tab-1', 'label' => 'Alltag', 'icon' => 'schedule']],
            'widgets' => [['key' => 'bookmarks', 'hidden' => '0', 'tab' => 'tab-1']],
        ])->assertRedirect();

        $tabs = app(DashboardLayoutService::class)->tabsFor($user->refresh());
        $this->assertSame('schedule', $tabs[0]['icon']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('data-icon="schedule"', false);
    }

    public function test_section_symbol_is_rejected_when_it_is_not_a_symbol_name(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'tabs' => [['key' => 'tab-1', 'label' => 'Alltag', 'icon' => '"><script>alert(1)</script>']],
            'widgets' => [['key' => 'bookmarks', 'hidden' => '0']],
        ])->assertSessionHasErrors('tabs.0.icon');
    }

    public function test_classic_preset_rebuilds_the_pre_rebuild_dashboard(): void {
        $this->setUpOrganization();
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.preset'), ['preset' => 'classic'])
            ->assertRedirect(route('dashboard.customize'));

        $layout = app(DashboardLayoutService::class);
        $tabs = $layout->tabsFor($user->refresh());
        $this->assertSame(['tab-1', 'tab-2', 'tab-3', 'tab-4'], array_column($tabs, 'key'));
        $this->assertSame(__('Überblick'), $tabs[0]['label']);
        $this->assertSame('dashboard', $tabs[0]['icon']);

        $resolved = $layout->resolveFor($user)->keyBy(fn ($i) => $i->key());

        // Über der Leiste: Lesezeichen und KPI-Zeile — wie vor dem Umbau.
        $this->assertNull($resolved['personal-kpis']->tabKey);
        $this->assertFalse($resolved['personal-kpis']->hidden);
        $this->assertNull($resolved['bookmarks']->tabKey);

        // In den Bereichen: die früheren Registerkarten-Inhalte.
        $this->assertSame('tab-1', $resolved['today-shifts']->tabKey);
        $this->assertSame('tab-2', $resolved['open-issues']->tabKey);
        $this->assertSame('tab-3', $resolved['recent-comments']->tabKey);
        $this->assertSame('tab-4', $resolved['finance']->tabKey);

        // Alles, was das Preset nicht nennt, bleibt aus.
        $this->assertTrue($resolved['open-times']->hidden);

        // Der Sicherungsstand gilt der Installation: für einen Org-Admin steht
        // die Kachel gar nicht erst zur Wahl (Sicherheitsscan 2026-08-23, S-02).
        $this->assertArrayNotHasKey('backup-status', $resolved->all());

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee(__('Meine offenen Einträge'))
            ->assertSee(__('Überblick'))
            ->assertSee(__('Finanzen & Reisen'));
    }

    public function test_unknown_preset_is_rejected(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.preset'), ['preset' => 'does-not-exist'])
            ->assertNotFound();

        $this->assertSame(0, $user->dashboardWidgets()->count());
    }

    public function test_tabs_are_rejected_when_the_key_is_not_slug_shaped(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'tabs' => [['key' => 'tab 1"><script>', 'label' => 'Böse']],
            'widgets' => [['key' => 'bookmarks', 'hidden' => '0']],
        ])->assertSessionHasErrors('tabs.0.key');

        $this->assertSame([], app(DashboardLayoutService::class)->tabsFor($user->refresh()));
    }

    public function test_reset_also_drops_own_sections(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'tabs' => [['key' => 'tab-1', 'label' => 'Alltag']],
            'widgets' => [['key' => 'bookmarks', 'hidden' => '0', 'tab' => 'tab-1']],
        ])->assertRedirect();
        $this->assertNotSame([], app(DashboardLayoutService::class)->tabsFor($user->refresh()));

        $this->actingAs($user)->post(route('dashboard.customize.reset'))->assertRedirect();

        $this->assertSame([], app(DashboardLayoutService::class)->tabsFor($user->refresh()));
    }

    public function test_organization_sections_apply_to_users_without_own_sections(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('dashboard.customize.save'), [
            'scope' => 'organization',
            'tabs' => [['key' => 'tab-1', 'label' => 'Betrieb']],
            'widgets' => [['key' => 'bookmarks', 'hidden' => '0', 'tab' => 'tab-1']],
        ])->assertRedirect();

        $layout = app(DashboardLayoutService::class);
        $this->assertSame([['key' => 'tab-1', 'label' => 'Betrieb', 'icon' => null]], $layout->tabsFor($member->refresh()));
        $this->assertSame('tab-1', $layout->resolveFor($member)->keyBy(fn ($i) => $i->key())['bookmarks']->tabKey);
    }

    public function test_guest_redirected_to_login(): void {
        $this->get(route('dashboard.customize'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_widgets_for_empty_user(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
