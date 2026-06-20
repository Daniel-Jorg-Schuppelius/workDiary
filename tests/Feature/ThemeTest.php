<?php
/*
 * Created on   : Sat Jun 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ThemeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Organization, User};
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase {
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function customTheme(string $key = 'ocean'): array {
        return [
            'key' => $key, 'label' => 'Ocean', 'scheme' => 'light',
            'colors' => [
                'base-100' => '#ffffff', 'base-200' => '#f1f5f9', 'base-300' => '#e2e8f0',
                'primary' => '#0284c7', 'secondary' => '#475569', 'accent' => '#0d9488', 'neutral' => '#1e293b',
            ],
        ];
    }

    private function actingResolver(Organization $org, User $user): ThemeService {
        $this->actingAs($user);
        $this->app->forgetInstance(\App\Services\BrandingService::class);
        $this->app->forgetInstance(ThemeService::class);

        return app(ThemeService::class);
    }

    public function test_resolve_active_key_uses_user_pref_else_auto(): void {
        $org = Organization::factory()->enterprise()->create([
            'settings' => ['theme' => ['custom' => [$this->customTheme()], 'default_light' => 'emerald', 'default_dark' => 'business']],
        ]);
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        // Keine persönliche Wahl → 'auto' (folgt System über das Org-Hell/Dunkel-Paar).
        $svc = $this->actingResolver($org, $user);
        $this->assertSame('auto', $svc->resolveActiveKey());
        $seed = $svc->seed();
        $this->assertSame('emerald', $seed['autoLight']);
        $this->assertSame('business', $seed['autoDark']);

        // Persönliche Wahl auf das Org-Custom-Theme → gewinnt.
        $user->update(['preferences' => ['theme' => 'org-ocean']]);
        $this->assertSame('org-ocean', $this->actingResolver($org, $user)->resolveActiveKey());

        // Referenz auf nicht existierendes Theme → Fallback auf 'auto'.
        $user->update(['preferences' => ['theme' => 'org-ghost']]);
        $this->assertSame('auto', $this->actingResolver($org, $user)->resolveActiveKey());
    }

    public function test_org_default_pair_with_deleted_themes_falls_back_to_builtin(): void {
        $org = Organization::factory()->enterprise()->create([
            'settings' => ['theme' => ['custom' => [], 'default_light' => 'org-removed', 'default_dark' => 'org-gone']],
        ]);
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        $seed = $this->actingResolver($org, $user)->seed();
        $this->assertSame('corporate', $seed['autoLight']);
        $this->assertSame('dim', $seed['autoDark']);
    }

    public function test_set_default_pair_persists_and_rejects_wrong_scheme(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)
            ->put(route('admin.themes.default'), ['default_light' => 'emerald', 'default_dark' => 'business'])
            ->assertRedirect(route('admin.themes.index'));
        $org->refresh();
        $this->assertSame('emerald', data_get($org->settings, 'theme.default_light'));
        $this->assertSame('business', data_get($org->settings, 'theme.default_dark'));

        // Dunkel-Theme im Hell-Slot → abgelehnt.
        $this->actingAs($admin)
            ->put(route('admin.themes.default'), ['default_light' => 'dim', 'default_dark' => 'business'])
            ->assertSessionHasErrors('default_light');
    }

    public function test_custom_theme_css_is_injected_into_layout(): void {
        $org = Organization::factory()->enterprise()->create([
            'settings' => ['theme' => ['custom' => [$this->customTheme()]]],
        ]);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('[data-theme="org-ocean"]', false);
        $response->assertSee('window.__theme', false);
    }

    public function test_theme_editor_blocked_on_free_plan(): void {
        $org = Organization::factory()->free()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)
            ->get(route('admin.themes.index'))
            ->assertStatus(423);
    }

    public function test_theme_editor_reachable_on_enterprise(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)
            ->get(route('admin.themes.index'))
            ->assertOk();
    }

    public function test_admin_can_store_custom_theme(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)
            ->post(route('admin.themes.store'), $this->customTheme('brandx'))
            ->assertRedirect(route('admin.themes.index'));

        $org->refresh();
        $stored = data_get($org->settings, 'theme.custom');
        $this->assertIsArray($stored);
        $this->assertSame('brandx', $stored[0]['key']);
        // -content-Farben wurden serverseitig abgeleitet und mitgespeichert.
        $this->assertArrayHasKey('primary-content', $stored[0]['colors']);
    }

    public function test_store_rejects_injection_in_color(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $payload = $this->customTheme('evil');
        $payload['colors']['base-100'] = '#000;}body{display:none}';

        $this->actingAs($admin)
            ->post(route('admin.themes.store'), $payload)
            ->assertSessionHasErrors('colors.base-100');

        $org->refresh();
        $this->assertEmpty(data_get($org->settings, 'theme.custom', []));
    }

    public function test_store_rejects_low_neutral_contrast(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $payload = $this->customTheme('lowcontrast');
        // neutral ~ neutral-content (beide hell) → unlesbar → muss abgelehnt werden.
        $payload['colors']['neutral'] = '#f4f4f4';
        $payload['colors']['neutral-content'] = '#ffffff';

        $this->actingAs($admin)
            ->post(route('admin.themes.store'), $payload)
            ->assertSessionHasErrors('colors.neutral-content');
    }

    public function test_deleting_default_theme_clears_org_default(): void {
        $org = Organization::factory()->enterprise()->create([
            'settings' => ['theme' => ['custom' => [$this->customTheme()], 'default_light' => 'org-ocean']],
        ]);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)
            ->delete(route('admin.themes.destroy', 'ocean'))
            ->assertRedirect(route('admin.themes.index'));

        $org->refresh();
        $this->assertEmpty(data_get($org->settings, 'theme.custom', []));
        $this->assertNull(data_get($org->settings, 'theme.default_light'));
    }

    public function test_header_toggle_persists_color_scheme_and_drives_org_pair(): void {
        $org = Organization::factory()->enterprise()->create([
            'settings' => ['theme' => ['custom' => [], 'default_light' => 'emerald', 'default_dark' => 'business']],
        ]);
        // User mit konkretem Theme — der Header-Modus muss das aufheben.
        $user = User::factory()->user()->create(['organization_id' => $org->id, 'preferences' => ['theme' => 'nord']]);

        // Header → Dunkel: color_scheme=dark, konkretes Theme weg.
        $this->actingAs($user)
            ->putJson(route('account.theme.update'), ['scheme' => 'dark'])
            ->assertOk()->assertJson(['ok' => true, 'scheme' => 'dark']);
        $fresh = $user->fresh();
        $this->assertSame('dark', $fresh->preferences['color_scheme'] ?? null);
        $this->assertArrayNotHasKey('theme', (array) $fresh->preferences);
        // Auflösung: dark → Org-Dunkel-Theme 'business'.
        $this->assertSame('business', $this->actingResolver($org, $fresh)->resolveActiveKey());

        // Header → Hell: löst auf das Org-Hell-Theme 'emerald' auf (nicht „hängen").
        $this->actingAs($fresh)->putJson(route('account.theme.update'), ['scheme' => 'light'])->assertOk();
        $this->assertSame('emerald', $this->actingResolver($org, $user->fresh())->resolveActiveKey());

        // Ungültiger Modus → abgelehnt.
        $this->actingAs($user->fresh())
            ->putJson(route('account.theme.update'), ['scheme' => 'sideways'])
            ->assertStatus(422);
    }

    public function test_profile_save_preserves_header_color_scheme(): void {
        $org = Organization::factory()->enterprise()->create();
        $user = User::factory()->user()->create([
            'organization_id' => $org->id,
            'preferences' => ['color_scheme' => 'dark', 'locale' => 'de'],
        ]);

        // Profil speichern ohne konkretes Theme darf color_scheme nicht löschen.
        $this->actingAs($user)
            ->put(route('account.profile.update'), [
                'name' => $user->name, 'email' => $user->email,
                'preferences' => ['startpage' => 'dashboard'],
            ])
            ->assertRedirect();

        $this->assertSame('dark', $user->fresh()->preferences['color_scheme'] ?? null);
    }

    public function test_profile_accepts_custom_theme_token_and_rejects_unknown(): void {
        $org = Organization::factory()->enterprise()->create([
            'settings' => ['theme' => ['custom' => [$this->customTheme()]]],
        ]);
        $user = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($user)
            ->put(route('account.profile.update'), [
                'name' => $user->name, 'email' => $user->email,
                'preferences' => ['theme' => 'org-ocean'],
            ])
            ->assertRedirect();
        $user->refresh();
        $this->assertSame('org-ocean', $user->preferences['theme'] ?? null);

        $this->actingAs($user)
            ->put(route('account.profile.update'), [
                'name' => $user->name, 'email' => $user->email,
                'preferences' => ['theme' => 'org-does-not-exist'],
            ])
            ->assertSessionHasErrors('preferences.theme');
    }
}
