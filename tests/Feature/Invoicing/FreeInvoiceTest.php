<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Services\Invoicing\{InvoiceIssueException, InvoiceIssueService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Freie Rechnungen aus Artikeln, Material und Fertigung (Feature 160,
 * MVP-856/857/859): Entwurf ohne Quellen, Doppelklick-Schutz, Sperre leerer
 * Ausstellung/Versendung, Artikel-/Varianten-/Freitextpositionen mit
 * eingefrorenen Belegwerten und den Summen aus der Feature-Doku.
 */
final class FreeInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Möbel Muster', 'currency' => 'EUR']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function manualPayload(array $extra = []): array {
        return ['customer_id' => $this->customer->sqid, 'content' => 'manual', 'payment_terms_days' => 14] + $extra;
    }

    private function draft(): Invoice {
        $this->actingAs($this->admin)->post(route('invoices.store'), $this->manualPayload());

        return Invoice::query()->latest('id')->firstOrFail();
    }

    public function test_manual_draft_is_created_without_sources_and_a_double_click_returns_the_same_draft(): void {
        $this->actingAs($this->admin)->get(route('invoices.create'))->assertOk()
            ->assertSee('name="draft_token"', false)
            ->assertSee(__('invoicing.free.option.manual'));

        $token = 'demo-token-1';
        $first = $this->actingAs($this->admin)->post(route('invoices.store'), $this->manualPayload(['draft_token' => $token]));
        $first->assertRedirect();
        $invoice = Invoice::query()->latest('id')->firstOrFail();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(Invoice::TYPE_INVOICE, $invoice->type);
        $this->assertStringStartsWith('R', (string) $invoice->number);
        $this->assertSame(0, $invoice->items()->count());
        $this->assertSame($this->customer->id, $invoice->customer_id);

        $this->actingAs($this->admin)->post(route('invoices.store'), $this->manualPayload(['draft_token' => $token]))
            ->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame(1, Invoice::query()->count(), 'Der Doppelklick darf keine zweite Rechnung erzeugen.');
    }

    public function test_manual_draft_respects_external_billing_sovereignty_and_foreign_project(): void {
        $this->customer->update(['billing_mode' => 'lexoffice']);
        $this->actingAs($this->admin)->post(route('invoices.store'), $this->manualPayload())->assertSessionHasErrors('customer_id');
        $this->assertSame(0, Invoice::query()->count());

        $this->customer->update(['billing_mode' => 'workdiary']);
        $other = Organization::factory()->create();
        $foreignProject = Project::factory()->create(['organization_id' => $other->id, 'customer_id' => Customer::factory()->create(['organization_id' => $other->id])->id]);
        $this->actingAs($this->admin)->post(route('invoices.store'), $this->manualPayload(['project_id' => $foreignProject->sqid]))->assertSessionHasErrors('project_id');
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_empty_draft_can_be_completed_later_but_never_issued_or_sent(): void {
        $invoice = $this->draft();

        $this->actingAs($this->admin)->post(route('invoices.issue', $invoice))->assertSessionHas('error');
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);

        try {
            app(InvoiceIssueService::class)->issue($invoice);
            $this->fail('Ein leerer Entwurf darf nicht ausgestellt werden.');
        } catch (InvoiceIssueException $e) {
            $this->assertSame(InvoiceIssueException::REASON_EMPTY, $e->reason);
        }
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);

        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()->assertSee(__('invoicing.free.hint.empty_draft'));

        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), ['description' => 'Montage pauschal', 'quantity' => '1', 'unit' => 'pausch.', 'unit_price' => '120.00'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('invoices.issue', $invoice))->assertSessionMissing('error');
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->refresh()->status);
    }

    public function test_article_material_and_flat_service_combine_to_the_documented_totals_with_frozen_snapshots(): void {
        $shelf = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'ART-REGAL', 'name' => 'Regal Eiche', 'type' => ArticleType::Finished->value, 'base_unit' => 'Stk', 'default_sale_price' => '250.0000', 'currency' => 'EUR', 'sellable' => true]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $shelf->id, 'sku' => 'REGAL-180', 'name' => '180 cm', 'sale_price' => '250.0000', 'currency' => 'EUR']);
        $material = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'MAT-KANTE', 'name' => 'Kantenleiste', 'type' => ArticleType::Consumable->value, 'base_unit' => 'm', 'default_sale_price' => '8.0000', 'currency' => 'EUR', 'sellable' => true]);
        $invoice = $this->draft();

        $this->actingAs($this->admin)->get(route('invoices.items.create', $invoice))->assertOk()
            ->assertSee('name="article_variant_id"', false)
            ->assertSee(__('invoicing.free.hint.no_stock_movement'));

        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), ['article_id' => $shelf->sqid, 'article_variant_id' => $variant->sqid, 'description' => 'Regal Eiche – 180 cm', 'quantity' => '2', 'unit' => 'Stk', 'unit_price' => '250.00', 'add_copper_surcharge' => '0'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), ['article_id' => $material->sqid, 'description' => 'Kantenleiste', 'quantity' => '10', 'unit' => 'm', 'unit_price' => '8.00', 'add_copper_surcharge' => '0'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), ['description' => 'Montage pauschal', 'quantity' => '1', 'unit' => 'pausch.', 'unit_price' => '120.00'])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('700.00', $invoice->subtotal?->getAmount());
        $this->assertSame('133.00', $invoice->tax_amount?->getAmount());
        $this->assertSame('833.00', $invoice->total?->getAmount());

        /** @var InvoiceItem $shelfItem */
        $shelfItem = $invoice->items()->where('article_id', $shelf->id)->firstOrFail();
        $this->assertSame($variant->id, $shelfItem->article_variant_id);
        $this->assertSame('REGAL-180', $shelfItem->article_number_snapshot);
        $this->assertSame('MAT-KANTE', $invoice->items()->where('article_id', $material->id)->value('article_number_snapshot'));

        // Stammänderungen deuten den Beleg nicht um.
        $shelf->update(['name' => 'Regal Buche', 'number' => 'ART-NEU', 'default_sale_price' => '999.0000']);
        $variant->update(['sku' => 'NEU-1', 'sale_price' => '999.0000']);
        $shelfItem->refresh();
        $this->assertSame('Regal Eiche – 180 cm', $shelfItem->description);
        $this->assertSame('REGAL-180', $shelfItem->article_number_snapshot);
        $this->assertSame('250.0000', $shelfItem->unit_price?->getAmount());

        // Keine künstlichen Quellposten oder Lagerbewegungen.
        $this->assertSame(0, \App\Models\Time\TimeEntry::query()->count());
        $this->assertSame(0, \App\Models\Material\MaterialUsage::query()->count());
        $this->assertSame(0, \App\Models\Inventory\StockMovement::query()->count());

        // Verkäuferdaten für die E-Rechnung (Preflight), Leitweg-ID am Beleg.
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Möbelwerkstatt Muster', 'street' => 'Werkstattweg 3', 'zip' => '50667', 'city' => 'Köln', 'country' => 'DE',
            'vat_id' => 'DE123456789', 'tax_number' => '201/987/65432', 'contact_name' => 'Anna Muster', 'contact_email' => 'anna@muster.example',
            'contact_phone' => '+49 221 55555', 'iban' => 'DE89370400440532013000', 'bic' => 'COBADEFFXXX', 'account_holder' => 'Anna Muster', 'payment_terms_days' => 14,
        ]]]);
        app()->instance('currentOrganization', $this->organization->fresh());
        $invoice->update(['buyer_reference' => '04011000-12345-67']);

        $this->actingAs($this->admin)->post(route('invoices.issue', $invoice))->assertSessionMissing('error');
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->refresh()->status);
        $this->actingAs($this->admin)->get(route('invoices.pdf', $invoice))->assertOk();
        $xml = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice))->assertOk()->getContent();
        $this->assertStringContainsString('833.00', (string) $xml);
    }

    public function test_variant_must_belong_to_the_article_and_foreign_variant_is_rejected(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'sellable' => true, 'default_sale_price' => '10.0000']);
        $otherArticle = Article::factory()->create(['organization_id' => $this->organization->id, 'sellable' => true]);
        $wrongVariant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $otherArticle->id, 'sku' => 'X-1']);
        $other = Organization::factory()->create();
        $foreignVariant = ArticleVariant::factory()->create(['organization_id' => $other->id, 'article_id' => Article::factory()->create(['organization_id' => $other->id])->id, 'sku' => 'F-1']);
        $invoice = $this->draft();

        $payload = ['article_id' => $article->sqid, 'description' => 'Test', 'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '10.00', 'add_copper_surcharge' => '0'];
        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), $payload + ['article_variant_id' => $wrongVariant->sqid])->assertSessionHasErrors('article_variant_id');
        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), $payload + ['article_variant_id' => $foreignVariant->sqid])->assertSessionHasErrors('article_variant_id');
        $this->assertSame(0, $invoice->items()->count());

        // Fehlender Preis wird nicht still zu null.
        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), ['description' => 'Ohne Preis', 'quantity' => '1', 'unit' => 'Stk'])->assertSessionHasErrors('unit_price');
        // Ausdrücklicher Nullpreis bleibt als Gratisposition möglich.
        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), ['description' => 'Gratis', 'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->items()->count());
    }

    public function test_existing_time_material_and_proforma_paths_keep_working(): void {
        $this->actingAs($this->admin)->post(route('invoices.store'), ['customer_id' => $this->customer->sqid, 'content' => 'proforma'])->assertRedirect();
        $this->assertSame(Invoice::TYPE_PROFORMA, Invoice::query()->latest('id')->firstOrFail()->type);
        $this->actingAs($this->admin)->post(route('invoices.store'), ['customer_id' => $this->customer->sqid, 'content' => 'service', 'from' => '2026-09-01', 'to' => '2026-09-30'])->assertSessionHasErrors('customer_id');
        $this->actingAs($this->admin)->post(route('invoices.store'), ['customer_id' => $this->customer->sqid, 'content' => 'material', 'from' => '2026-09-01', 'to' => '2026-09-30'])->assertSessionHasErrors('customer_id');
        $this->assertSame(1, Invoice::query()->count());
    }
}
