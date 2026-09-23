<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantLockPublicSurfacesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Enums\Organization\TenantStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (tenant-status-1): Die Mandantensperre (S-42)
 * galt nur für angemeldete Zugriffe und die Ingest-Endpunkte. Tokenbasierte
 * öffentliche Wege — Angebot annehmen, unterschreiben, SCIM — schrieben
 * weiter in gesperrte Mandanten.
 */
final class TenantLockPublicSurfacesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_quote_portal_is_locked_for_suspended_tenants(): void {
        $this->setUpOrganization();
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde',
            'currency' => 'EUR',
            'created_by' => $user->id,
        ]);

        $service = app(QuoteService::class);
        $quote = $service->create([
            'customer_id' => $customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [
            ['description' => 'Leistung', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => '19.00'],
        ], $user);
        $quote = $service->approve($quote, $user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $user);

        // Offener Mandant: der Link trägt.
        $this->get(route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => $token]))->assertOk();

        $this->organization->forceFill(['tenant_status' => TenantStatus::Suspended])->save();

        $this->get(route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => $token]))
            ->assertStatus(423);
        $this->post(route('quotes.portal.decide', ['quote' => $quote->getRouteKey()]), [
            'token' => $token,
            'decision' => 'accept',
        ])->assertStatus(423);
    }
}
