<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CreditorReferenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Finance\Banking\ReferenceExtractor;
use App\Services\Invoicing\GirocodeService;
use App\Settings\SettingScope;
use App\Support\Setting;
use CommonToolkit\Helper\Data\CreditorReferenceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-978: RF-Gläubigerreferenz (ISO 11649) auf Rechnung, Girocode und im Zahlungsabgleich. */
final class CreditorReferenceTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create(['settings' => ['branding' => ['legal' => [
            'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'bank_name' => 'Testbank', 'account_holder' => 'ACME GmbH',
        ]]]]);
        app()->instance('currentOrganization', $this->org);
        Setting::set('invoicing.girocode_enabled', true, SettingScope::Organization, $this->org);
    }

    private function invoice(): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id, 'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'R2030-0007', 'status' => Invoice::STATUS_ISSUED, 'currency' => 'EUR', 'tax_rate' => '19.00',
            'created_by' => User::factory()->admin()->create(['organization_id' => $this->org->id])->id,
        ]);
        $invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Beratung', 'quantity' => '1.000', 'unit_price' => '100.0000', 'tax_rate' => '19.00', 'position' => 1]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    public function test_girocode_carries_the_structured_reference_when_enabled(): void {
        $invoice = $this->invoice();
        $legal = app(\App\Services\UI\BrandingService::class)->legalFor($this->org);
        $this->assertNull($invoice->creditorReference());

        Setting::set('invoicing.creditor_reference', true, SettingScope::Organization, $this->org);
        $reference = $invoice->fresh()->creditorReference();
        $this->assertSame(CreditorReferenceHelper::create('R20300007'), $reference);

        $lines = explode("\n", (string) app(GirocodeService::class)->payload($invoice->fresh(), $legal));
        $this->assertSame($reference, $lines[9]);
        $this->assertSame('', $lines[10] ?? '');
    }

    public function test_reconciliation_recognises_the_invoice_number_behind_an_rf_reference(): void {
        $reference = (string) CreditorReferenceHelper::create('R20300007');
        $refs = ReferenceExtractor::extract('Zahlung ' . CreditorReferenceHelper::format($reference) . ' danke');

        $this->assertContains(ReferenceExtractor::normalize('R2030-0007'), array_map(ReferenceExtractor::normalize(...), $refs));
    }
}
