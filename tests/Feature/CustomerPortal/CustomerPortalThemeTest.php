<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalThemeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Phase 154 (MVP-1241): Farbschema im Kundenportal — Seed im HTML, gespeicherte
 * Wahl am Portalkonto, Umschalter speichert, `auto` löscht, fremder Guard
 * kommt nicht an die Route, Vorschau ohne Umschalter.
 */
class CustomerPortalThemeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer, ['diary']);
        $this->portalUser = User::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
    }

    public function test_portal_pages_carry_the_theme_seed_and_toggle(): void {
        $this->actingAs($this->portalUser, 'customer');

        $page = $this->get(route('customer.dashboard'))->assertOk();
        $page->assertSee('window.__theme', false)
            ->assertSee('"authenticated":true', false)
            ->assertSee('data-theme-toggle', false)
            // @json escapt Schrägstriche.
            ->assertSee('customer-portal\\/farbschema', false);
    }

    public function test_saved_scheme_is_seeded_and_toggle_persists(): void {
        $this->actingAs($this->portalUser, 'customer');

        $this->putJson(route('customer.theme.update'), ['scheme' => 'dark'])
            ->assertOk()
            ->assertJson(['ok' => true, 'scheme' => 'dark']);
        $this->assertSame('dark', ((array) $this->portalUser->refresh()->preferences)['color_scheme'] ?? null);

        $this->get(route('customer.dashboard'))->assertOk()
            ->assertSee('"active":"' . config('theme.auto.dark') . '"', false);

        // Profilkarte: Formular speichert dieselbe Wahl, „auto" löscht sie.
        $this->post(route('customer.theme.update'), ['scheme' => 'auto'])
            ->assertRedirect(route('customer.profile.show'));
        $this->assertArrayNotHasKey('color_scheme', (array) $this->portalUser->refresh()->preferences);

        $this->putJson(route('customer.theme.update'), ['scheme' => 'neon'])->assertUnprocessable();
    }

    public function test_profile_shows_appearance_card_with_current_choice(): void {
        $this->portalUser->forceFill(['preferences' => ['color_scheme' => 'light']])->save();
        $this->actingAs($this->portalUser, 'customer');

        $this->get(route('customer.profile.show'))->assertOk()
            ->assertSee(__('customer_portal.appearance.title'))
            ->assertSee('value="light" class="radio radio-sm" checked', false)
            ->assertSee(route('locale.switch', 'en'), false);
    }

    public function test_theme_route_needs_the_customer_guard(): void {
        $this->putJson(route('customer.theme.update'), ['scheme' => 'dark'])->assertUnauthorized();

        $internal = $this->orgUser();
        $this->actingAs($internal)->putJson(route('customer.theme.update'), ['scheme' => 'dark'])->assertUnauthorized();
        $this->assertNull($internal->fresh()->preferences['color_scheme'] ?? null);
    }

    public function test_brand_logo_renders_both_variants_only_with_a_dark_logo(): void {
        $this->app->instance(\App\Services\UI\BrandingService::class, new class extends \App\Services\UI\BrandingService {
            public function logoUrl(string $variant = 'light'): ?string {
                return $variant === 'dark' ? '/logo-dark.png' : '/logo-light.png';
            }
        });
        $html = (string) $this->blade('<x-brand-logo class="h-9" alt="Org" />');
        $this->assertStringContainsString('src="/logo-light.png"', $html);
        $this->assertStringContainsString('wd-logo-light', $html);
        $this->assertStringContainsString('src="/logo-dark.png"', $html);
        $this->assertStringContainsString('wd-logo-dark', $html);

        // Ohne eigenes dunkles Logo liefert das Branding dieselbe Datei — nur ein Bild, keine Schaltklassen.
        $this->app->instance(\App\Services\UI\BrandingService::class, new class extends \App\Services\UI\BrandingService {
            public function logoUrl(string $variant = 'light'): ?string {
                return '/logo.png';
            }
        });
        $html = (string) $this->blade('<x-brand-logo class="h-9" alt="Org" />');
        $this->assertSame(1, substr_count($html, '<img'));
        $this->assertStringNotContainsString('wd-logo-', $html);

        $this->actingAs($this->portalUser, 'customer');
        $this->get(route('customer.dashboard'))->assertOk()->assertSee("root.setAttribute('data-scheme', scheme)", false);
    }

    public function test_guest_login_page_uses_local_choice_without_update_url(): void {
        $this->get(route('customer.login'))->assertOk()
            ->assertSee('"authenticated":false', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('themeUpdateUrl: null', false);
    }
}
