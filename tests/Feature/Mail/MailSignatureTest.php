<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailSignatureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\Platform\User;
use App\Services\Mail\MailSignature;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Signatur je Person (Präferenz, sonst Name und Organisation) und Fußzeile je Organisation unter Antworten aus WorkDiary. */
final class MailSignatureTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Acme GmbH']);
        $this->user = $this->orgUser(['name' => 'Max Mustermann']);
    }

    public function test_without_own_signature_name_and_organisation_are_used(): void {
        $this->assertSame("Max Mustermann\nAcme GmbH", app(MailSignature::class)->blockFor($this->user, $this->organization));
    }

    public function test_own_signature_and_organisation_footer_form_the_block(): void {
        $this->user->setPreference(MailSignature::PREFERENCE, "Max Mustermann\nProjektleitung · Tel. 030 1");
        Setting::set(MailSignature::FOOTER_KEY, "Acme GmbH · Musterstraße 1 · 10115 Berlin\nHRB 12345 · Geschäftsführung: Erika Muster", SettingScope::Organization, $this->organization);

        $signatures = app(MailSignature::class);
        $this->assertSame("Max Mustermann\nProjektleitung · Tel. 030 1", $signatures->signatureFor($this->user, $this->organization));
        $this->assertSame("Acme GmbH · Musterstraße 1 · 10115 Berlin\nHRB 12345 · Geschäftsführung: Erika Muster", $signatures->footerFor($this->organization));
        $this->assertSame(
            "Max Mustermann\nProjektleitung · Tel. 030 1\n\nAcme GmbH · Musterstraße 1 · 10115 Berlin\nHRB 12345 · Geschäftsführung: Erika Muster",
            $signatures->blockFor($this->user, $this->organization),
        );
    }

    public function test_system_footer_applies_until_the_organisation_overrides_it(): void {
        Setting::set(MailSignature::FOOTER_KEY, 'Betreiber-Fußzeile', SettingScope::System);
        $this->assertSame('Betreiber-Fußzeile', app(MailSignature::class)->footerFor($this->organization));

        Setting::set(MailSignature::FOOTER_KEY, 'Eigene Fußzeile', SettingScope::Organization, $this->organization);
        $this->assertSame('Eigene Fußzeile', app(MailSignature::class)->footerFor($this->organization->fresh()));
    }

    public function test_organisation_form_offers_the_footer_field(): void {
        Setting::set(MailSignature::FOOTER_KEY, 'Acme GmbH · HRB 1', SettingScope::Organization, $this->organization);

        $this->actingAs($this->orgAdmin())->get(route('admin.organizations.edit', $this->organization->fresh()))
            ->assertOk()
            ->assertSee('settings[communication][mail_footer]', false)
            ->assertSee('Acme GmbH · HRB 1');
    }

    public function test_profile_saves_the_signature_preference(): void {
        $this->actingAs($this->user)->put(route('account.profile.update'), [
            'name' => 'Max Mustermann',
            'email' => $this->user->email,
            'preferences' => ['mail_signature' => "Max\nTel. 1"],
        ])->assertRedirect();

        $this->assertSame("Max\nTel. 1", $this->user->fresh()?->getPreference(MailSignature::PREFERENCE));
    }
}
