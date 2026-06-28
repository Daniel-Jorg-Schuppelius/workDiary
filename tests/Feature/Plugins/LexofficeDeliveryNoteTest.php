<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDeliveryNoteTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\{Article, ArticleVariant, Customer, ExternalReference, StockDelivery, Warehouse};
use App\Plugins\Lexoffice\{LexofficeDeliveryNoteService, LexofficePlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lexoffice-Lieferschein (Feature 045/047): Push einer Auslieferung als
 * Lexoffice-Lieferschein und Pull des verknüpften Belegs — HTTP über Http::fake.
 */
final class LexofficeDeliveryNoteTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'kunde@example.com',
        ]);
    }

    private function makeDelivery(): StockDelivery {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        return StockDelivery::query()->create([
            'organization_id' => $this->organization->id,
            'article_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $this->customer->id,
            'quantity' => '3.0000',
            'unit' => 'Stk',
            'sku_snapshot' => 'SKU-9',
            'name_snapshot' => 'Bürostuhl',
            'unit_price_snapshot' => '120.0000',
            'currency' => 'EUR',
            'stock_status' => 'delivered',
            'facturation_status' => DeliveryFacturationStatus::Pending->value,
            'facturation_target' => 'lexoffice',
            'delivered_at' => Carbon::parse('2026-06-26 10:00:00'),
        ]);
    }

    private function seedContactReference(): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->getKey(),
            'external_id' => 'lex-contact-1',
            'synced_at' => now(),
        ]);
    }

    public function test_push_creates_lexoffice_delivery_note(): void {
        $this->seedContactReference();
        $delivery = $this->makeDelivery();

        Http::fake([
            'https://api.lexoffice.io/v1/delivery-notes*' => Http::response(['id' => 'lex-dn-1'], 201),
        ]);

        $reference = app(LexofficeDeliveryNoteService::class)->push($delivery);

        $this->assertSame(LexofficeDeliveryNoteService::EXT_TYPE_DELIVERY_NOTE, $reference->external_type);
        $this->assertSame('lex-dn-1', $reference->external_id);
        $this->assertSame($delivery->getMorphClass(), $reference->referenceable_type);

        $fresh = $delivery->fresh();
        $this->assertSame(DeliveryFacturationStatus::HandedOver, $fresh->facturation_status);
        $this->assertSame('lex-dn-1', $fresh->external_id);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/delivery-notes')) {
                return false;
            }
            $body = $request->data();

            return data_get($body, 'address.contactId') === 'lex-contact-1'
                && data_get($body, 'lineItems.0.quantity') === 3.0
                && str_contains((string) data_get($body, 'lineItems.0.name'), 'Bürostuhl');
        });
    }

    public function test_push_resolves_contact_via_email_search(): void {
        $delivery = $this->makeDelivery();

        Http::fake([
            'https://api.lexoffice.io/v1/contacts*' => Http::response(['content' => [['id' => 'lex-contact-2']]], 200),
            'https://api.lexoffice.io/v1/delivery-notes*' => Http::response(['id' => 'lex-dn-2'], 201),
        ]);

        $reference = app(LexofficeDeliveryNoteService::class)->push($delivery);

        $this->assertSame('lex-dn-2', $reference->external_id);
        // Kontakt-Referenz wurde aus der Suche angelegt.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'lex-contact-2',
        ]);
    }

    public function test_push_failure_marks_delivery_failed(): void {
        $this->seedContactReference();
        $delivery = $this->makeDelivery();

        Http::fake([
            'https://api.lexoffice.io/v1/delivery-notes*' => Http::response(['message' => 'bad'], 400),
        ]);

        try {
            app(LexofficeDeliveryNoteService::class)->push($delivery);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->assertSame(DeliveryFacturationStatus::Failed, $delivery->fresh()->facturation_status);
    }

    public function test_pull_reads_linked_delivery_note(): void {
        $delivery = $this->makeDelivery();
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficeDeliveryNoteService::EXT_TYPE_DELIVERY_NOTE,
            'referenceable_type' => $delivery->getMorphClass(),
            'referenceable_id' => $delivery->getKey(),
            'external_id' => 'lex-dn-9',
            'synced_at' => now(),
        ]);

        Http::fake([
            'https://api.lexoffice.io/v1/delivery-notes/lex-dn-9' => Http::response(['id' => 'lex-dn-9', 'voucherStatus' => 'open'], 200),
        ]);

        $result = app(LexofficeDeliveryNoteService::class)->pull($delivery);

        $this->assertSame('lex-dn-9', $result['id']);
        $this->assertSame('open', $result['voucherStatus']);
    }
}
