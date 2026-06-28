<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeOrderConfirmationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Article, ArticleVariant, Customer, ExternalReference, ManufacturingOrder};
use App\Plugins\Lexoffice\{LexofficeOrderConfirmationService, LexofficePlugin, LexofficeQuotationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lexoffice-Auftragsbestätigung aus einem kundenbezogenen Fertigungsauftrag.
 */
final class LexofficeOrderConfirmationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');
        config()->set('plugins.lexoffice.default_vat_rate', 19.0);
        config()->set('plugins.lexoffice.default_currency', 'EUR');

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id, 'email' => 'kunde@example.com',
        ]);
    }

    private function makeOrder(): ManufacturingOrder {
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'name' => 'Spezialteil', 'base_unit' => 'Stk',
        ]);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default', 'sale_price' => '200.0000',
        ]);

        return ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
            'customer_id' => $this->customer->id,
            'target_qty' => '5',
            'unit' => 'Stk',
        ]);
    }

    private function seedContactReference(): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->getKey(),
            'external_id' => 'lex-contact-1', 'synced_at' => now(),
        ]);
    }

    public function test_push_creates_order_confirmation(): void {
        $this->seedContactReference();
        $order = $this->makeOrder();

        Http::fake([
            'https://api.lexoffice.io/v1/order-confirmations*' => Http::response(['id' => 'lex-oc-1'], 201),
        ]);

        $reference = app(LexofficeOrderConfirmationService::class)->push($order);

        $this->assertSame(LexofficeOrderConfirmationService::EXT_TYPE_ORDER_CONFIRMATION, $reference->external_type);
        $this->assertSame('lex-oc-1', $reference->external_id);
        $this->assertSame($order->getMorphClass(), $reference->referenceable_type);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/order-confirmations')) {
                return false;
            }
            $body = $request->data();

            return data_get($body, 'address.contactId') === 'lex-contact-1'
                && data_get($body, 'lineItems.0.quantity') === 5.0
                && data_get($body, 'lineItems.0.unitPrice.netAmount') === 200.0
                && str_contains((string) data_get($body, 'lineItems.0.name'), 'Spezialteil');
        });
    }

    public function test_quotation_push_creates_quotation(): void {
        $this->seedContactReference();
        $order = $this->makeOrder();

        Http::fake([
            'https://api.lexoffice.io/v1/quotations*' => Http::response(['id' => 'lex-q-1'], 201),
        ]);

        $reference = app(LexofficeQuotationService::class)->push($order);

        $this->assertSame(LexofficeQuotationService::EXT_TYPE_QUOTATION, $reference->external_type);
        $this->assertSame('lex-q-1', $reference->external_id);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/quotations')
                && data_get($request->data(), 'lineItems.0.unitPrice.netAmount') === 200.0
                && data_get($request->data(), 'address.contactId') === 'lex-contact-1';
        });
    }

    public function test_push_without_customer_throws(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'customer_id' => null, 'target_qty' => '1', 'unit' => 'Stk',
        ]);

        $this->expectException(RuntimeException::class);
        app(LexofficeOrderConfirmationService::class)->push($order);
    }
}
