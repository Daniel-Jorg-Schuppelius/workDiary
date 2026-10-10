<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSettingsDialogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Organization;

use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Dialog „Organisation" (MVP-1103): keine wirkungslosen Felder, keine rohen
 * Schlüssel, nur Einstellungen mit Organisationsebene, Texte übersetzt.
 */
final class OrganizationSettingsDialogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['locale' => 'de', 'timezone' => 'Europe/Berlin']);
    }

    private function dialog(?User $admin = null): string {
        return (string) $this->actingAs($admin ?? $this->orgAdmin())
            ->get(route('admin.organizations.edit', $this->organization))
            ->assertOk()
            ->getContent();
    }

    public function test_the_dialog_offers_only_fields_with_an_effect(): void {
        $html = $this->dialog();

        foreach (['customer_search', 'customer_attachments', 'dashboard_recent'] as $dead) {
            $this->assertStringNotContainsString('settings[pagination][' . $dead . ']', $html);
        }
        $this->assertStringNotContainsString('settings[ui][search]', $html);
        // Nur systemweit (Registry): gehört nicht in den Dialog der Organisation.
        $this->assertStringNotContainsString('settings[ui][news_feed]', $html);
        $this->assertStringNotContainsString('settings[pagination][organizations]', $html);

        $this->assertStringContainsString('settings[pagination][archive]', $html);
        $this->assertStringContainsString('settings[pagination][notifications]', $html);
        $this->assertStringContainsString('settings[ui][dashboard][recent_limit]', $html);
    }

    public function test_the_dialog_shows_no_raw_translation_keys(): void {
        $html = $this->dialog();

        $this->assertStringNotContainsString('settings.pagination.', $html);
        $this->assertStringNotContainsString('settings.ui.', $html);
        $this->assertStringContainsString(e(__('settings.pagination.notifications')), $html);
    }

    /** Leer gilt die Steuerregel, nicht ein fester Satz — der Platzhalter darf nichts anderes behaupten. */
    public function test_tax_rate_and_small_business_hints_describe_the_actual_effect(): void {
        $html = $this->dialog();

        $this->assertStringNotContainsString('Standard 19.00', $html);
        $this->assertStringContainsString(e(__('settings.invoicing.default_tax_rate_placeholder')), $html);
        $this->assertStringContainsString(e(__('settings.einvoice.small_business_hint')), $html);
        $this->assertStringContainsString('§ 19', __('settings.einvoice.small_business_hint'));
    }

    public function test_region_names_and_the_travel_default_follow_the_language(): void {
        $admin = $this->orgAdmin(['preferences' => ['locale' => 'en']]);

        $html = $this->dialog($admin);

        $this->assertStringContainsString('label="Germany"', $html);
        $this->assertStringContainsString('>Bavaria<', $html);
        $this->assertStringContainsString('label="Switzerland"', $html);
        $this->assertStringContainsString('>Geneva<', $html);
        $this->assertStringNotContainsString('>Bayern<', $html);
        $this->assertStringNotContainsString('label="Deutschland"', $html);
        $this->assertStringNotContainsString('placeholder="Anfahrt"', $html);
        $this->assertStringContainsString('placeholder="Travel"', $html);
    }

    /** Die Vier-Augen-Einstellung der Buchhaltung hatte keinen Schalter (Phase 137, E9). */
    public function test_four_eyes_principle_can_be_switched_in_the_dialog(): void {
        $admin = $this->orgAdmin();
        $this->assertStringContainsString('name="settings[finance][accounting_four_eyes]"', $this->dialog($admin));

        $this->actingAs($admin)->put(route('admin.organizations.update', $this->organization), [
            'name' => $this->organization->name,
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
            'settings' => ['finance' => ['accounting_four_eyes' => '1']],
        ])->assertRedirect();

        $this->assertTrue((bool) data_get($this->organization->refresh()->settings, 'finance.accounting_four_eyes'));
    }
}
