<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityOverviewPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{AuditLog, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-Sicherheitsübersicht (Feature 016, MVP) — HTTP-Sicht: Zugriff nur
 * für Admin, korrekte Aggregat-Anzeige, und der Negativ-Nachweis, dass
 * niemals Token-Werte/Secrets gerendert werden.
 */
class SecurityOverviewPageTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.security.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.security.index'))->assertForbidden();
    }

    public function test_index_renders_for_admin_with_sections_and_privacy_notice(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee(__('security.title.index'))
            ->assertSee(__('security.privacy_notice'))
            // Lösch-/Aufbewahrungsläufe als offen/abgegrenzt anzeigen.
            ->assertSee(__('security.deferred_notice'))
            ->assertSee(__('security.section.sessions'))
            ->assertSee(__('security.section.tokens'))
            ->assertSee(__('security.section.integrations'))
            ->assertSee(__('security.section.exports'))
            ->assertSee(__('security.section.support_access'))
            ->assertSee(__('security.section.two_factor'))
            ->assertSee(__('security.section.encryption'));
    }

    public function test_index_lists_api_token_metadata_but_never_the_token_value(): void {
        $admin = User::factory()->admin()->create();

        // Echter Sanctum-Token: liefert einen Klartext-Wert, der NIEMALS in
        // der Übersicht erscheinen darf — nur die Metadaten (Name).
        $newToken = $admin->createToken('CI-Pipeline-Token', ['diary:read']);
        $plainText = $newToken->plainTextToken;
        $hash = $newToken->accessToken->getAttribute('token');

        $response = $this->actingAs($admin)->get(route('admin.security.index'));

        $response->assertOk()
            ->assertSee('CI-Pipeline-Token')      // Metadatum: Name ist erlaubt
            ->assertSee('diary:read')             // Metadatum: Ability ist erlaubt
            ->assertDontSee($plainText)           // Klartext-Token NIE
            ->assertDontSee($hash);               // Token-Hash NIE
    }

    public function test_index_shows_recent_support_access_events(): void {
        $admin = User::factory()->admin()->create();
        AuditLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'support.reportGenerated',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'changes' => ['bytes' => 1024],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.security.index'))
            ->assertOk()
            ->assertSee(__('security.section.support_access'))
            ->assertSee('support.reportGenerated');
    }

    public function test_index_shows_encryption_status_section(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.security.index'))
            ->assertOk()
            // At-rest-Verschlüsselungs-Hinweis nennt das Kommando, nicht Daten.
            ->assertSee('security:encrypt-existing')
            ->assertSee('tax_identification_number');
    }
}
