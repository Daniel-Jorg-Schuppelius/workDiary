<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegalPagesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\User;
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
        $admin = User::factory()->admin()->create();
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
        $admin = User::factory()->admin()->create();
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
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('admin.settings.update', ['key' => 'legal.imprint']), [
            'scope' => 'organization',
            'value' => 'Org-Impressum',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_home_footer_links_to_legal_pages(): void {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="' . route('legal.imprint') . '"', false)
            ->assertSee('href="' . route('legal.privacy') . '"', false);
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
        $admin = User::factory()->admin()->create();
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
