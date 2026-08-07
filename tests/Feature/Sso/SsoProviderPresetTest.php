<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoProviderPresetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sso;

use App\Enums\Auth\{SsoProtocol, SsoProviderType};
use App\Models\{Organization, SsoConnection, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 057-Ausbau: Anbieter-Presets (Microsoft 365 / Google Workspace) im
 * Admin-Panel. Microsoft leitet den tenant-spezifischen Issuer ab, Google
 * nutzt den festen Issuer; beide OIDC-Anbieter können parallel je Organisation
 * bestehen.
 */
final class SsoProviderPresetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const TENANT = '11111111-2222-3333-4444-555555555555';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_microsoft_preset_builds_tenant_specific_issuer(): void {
        $this->actingAs($this->admin)->post(route('admin.sso.connections.save'), [
            'protocol' => 'oidc',
            'provider_type' => 'microsoft',
            'label' => 'Microsoft 365',
            'tenant' => self::TENANT,
            'client_id' => 'client-ms',
            'client_secret' => 'secret',
            'active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $connection = SsoConnection::query()->where('provider_type', SsoProviderType::Microsoft->value)->firstOrFail();
        $this->assertSame('https://login.microsoftonline.com/' . self::TENANT . '/v2.0', $connection->issuer);
        $this->assertSame('openid profile email', $connection->scopes);
        $this->assertTrue($connection->active);
    }

    public function test_microsoft_without_tenant_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('admin.sso.connections.save'), [
            'protocol' => 'oidc',
            'provider_type' => 'microsoft',
            'label' => 'Microsoft 365',
            'client_id' => 'client-ms',
            'client_secret' => 'secret',
        ])->assertSessionHasErrors('tenant');

        $this->assertSame(0, SsoConnection::query()->count());
    }

    public function test_google_preset_uses_fixed_issuer(): void {
        $this->actingAs($this->admin)->post(route('admin.sso.connections.save'), [
            'protocol' => 'oidc',
            'provider_type' => 'google',
            'label' => 'Google Workspace',
            'client_id' => 'client-g',
            'client_secret' => 'secret',
            'active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $connection = SsoConnection::query()->where('provider_type', SsoProviderType::Google->value)->firstOrFail();
        $this->assertSame(SsoProviderType::GOOGLE_ISSUER, $connection->issuer);
    }

    public function test_microsoft_and_google_coexist_per_organization(): void {
        $this->actingAs($this->admin)->post(route('admin.sso.connections.save'), [
            'protocol' => 'oidc',
            'provider_type' => 'microsoft',
            'label' => 'Microsoft 365',
            'tenant' => self::TENANT,
            'client_id' => 'client-ms',
            'client_secret' => 'secret',
            'active' => '1',
        ])->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('admin.sso.connections.save'), [
            'protocol' => 'oidc',
            'provider_type' => 'google',
            'label' => 'Google Workspace',
            'client_id' => 'client-g',
            'client_secret' => 'secret',
            'active' => '1',
        ])->assertSessionHas('success');

        $this->assertSame(2, SsoConnection::query()->where('protocol', SsoProtocol::Oidc->value)->count());
    }

    public function test_editing_microsoft_without_tenant_keeps_issuer(): void {
        $issuer = 'https://login.microsoftonline.com/' . self::TENANT . '/v2.0';
        SsoConnection::query()->create([
            'organization_id' => $this->organization->id,
            'protocol' => SsoProtocol::Oidc->value,
            'provider_type' => SsoProviderType::Microsoft->value,
            'label' => 'Microsoft 365',
            'issuer' => $issuer,
            'client_id' => 'client-ms',
            'client_secret' => 'secret',
        ]);

        $this->actingAs($this->admin)->post(route('admin.sso.connections.save'), [
            'protocol' => 'oidc',
            'provider_type' => 'microsoft',
            'label' => 'Microsoft 365 (aktiv)',
            'client_id' => 'client-ms',
            'active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $connection = SsoConnection::query()->where('provider_type', SsoProviderType::Microsoft->value)->firstOrFail();
        $this->assertSame($issuer, $connection->issuer);
        $this->assertTrue($connection->active);
    }
}
