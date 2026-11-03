<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNoticePrefillTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Construction;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Baustellenanzeige: Die Kundenoption trägt die E-Mail, das Empfängerfeld hängt am Kunden (Pilot 2026-11-03). */
class ConstructionNoticePrefillTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_create_form_offers_the_customer_email_as_recipient(): void {
        $this->setUpOrganization();
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Musterbau GmbH', 'email' => 'bauleitung@example.test']);

        $html = $this->actingAs($this->orgAdmin())
            ->get(route('construction-notices.create', ['kind' => RenderDocumentKind::ConstructionObstructionNotice->value]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-prefill-recipient-email="bauleitung@example.test"', $html);
        $this->assertMatchesRegularExpression('/name="recipient_email"[^>]*data-prefill-from="customer_id"[^>]*data-prefill-on-load/s', $html);
    }
}
