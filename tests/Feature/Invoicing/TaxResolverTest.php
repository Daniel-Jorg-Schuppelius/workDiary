<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Organization, User};
use App\Services\Invoicing\TaxResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restpunkt 68: länderspezifische Steuerlogik der lokalen Fakturierung —
 * Länderkatalog + Org-Override, Reverse-Charge bei EU-B2B mit gültiger
 * USt-IdNr., Drittland-Export, Belegwährung bleibt Kundenwährung.
 */
final class TaxResolverTest extends TestCase {
    use RefreshDatabase;

    private function org(string $sellerCountry = 'DE', array $extra = []): Organization {
        $org = Organization::factory()->create([
            'settings' => array_replace(['einvoice' => ['country' => $sellerCountry]], $extra),
        ]);
        app()->instance('currentOrganization', $org);

        return $org;
    }

    private function customer(Organization $org, array $overrides = []): Customer {
        $user = User::factory()->create(['organization_id' => $org->id]);

        return Customer::factory()->create(array_replace([
            'organization_id' => $org->id,
            'country' => 'DE',
            'vat_id' => null,
            'currency' => 'EUR',
            'created_by' => $user->id,
        ], $overrides));
    }

    public function test_domestic_uses_country_catalog(): void {
        $org = $this->org('AT');
        $customer = $this->customer($org, ['country' => 'AT']);

        $tax = app(TaxResolver::class)->resolve($org, $customer);

        $this->assertSame('20.00', $tax['rate']);
        $this->assertFalse($tax['reverse_charge']);
    }

    public function test_org_override_wins_domestically(): void {
        $org = $this->org('DE', ['invoicing' => ['default_tax_rate' => '7.00']]);
        $customer = $this->customer($org, ['country' => 'DE']);

        $tax = app(TaxResolver::class)->resolve($org, $customer);

        $this->assertSame('7.00', $tax['rate']);
    }

    public function test_eu_b2b_with_valid_vat_id_is_reverse_charge(): void {
        $org = $this->org('DE');
        $customer = $this->customer($org, ['country' => 'AT', 'vat_id' => 'ATU13585627']);

        $tax = app(TaxResolver::class)->resolve($org, $customer);

        $this->assertSame('0.00', $tax['rate']);
        $this->assertTrue($tax['reverse_charge']);
        $this->assertStringContainsString('Reverse Charge', (string) $tax['note']);
    }

    public function test_eu_b2c_without_vat_id_keeps_seller_rate(): void {
        $org = $this->org('DE');
        $customer = $this->customer($org, ['country' => 'AT', 'vat_id' => null]);

        $tax = app(TaxResolver::class)->resolve($org, $customer);

        $this->assertSame('19.00', $tax['rate']);
        $this->assertFalse($tax['reverse_charge']);
    }

    public function test_third_country_is_zero_rated_export(): void {
        $org = $this->org('DE');
        $customer = $this->customer($org, ['country' => 'CH', 'currency' => 'CHF']);

        $tax = app(TaxResolver::class)->resolve($org, $customer);

        $this->assertSame('0.00', $tax['rate']);
        $this->assertFalse($tax['reverse_charge']);
        $this->assertNotNull($tax['note']);
        // Belegwährung bleibt Kundenwährung (keine Kursumrechnung).
        $this->assertSame('CHF', $customer->currency);
    }
}
