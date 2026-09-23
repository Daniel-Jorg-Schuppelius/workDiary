<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarkdownMailRenderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Mail\{CustomerPortalInvitationMail, TwoFactorCodeMail};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * UI-Fuzz 2026-09-21: Beide Mails renderten ihre Markdown-Vorlage als normale
 * View und scheiterten („No hint path defined for [mail]“) — die Portal-
 * Einladung als 500, der 2FA-Code kam nie an. Mail::fake() rendert nicht.
 */
class MarkdownMailRenderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_two_factor_code_mail_renders(): void {
        $html = (new TwoFactorCodeMail('482913'))->render();

        $this->assertStringContainsString('482913', $html);
    }

    public function test_customer_portal_invitation_renders(): void {
        $this->setUpOrganization();
        $portalUser = User::factory()->create(['organization_id' => $this->organization->id]);

        $html = (new CustomerPortalInvitationMail($portalUser, 'https://example.test/einladung/abc'))->render();

        $this->assertStringContainsString('https://example.test/einladung/abc', $html);
    }
}
