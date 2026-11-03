<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalFormPrefillTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Verleihakte: Die Kundenoption trägt den Ansprechpartner, das Feld hängt am Kunden (Pilot 2026-11-03). */
class RentalFormPrefillTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_create_form_offers_the_customer_contact_as_contact_name(): void {
        $this->setUpOrganization();
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterfirma GmbH',
            'contact_first_name' => 'Mira',
            'contact_last_name' => 'Muster',
        ]);

        $html = $this->actingAs($this->orgAdmin())->get(route('rental.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-prefill-contact-name="Mira Muster"', $html);
        $this->assertMatchesRegularExpression('/name="contact_name"[^>]*data-prefill-from="customer_id"[^>]*data-prefill-on-load/s', $html);
    }
}
