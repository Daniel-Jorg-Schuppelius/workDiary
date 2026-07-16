<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavFocusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Navigation;

use App\Models\{Organization, User};
use App\Services\Navigation\{NavFocusService, NavigationRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arbeitsbereiche — schaltbare Fokus-Ansichten (Feature 082, MVP-377/378).
 *
 * Sichert die reine Filterlogik (applyFocus), die Auflösung des aktiven Fokus
 * und das Umschalten (Session + Preference-Persistenz) ab. Der Fokus ist rein
 * kosmetisch (D13): er darf nie mehr zeigen, als bereits sichtbar ist, und
 * `null`/'all' lässt die Navigation unverändert (Golden-Snapshot-Kompatibilität).
 */
class NavFocusTest extends TestCase {
    use RefreshDatabase;

    /** @return list<array<string, mixed>> */
    private function sampleSections(): array {
        return [
            ['key' => 'work', 'label' => 'Work', 'groups' => [
                ['key' => 'work-capture', 'label' => 'Erfassung', 'items' => [['route' => 'duties.index']]],
                ['key' => 'work-knowledge', 'label' => 'Wissen', 'items' => [['route' => 'documents.index']]],
            ]],
            ['key' => 'plan', 'label' => 'Plan', 'items' => [
                ['route' => 'duty-plans.index'],
                ['route' => 'shifts.index'],
            ]],
            ['key' => 'sales', 'label' => 'Sales', 'groups' => [
                ['key' => 'sales-inventory', 'label' => 'Lager', 'items' => [['route' => 'articles.index']]],
                ['key' => 'sales-billing', 'label' => 'Abrechnung', 'items' => [['route' => 'invoices.index']]],
            ]],
        ];
    }

    private function registry(): NavigationRegistry {
        return app(NavigationRegistry::class);
    }

    public function test_null_focus_returns_sidebar_unchanged(): void {
        $sections = $this->sampleSections();

        $this->assertSame($sections, $this->registry()->applyFocus($sections, null));
    }

    public function test_section_key_keeps_whole_section(): void {
        $out = $this->registry()->applyFocus($this->sampleSections(), ['section:work']);

        $this->assertCount(1, $out);
        $this->assertSame('work', $out[0]['key']);
        // Ganze Sektion inklusive aller Gruppen bleibt erhalten.
        $this->assertCount(2, $out[0]['groups']);
    }

    public function test_group_key_narrows_section_to_that_group(): void {
        $out = $this->registry()->applyFocus($this->sampleSections(), ['group:sales-inventory']);

        $this->assertCount(1, $out);
        $this->assertSame('sales', $out[0]['key']);
        $this->assertCount(1, $out[0]['groups']);
        $this->assertSame('sales-inventory', $out[0]['groups'][0]['key']);
    }

    public function test_item_key_narrows_group_to_that_item(): void {
        $out = $this->registry()->applyFocus($this->sampleSections(), ['item:invoices.index']);

        $this->assertCount(1, $out);
        $this->assertSame('sales', $out[0]['key']);
        $this->assertCount(1, $out[0]['groups']);
        $this->assertSame('sales-billing', $out[0]['groups'][0]['key']);
        $this->assertCount(1, $out[0]['groups'][0]['items']);
        $this->assertSame('invoices.index', $out[0]['groups'][0]['items'][0]['route']);
    }

    public function test_item_key_narrows_flat_section(): void {
        $out = $this->registry()->applyFocus($this->sampleSections(), ['item:duty-plans.index']);

        $this->assertCount(1, $out);
        $this->assertSame('plan', $out[0]['key']);
        $this->assertCount(1, $out[0]['items']);
        $this->assertSame('duty-plans.index', $out[0]['items'][0]['route']);
    }

    public function test_focus_never_adds_unknown_sections(): void {
        // Ein Schlüssel, den es im Bauplan nicht gibt, bringt nichts Neues.
        $out = $this->registry()->applyFocus($this->sampleSections(), ['section:does-not-exist']);

        $this->assertSame([], $out);
    }

    public function test_config_keep_keys(): void {
        $focus = app(NavFocusService::class);

        // 'all' filtert nie.
        $this->assertNull($focus->keepKeys('all'));
        // Ein kuratierter Bereich liefert seine Positivliste.
        $timeKeys = $focus->keepKeys('time');
        $this->assertIsArray($timeKeys);
        $this->assertContains('section:work', $timeKeys);
    }

    public function test_resolve_active_precedence(): void {
        $org = Organization::factory()->create();
        $org->settings = ['nav_focus_default' => 'finance'];
        $org->save();

        $user = User::factory()->admin()->create(['organization_id' => $org->id]);
        $focus = app(NavFocusService::class);

        // Session gewinnt vor allem (validiert gegen verfügbare Bereiche).
        $this->assertSame('service', $focus->resolveActive($user, $org, 'service'));
        // Ohne Session zählt die Preference.
        $user->setPreference(NavFocusService::PREFERENCE_KEY, 'inventory');
        $this->assertSame('inventory', $focus->resolveActive($user, $org, null));
        // Ungültige Session/Preference fallen auf den Org-Default zurück.
        $this->assertSame('finance', $focus->resolveActive(
            User::factory()->admin()->create(['organization_id' => $org->id]),
            $org,
            'gibt-es-nicht'
        ));
    }

    public function test_switch_persists_session_and_preference(): void {
        $org = Organization::factory()->create();
        $user = User::factory()->admin()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($user)->post(route('me.focus.switch', 'time'), ['origin' => 'home']);

        $response->assertRedirect();
        $this->assertSame('time', session(NavFocusService::SESSION_KEY));
        $this->assertSame('time', $user->fresh()?->getPreference(NavFocusService::PREFERENCE_KEY));
    }

    public function test_switch_rejects_unknown_focus(): void {
        $org = Organization::factory()->create();
        $user = User::factory()->admin()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($user)->post(route('me.focus.switch', 'does-not-exist'));

        $response->assertSessionHas('error');
        $this->assertNull(session(NavFocusService::SESSION_KEY));
        $this->assertNull($user->fresh()?->getPreference(NavFocusService::PREFERENCE_KEY));
    }

    public function test_admin_can_curate_workspaces(): void {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->get(route('admin.workspaces.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.workspaces.save'), [
            'available' => ['time', 'finance'],
            'default' => 'finance',
            'labels' => ['time' => 'Meine Zeit'],
        ])->assertRedirect();

        $settings = $org->fresh()?->settings ?? [];
        // 'all' bleibt immer angeboten; nicht gewählte Bereiche fallen raus.
        $this->assertContains('all', $settings['nav_focus_available']);
        $this->assertContains('finance', $settings['nav_focus_available']);
        $this->assertNotContains('sales', $settings['nav_focus_available']);
        $this->assertSame('finance', $settings['nav_focus_default']);
        $this->assertSame('Meine Zeit', $settings['nav_focus_labels']['time']);

        $focus = app(NavFocusService::class);
        $this->assertTrue($focus->isAvailableFor($org->fresh(), 'finance'));
        $this->assertFalse($focus->isAvailableFor($org->fresh(), 'sales'));
        $this->assertSame('finance', $focus->defaultFor($org->fresh()));
        $this->assertSame('Meine Zeit', $focus->label($org->fresh(), 'time'));
    }

    public function test_workspaces_page_requires_permission(): void {
        $org = Organization::factory()->create();
        $member = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($member)->get(route('admin.workspaces.index'))->assertForbidden();
    }

    public function test_branch_profile_supplies_default(): void {
        $org = Organization::factory()->create();
        $org->settings = ['branch_profile_code' => 'facility'];
        $org->save();

        // facility.php liefert nav_focus_default => 'facility' (nur Vorschlag).
        $this->assertSame('facility', app(NavFocusService::class)->defaultFor($org->fresh()));
    }

    public function test_catalog_marks_focus_hidden_entries(): void {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($admin)
            ->withSession([NavFocusService::SESSION_KEY => 'compliance'])
            ->get(route('me.functions'));

        $response->assertOk();
        $response->assertViewHas('focusActive', true);

        // Der Fokus 'compliance' blendet das Tagesgeschäft aus → mind. ein
        // lizenzierter, sichtbarer Eintrag ist als fokus-ausgeblendet markiert.
        $sections = $response->viewData('sections');
        $focusHidden = false;
        foreach ($sections as $section) {
            foreach ($section['entries'] as $entry) {
                if ($entry['in_focus_hidden'] === true) {
                    $focusHidden = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($focusHidden, 'Katalog sollte fokus-ausgeblendete Einträge markieren.');
    }
}
