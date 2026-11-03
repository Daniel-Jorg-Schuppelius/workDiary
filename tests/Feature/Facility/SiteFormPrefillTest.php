<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SiteFormPrefillTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Facility;

use App\Models\Customer\Customer;
use App\Models\Facility\Site;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Standort-Dialog: Die Kundenoption trägt Anschrift, Land und Koordinaten, die
 * Adressfelder hängen am Kunden-Select (Vorbelegung aus der Elternauswahl,
 * Pilot 2026-11-03); beim Bearbeiten bleibt der Bestand unangetastet.
 */
class SiteFormPrefillTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgAdmin();
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterfirma GmbH',
            'address_street' => 'Musterweg 7',
            'address_zip' => '12345',
            'address_city' => 'Musterstadt',
            'country' => 'de',
            'address_lat' => '52.5200000',
            'address_lng' => '13.4050000',
        ]);
    }

    public function test_create_form_couples_address_fields_to_the_customer_option(): void {
        $html = $this->actingAs($this->user)->get(route('sites.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-prefill-address-street="Musterweg 7"', $html);
        $this->assertStringContainsString('data-prefill-address-zip="12345"', $html);
        $this->assertStringContainsString('data-prefill-address-city="Musterstadt"', $html);
        $this->assertStringContainsString('data-prefill-country="DE"', $html);
        $this->assertStringContainsString('data-prefill-geo-lat="52.5200000"', $html);
        $this->assertMatchesRegularExpression('/name="address_street"[^>]*data-prefill-from="customer_id"[^>]*data-prefill-guard[^>]*data-prefill-on-load/s', $html);
        $this->assertMatchesRegularExpression('/name="geo_lng"[^>]*data-prefill-from="customer_id"/s', $html);
    }

    public function test_edit_form_does_not_prefill_on_load(): void {
        $site = Site::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);

        $html = $this->actingAs($this->user)->get(route('sites.edit', $site))->assertOk()->getContent();

        $this->assertStringContainsString('data-prefill-from="customer_id"', $html);
        $this->assertStringNotContainsString('data-prefill-on-load', $html);
    }
}
