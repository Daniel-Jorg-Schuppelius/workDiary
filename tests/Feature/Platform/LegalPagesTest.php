<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegalPagesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Platform;

use App\Models\Platform\User;
use App\Settings\{SettingScope, SettingType, SettingsRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Öffentliche Rechtstexte (MVP-326): /impressum und /datenschutz sind
 * ohne Login erreichbar; Inhalte kommen aus der Settings-Registry
 * (legal.imprint / legal.privacy, System-Scope, Typ text).
 */
class LegalPagesTest extends TestCase {
    use RefreshDatabase;

    public function test_legal_pages_are_public_and_show_placeholder_without_content(): void {
        $this->get(route('legal.imprint'))
            ->assertOk()
            ->assertSee(__('Impressum'))
            ->assertSee(__('Der Betreiber dieser Installation hat diesen Rechtstext noch nicht hinterlegt.'))
            ->assertSee('legal.imprint');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee(__('Datenschutz'))
            ->assertSee('legal.privacy');
    }

    public function test_registry_registers_legal_keys_as_system_only_text(): void {
        $registry = app(SettingsRegistry::class);

        foreach (['legal.imprint', 'legal.privacy'] as $key) {
            $definition = $registry->definition($key);
            $this->assertSame(SettingType::Text, $definition->type);
            $this->assertSame([SettingScope::System], $definition->scopes);
            $this->assertFalse($definition->sensitive);
        }
    }

    public function test_configured_content_is_shown_with_line_breaks_and_escaped(): void {
        // Schreibweg über die Admin-UI: deckt den neuen Text-Typ inkl.
        // Registry-Validierung und System-Override-Ablage mit ab.
        // Impressum/Datenschutz sind System-Scope — also Betreiber-Sache
        // (Sicherheitsscan 2026-08-23, S-02).
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('admin.settings.update', ['key' => 'legal.imprint']), [
            'scope' => 'system',
            'value' => "Muster GmbH\nMusterstraße 1, 12345 Musterstadt\n<script>alert(1)</script>",
        ])->assertRedirect()->assertSessionMissing('error');

        $response = $this->get(route('legal.imprint'))->assertOk();
        $response->assertSee('Muster GmbH');
        $response->assertSee('Musterstraße 1, 12345 Musterstadt');
        // Betreiber-Text wird escaped ausgegeben (kein Roh-HTML).
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('<script>alert(1)</script>');
        // Platzhalter verschwindet, sobald Inhalt hinterlegt ist.
        $response->assertDontSee(__('Der Betreiber dieser Installation hat diesen Rechtstext noch nicht hinterlegt.'));
    }

    public function test_privacy_page_uses_its_own_setting(): void {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('admin.settings.update', ['key' => 'legal.privacy']), [
            'scope' => 'system',
            'value' => 'Datenschutzerklärung der Muster GmbH',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->get(route('legal.privacy'))->assertOk()->assertSee('Datenschutzerklärung der Muster GmbH');
        // Impressum bleibt unkonfiguriert.
        $this->get(route('legal.imprint'))
            ->assertOk()
            ->assertSee(__('Der Betreiber dieser Installation hat diesen Rechtstext noch nicht hinterlegt.'));
    }

    public function test_legal_keys_reject_organization_scope(): void {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('admin.settings.update', ['key' => 'legal.imprint']), [
            'scope' => 'organization',
            'value' => 'Org-Impressum',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_configured_url_redirects_and_wins_over_text(): void {
        $admin = User::factory()->platformAdmin()->create();
        foreach (['legal.imprint' => 'Eigener Text', 'legal.imprint_url' => 'https://www.example.com/impressum'] as $key => $value) {
            $this->actingAs($admin)->put(route('admin.settings.update', ['key' => $key]), [
                'scope' => 'system',
                'value' => $value,
            ])->assertRedirect()->assertSessionMissing('error');
        }

        $this->get(route('legal.imprint'))->assertRedirect('https://www.example.com/impressum');
        // Datenschutz bleibt ohne eigene URL die interne Seite.
        $this->get(route('legal.privacy'))->assertOk()->assertSee(__('Datenschutz'));
    }

    public function test_privacy_url_redirects_independently(): void {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('admin.settings.update', ['key' => 'legal.privacy_url']), [
            'scope' => 'system',
            'value' => 'https://www.example.com/datenschutz',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->get(route('legal.privacy'))->assertRedirect('https://www.example.com/datenschutz');
        $this->get(route('legal.imprint'))->assertOk();
    }

    public function test_url_keys_accept_only_http_and_https(): void {
        $admin = User::factory()->platformAdmin()->create();
        foreach (['javascript:alert(1)', 'ftp://example.com/impressum', 'kein-link'] as $value) {
            $this->actingAs($admin)->put(route('admin.settings.update', ['key' => 'legal.imprint_url']), [
                'scope' => 'system',
                'value' => $value,
            ])->assertRedirect();
        }

        $this->assertDatabaseCount('system_settings', 0);
        $this->get(route('legal.imprint'))->assertOk();
    }

    public function test_home_footer_links_to_legal_pages(): void {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="' . route('legal.imprint') . '"', false)
            ->assertSee('href="' . route('legal.privacy') . '"', false);
    }

    /** Rechtstexte und Formularseiten tragen x-guest-header/-footer wie die Startseite. */
    public function test_guest_pages_share_header_and_footer(): void {
        foreach (['home', 'legal.imprint', 'legal.privacy', 'legal.accessibility', 'login'] as $page) {
            $response = $this->get(route($page))->assertOk();

            $response->assertSee('data-theme-toggle', false)
                ->assertSee('aria-label="' . e(__('Rechtliches')) . '"', false);
            foreach (['legal.imprint', 'legal.privacy', 'legal.accessibility'] as $legal) {
                $response->assertSee('href="' . route($legal) . '"', false);
            }
        }
    }

    public function test_footer_marks_the_current_legal_page(): void {
        $html = (string) $this->get(route('legal.privacy'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~href="' . preg_quote(route('legal.privacy'), '~') . '"[^>]*aria-current="page"~', $html);
        $this->assertDoesNotMatchRegularExpression('~href="' . preg_quote(route('legal.imprint'), '~') . '"[^>]*aria-current="page"~', $html);
    }

    /** H18 (Vollscan 2026-08-23): BFSG-Pflichtseite mit Anlage-3-Gerüst als Default. */
    public function test_accessibility_statement_renders_the_default_skeleton(): void {
        $this->get(route('legal.accessibility'))
            ->assertOk()
            ->assertSee(__('Barrierefreiheit'))
            ->assertSee(__('Stand der Vereinbarkeit mit den Anforderungen'))
            ->assertSee(__('Durchsetzungsverfahren'));
    }

    /** H18: Betreiber-Text (legal.accessibility) ersetzt das Gerüst. */
    public function test_accessibility_statement_prefers_operator_text(): void {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('admin.settings.update', ['key' => 'legal.accessibility']), [
            'scope' => 'system',
            'value' => 'Individuelle Erklärung des Betreibers.',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->get(route('legal.accessibility'))
            ->assertOk()
            ->assertSee('Individuelle Erklärung des Betreibers.')
            ->assertDontSee(__('Durchsetzungsverfahren'));
    }
}
