<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdentifierLookupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Stammdaten;

use App\Enums\Inventory\BarcodeMatchType;
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Sepa\PaymentProposalService;
use App\Services\Inventory\{BarcodeResolver, VariantMatcher};
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Suche über Kennungen (MVP-1527, nl-08): Seit MVP-1512 speichern die Casts
 * USt-IdNr. und GTIN kanonisch; die Suchstellen finden sie auch mit
 * Leerzeichen und Kleinbuchstaben, Altbestand und Ungültiges über den
 * Rohvergleich.
 */
final class IdentifierLookupTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_scope_finds_canonical_values_by_formatted_input_and_legacy_values_by_raw_input(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Dünenhof Kaffeerösterei', 'vat_id' => 'de 811 907 980']);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Seilerei Brandung', 'vat_id' => 'DE811907980']);
        $this->assertSame('DE811907980', DB::table('customers')->where('id', $customer->id)->value('vat_id'));

        foreach (['DE811907980', 'de811907980', ' DE 811 907 980 ', 'de 811-907-980'] as $input) {
            $this->assertSame([$customer->id], Customer::query()->whereIdentifier('vat_id', $input)->pluck('id')->all(), $input);
            $this->assertSame([$supplier->id], Supplier::query()->whereIdentifier('vat_id', $input)->pluck('id')->all(), $input);
        }

        // Altbestand vor den Casts und ungültige Werte bleiben über die getrimmte Eingabe auffindbar.
        DB::table('suppliers')->where('id', $supplier->id)->update(['vat_id' => 'ATU-Alt 12']);
        $this->assertSame([$supplier->id], Supplier::query()->whereIdentifier('vat_id', ' ATU-Alt 12 ')->pluck('id')->all());
        $this->assertSame([], Supplier::query()->whereIdentifier('vat_id', '')->pluck('id')->all());
    }

    public function test_scope_refuses_encrypted_and_non_identifier_columns(): void {
        $this->assertThrows(fn () => Customer::query()->whereIdentifier('bank_iban', 'DE89370400440532013000')->get(), LogicException::class);
        $this->assertThrows(fn () => Customer::query()->whereIdentifier('name', 'Dünenhof')->get(), LogicException::class);
    }

    public function test_incoming_invoice_suggestion_and_supplier_recognition_match_a_formatted_vat_id(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Seilerei Brandung', 'vat_id' => 'DE811907980']);

        $suggestions = app(IncomingEInvoiceService::class)->suggestions((int) $this->organization->id, ['seller_vat' => 'de 811 907 980', 'seller' => 'Unbekannter Absender']);

        $this->assertSame([$supplier->id], array_column($suggestions['suppliers'], 'id'));
        $this->assertSame(['USt-IdNr. stimmt überein'], $suggestions['suppliers'][0]['reasons']);
    }

    public function test_payment_proposal_finds_the_supplier_by_a_formatted_vat_id(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Seilerei Brandung', 'vat_id' => 'DE811907980']);
        $invoice = IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id,
            'document_id' => Document::factory()->create(['organization_id' => $this->organization->id])->id,
            'sha256' => hash('sha256', 'identifier-lookup'),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::PaymentReleased,
            'invoice_number' => 'SB-2026-17',
            'seller_name' => 'Brandung Seile (Rechnungsname)',
            'seller_vat_id' => 'de 811907980',
            'issue_date' => CarbonImmutable::today()->subDays(3)->toDateString(),
            'due_date' => CarbonImmutable::today()->addDays(27)->toDateString(),
            'currency' => 'EUR',
            'amount_gross' => '119.00',
            'creditor_iban' => 'DE89370400440532013000',
        ]);

        $this->assertSame($supplier->id, app(PaymentProposalService::class)->proposalFor($invoice)['supplier']?->id);
    }

    public function test_gtin_lookups_match_a_formatted_code(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Festmacherleine 12 mm', 'gtin' => '4006381333931']);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'FL-12-ROT', 'gtin' => '4 006381 333931']);
        $this->assertSame('4006381333931', DB::table('article_variants')->where('id', $variant->id)->value('gtin'));

        $match = app(VariantMatcher::class)->match((int) $this->organization->id, '', '4006381 333931');
        $this->assertSame($variant->id, $match['variant']?->id);

        $scan = app(BarcodeResolver::class)->resolve('4-006381-333931');
        $this->assertSame(BarcodeMatchType::Variant, $scan->type);
        $this->assertSame($variant->id, $scan->variant?->id);
    }
}
