<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxRuleMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, TaxRule, User};
use App\Services\Invoicing\TaxResolver;
use Database\Seeders\TaxRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 23 (MVP-237–244): versionierte Steuerregelmatrix —
 * Stichtagsauflösung (DE-Covid-Absenkung 2020 + AT-4,9-%-Wechsel als
 * W1-Testfall), Org-Override, Überschneidungssperre, expliziter
 * Fallback, eingefrorener Steuerkontext am Beleg und Mischsatz-Split
 * in der DATEV-Übergabe.
 */
final class TaxRuleMatrixTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'country' => 'DE']);
    }

    public function test_effective_date_resolution_covers_de_covid_cut_and_at_w1_switch(): void {
        $this->seed(TaxRulesSeeder::class);
        $resolver = app(TaxResolver::class);

        // DE-Stichtagswechsel: Covid-Absenkung Jul–Dez 2020.
        $covid = $resolver->resolve($this->org, $this->customer, new \DateTimeImmutable('2020-08-15'));
        $this->assertSame('16.00', $covid['rate']);
        $this->assertSame('Zweites Corona-Steuerhilfegesetz', $covid['rule']['source'] ?? null);
        $today = $resolver->resolve($this->org, $this->customer, new \DateTimeImmutable('2026-07-10'));
        $this->assertSame('19.00', $today['rate']);

        // AT-W1-Testfall: media reduced 10 % → 4,9 % zum 01.07.2026.
        $before = $resolver->ruleFor($this->org->id, 'AT', 'media', 'reduced', new \DateTimeImmutable('2026-06-30'));
        $after = $resolver->ruleFor($this->org->id, 'AT', 'media', 'reduced', new \DateTimeImmutable('2026-07-01'));
        $this->assertSame('10.00', $before?->rate?->getNumericValue());
        $this->assertSame('4.90', $after?->rate?->getNumericValue());
        $this->assertStringContainsString('W1', (string) $after?->source);
    }

    public function test_org_override_wins_and_overlap_is_rejected(): void {
        $this->seed(TaxRulesSeeder::class);
        $resolver = app(TaxResolver::class);

        // Org-Override: eigener Standardsatz ab 2026.
        $override = new TaxRule([
            'organization_id' => $this->org->id,
            'country' => 'DE', 'category' => 'services', 'rate_type' => 'standard',
            'rate' => '18.00', 'valid_from' => '2026-01-01', 'status' => 'active',
        ]);
        $resolver->assertNoOverlap($override); // Org-Zeile ≠ Katalog-Zeile → kein Konflikt
        $override->save();

        $resolved = $resolver->resolve($this->org, $this->customer, new \DateTimeImmutable('2026-07-10'));
        $this->assertSame('18.00', $resolved['rate']);
        $this->assertTrue($resolved['rule']['org_override'] ?? false);

        // Überschneidung im selben Geltungsbereich wird abgewiesen.
        $conflict = new TaxRule([
            'organization_id' => $this->org->id,
            'country' => 'DE', 'category' => 'services', 'rate_type' => 'standard',
            'rate' => '17.00', 'valid_from' => '2026-06-01', 'status' => 'active',
        ]);
        $this->expectException(\RuntimeException::class);
        $resolver->assertNoOverlap($conflict);
    }

    public function test_explicit_fallback_to_static_catalog_without_rules(): void {
        // KEINE Regeln geseedet → expliziter Fallback auf config/taxation.php.
        $resolved = app(TaxResolver::class)->resolve($this->org, $this->customer);
        $this->assertSame('19.00', $resolved['rate']);
        $this->assertNull($resolved['rule']);
        $this->assertSame('S', $resolved['category']);
    }

    public function test_issue_freezes_tax_context_against_later_rule_changes(): void {
        $this->seed(TaxRulesSeeder::class);

        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-9001',
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung', 'quantity' => '1', 'unit' => 'Std.', 'unit_price' => '100.00', 'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertRedirect();

        $issued = $invoice->fresh();
        $this->assertNotNull($issued->tax_context);
        $this->assertSame('19.00', (string) data_get($issued->tax_context, 'rate'));
        $this->assertNotNull(data_get($issued->tax_context, 'rule.id'), 'Regelquelle muss im Snapshot stehen.');

        // Spätere Regeländerung darf den Beleg NICHT umdeuten.
        TaxRule::query()->whereNull('organization_id')->where('country', 'DE')->update(['rate' => '25.00']);
        $after = $invoice->fresh();
        $this->assertSame('19.00', (string) data_get($after->tax_context, 'rate'));
        $this->assertSame('19.00', $after->tax_rate?->getNumericValue());

        // Und der eingefrorene Kontext ist unveränderlich (Model-Guard).
        $this->expectException(\RuntimeException::class);
        $after->update(['tax_context' => ['rate' => '7.00']]);
    }

    public function test_reverse_charge_invoice_gets_ae_category_in_xrechnung(): void {
        $this->org->update(['settings' => ['einvoice' => [
            'seller_name' => 'Test GmbH', 'street' => 'Weg 1', 'zip' => '12345', 'city' => 'Berlin',
            'country' => 'DE', 'vat_id' => 'DE123456789', 'contact_name' => 'Max', 'contact_email' => 'm@t.de',
            'contact_phone' => '+49 30 1', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX',
            'account_holder' => 'Test GmbH', 'payment_terms_days' => 14,
        ]]]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'country' => 'AT',
            'vat_id' => 'ATU12345675',
            'address_street' => 'Ring 1', 'address_zip' => '1010', 'address_city' => 'Wien',
            'buyer_reference' => 'REF-1',
        ]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'R2026-9002',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => now(), 'due_on' => now()->addDays(14),
            'currency' => 'EUR',
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '0.00',
            'is_reverse_charge' => true,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Leistung', 'quantity' => '1', 'unit' => 'Std.', 'unit_price' => '100.00', 'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        $xml = app(\App\Services\Invoicing\EInvoice\XRechnungGenerator::class)->generate($invoice->fresh(['items', 'customer']));

        // Folge-Lücke 1 geschlossen: Reverse Charge = Kategorie AE, nicht Z.
        $this->assertStringContainsString('>AE<', $xml);
        $this->assertStringNotContainsString('>Z<', $xml);
    }

    public function test_datev_rows_split_by_tax_breakdown(): void {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-9003',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => now(),
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
        ]);
        $invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Ware A', 'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '100.00', 'position' => 1]);
        $invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Buch', 'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '50.00', 'tax_rate' => '7.00', 'position' => 2]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        $config = \App\Services\Finance\Datev\DatevBookingConfig::forOrganization($this->org);

        $rows = app(\App\Services\Finance\DatevBookingService::class)->buildBookingRows(collect([$invoice->fresh(['items', 'customer'])]), $config);

        $this->assertCount(2, $rows, 'Mischsatz-Beleg muss je Steuersatz gesplittet werden.');
        $rates = array_map(fn(array $row): float => $row['tax_rate'], $rows);
        sort($rates);
        $this->assertSame([7.0, 19.0], $rates);
        $this->assertEqualsWithDelta(53.5, min(array_column($rows, 'amount')), 0.01); // 50 + 7 %
        $this->assertEqualsWithDelta(119.0, max(array_column($rows, 'amount')), 0.01); // 100 + 19 %
    }
}
