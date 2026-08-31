<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoDiscoveryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sso;

use App\Enums\Auth\{SsoProtocol, SsoProviderType};
use App\Models\{Organization, OrganizationSsoDomain, SsoConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 057-Ausbau: E-Mail-Domain-Discovery. Aus der E-Mail-Adresse leitet
 * der Login die SSO-Organisation ab (global eindeutige Domain) und startet die
 * einzige aktive Verbindung bzw. zeigt die Anbieterauswahl. Domains werden im
 * Admin-Panel verwaltet; fremd belegte Domains werden abgelehnt.
 */
final class SsoDiscoveryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
    }

    private function connection(SsoProviderType $type, ?string $issuer = null): SsoConnection {
        return SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'provider_type' => $type->value,
            'label' => $type->label(),
            'issuer' => $issuer ?? SsoProviderType::GOOGLE_ISSUER,
            'client_id' => 'client',
            'client_secret' => 'secret',
            'active' => true,
        ]);
    }

    private function mapDomain(string $domain): void {
        OrganizationSsoDomain::query()->create([
            'organization_id' => $this->organization->id,
            'domain' => $domain,
            // Nur NACHGEWIESENE Domains lenken Anmeldungen (Sicherheitsscan
            // 2026-08-23, S-49).
            'verified_at' => now(),
        ]);
    }

    public function test_email_domain_routes_to_single_connection(): void {
        $connection = $this->connection(SsoProviderType::Google);
        $this->mapDomain('firma.de');

        $this->get(route('sso.discover', ['email' => 'User@Firma.de']))
            ->assertRedirect(route('sso.start', ['slug' => $this->organization->slug, 'connection' => $connection->sqid]));
    }

    public function test_email_domain_with_multiple_connections_shows_chooser(): void {
        $this->connection(SsoProviderType::Google);
        $this->connection(SsoProviderType::Microsoft, 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0');
        $this->mapDomain('firma.de');

        $this->get(route('sso.discover', ['email' => 'user@firma.de']))
            ->assertRedirect(route('sso.choose', ['slug' => $this->organization->slug]));

        $this->get(route('sso.choose', ['slug' => $this->organization->slug]))
            ->assertOk()
            ->assertSee('Google Workspace')
            ->assertSee('Microsoft 365');
    }

    public function test_unknown_email_domain_is_rejected(): void {
        $this->connection(SsoProviderType::Google);

        $this->get(route('sso.discover', ['email' => 'user@unknown.example']))
            ->assertRedirect(route('sso.discover'))
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_add_and_remove_email_domain(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('admin.sso.domains.add'), ['domain' => 'Firma.DE'])
            ->assertRedirect()->assertSessionHas('success');

        $domain = OrganizationSsoDomain::query()->firstOrFail();
        $this->assertSame('firma.de', $domain->domain);

        $this->actingAs($admin)->delete(route('admin.sso.domains.remove', $domain->sqid))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, OrganizationSsoDomain::query()->count());
    }

    public function test_domain_owned_by_other_org_is_rejected(): void {
        $other = Organization::factory()->create();
        OrganizationSsoDomain::query()->create(['organization_id' => $other->id, 'domain' => 'firma.de']);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($admin)->post(route('admin.sso.domains.add'), ['domain' => 'firma.de'])
            ->assertSessionHasErrors('domain');

        $this->assertSame(1, OrganizationSsoDomain::query()->count());
    }

    /**
     * S-49: Ohne DNS-Nachweis lenkt eine Domain nichts. Sonst genügte das
     * bloße Eintragen, um die Mail-Domain eines fremden Mandanten zu
     * beanspruchen und dessen Nutzer auf den eigenen IdP zu leiten.
     */
    public function test_unverifizierte_domain_lenkt_nicht(): void {
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->connection(SsoProviderType::Google);

        OrganizationSsoDomain::query()->create([
            'organization_id' => $this->organization->id,
            'domain' => 'ungeprueft.test',
            'verified_at' => null,
        ]);

        $this->get(route('sso.discover', ['email' => 'wer@ungeprueft.test']))
            ->assertRedirect(route('sso.discover'))
            ->assertSessionHasErrors('email');
    }

}
