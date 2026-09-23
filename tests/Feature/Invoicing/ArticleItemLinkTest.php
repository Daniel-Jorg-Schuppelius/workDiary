<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleItemLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Models\{Article, Invoice};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Artikelbezug auf Belegpositionen (Feature 140): Picker im Dialog,
 * Sqid-Persistenz, org-gescopte Prüfung, Mitnahme Angebot → Rechnung.
 */
class ArticleItemLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
        $this->article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'A-100',
            'name' => 'Wartungspauschale',
            'base_unit' => 'Psch.',
            'default_sale_price' => '250.00',
        ]);
    }

    private function draft(): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-' . fake()->unique()->numerify('####'),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_item_dialog_renders_article_picker_with_prefill_map(): void {
        $invoice = $this->draft();

        $this->actingAs($this->admin)
            ->get(route('invoices.items.create', $invoice))
            ->assertOk()
            ->assertSee('name="article_id"', false)
            ->assertSee('x-data="articleItemPicker"', false)
            ->assertSee($this->article->sqid)
            ->assertSee('A-100 · Wartungspauschale');
    }

    public function test_invoice_item_persists_article_by_sqid(): void {
        $invoice = $this->draft();

        $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), [
                'article_id' => $this->article->sqid,
                'description' => 'Wartungspauschale',
                'quantity' => '1',
                'unit' => 'Psch.',
                'unit_price' => '250.00',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $item = $invoice->items()->firstOrFail();
        $this->assertSame($this->article->id, (int) $item->article_id);

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('A-100');
    }

    public function test_foreign_article_is_rejected(): void {
        $other = Organization::factory()->create();
        $foreign = Article::factory()->create(['organization_id' => $other->id]);
        $invoice = $this->draft();

        $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), [
                'article_id' => $foreign->sqid,
                'description' => 'Fremd',
                'quantity' => '1',
                'unit_price' => '1.00',
            ])
            ->assertSessionHasErrors('article_id');

        $this->assertSame(0, $invoice->items()->count());
    }

    public function test_quote_item_article_survives_acceptance_and_conversion(): void {
        $user = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $service = app(QuoteService::class);
        $quote = $service->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [
            ['article_id' => $this->article->id, 'description' => 'Wartungspauschale', 'quantity' => 1, 'unit_price' => 250, 'tax_rate' => '19.00'],
            ['description' => 'Anfahrt', 'quantity' => 1, 'unit_price' => 40, 'tax_rate' => '19.00'],
        ], $user);

        $quote = $service->approve($quote, $user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $user);
        $quote = $service->accept($quote, null, $token);
        $this->assertSame($this->article->id, (int) data_get($quote->decision_snapshot, 'items.0.article_id'));

        $invoice = $service->convertToInvoice($quote, $user);
        $items = $invoice->items()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame($this->article->id, (int) $items->firstOrFail()->article_id);
        $this->assertNull($items->last()?->article_id);
    }

    public function test_quote_item_form_accepts_article_sqid(): void {
        $quote = app(QuoteService::class)->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [], $this->admin);

        $this->actingAs($this->admin)
            ->post(route('quotes.items.store', $quote), [
                'article_id' => $this->article->sqid,
                'description' => 'Wartungspauschale',
                'quantity' => '1',
                'unit' => 'Psch.',
                'unit_price' => '250.00',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($this->article->id, (int) $quote->items()->firstOrFail()->article_id);
    }

    // ── Kupferzuschlag als eigene Position (MVP-804) ───────────────────

    private function copperArticle(): Article {
        // (2,00 − 1,50) × 0,043 kg = 0,0215 €/Einheit — dieselbe Rechnung wie im OCI-Warenkorb.
        \App\Models\MetalQuotation::query()->create([
            'organization_id' => $this->organization->id,
            'metal' => 'CU', 'price_per_kg' => '2', 'quoted_at' => now()->toDateString(),
        ]);

        return Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'NYM-CU',
            'name' => 'Kabel NYM-J 3x1,5',
            'base_unit' => 'm',
            'copper_weight' => '0.0430',
            'copper_base_price' => '150.0000',
        ]);
    }

    public function test_invoice_item_with_copper_article_adds_the_surcharge_position(): void {
        $invoice = $this->draft();
        $cable = $this->copperArticle();

        $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), [
                'article_id' => $cable->sqid,
                'description' => 'Kabel NYM-J 3x1,5',
                'quantity' => '100',
                'unit' => 'm',
                'unit_price' => '1.90',
                'tax_rate' => '19',
                'add_copper_surcharge' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $items = $invoice->items()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame(__('b2b_catalog.copper_surcharge_position', ['number' => 'NYM-CU']), $items[1]->description);
        $this->assertSame('100.000', (string) $items[1]->quantity);
        $this->assertSame('0.0215', $items[1]->unit_price?->getAmount());
        $this->assertNull($items[1]->article_id);
    }

    public function test_surcharge_is_skipped_when_unchecked_or_without_copper_data(): void {
        $invoice = $this->draft();
        $cable = $this->copperArticle();

        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), [
            'article_id' => $cable->sqid, 'description' => 'Kabel', 'quantity' => '10', 'unit' => 'm', 'unit_price' => '1.90',
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('invoices.items.store', $invoice), [
            'article_id' => $this->article->sqid, 'description' => 'Wartung', 'quantity' => '1', 'unit' => 'Psch.', 'unit_price' => '250.00', 'add_copper_surcharge' => '1',
        ])->assertRedirect();

        $this->assertSame(2, $invoice->items()->count());
    }

    public function test_quote_item_with_copper_article_adds_the_surcharge_position(): void {
        $quote = app(QuoteService::class)->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [], $this->admin);
        $cable = $this->copperArticle();

        $this->actingAs($this->admin)->post(route('quotes.items.store', $quote), [
            'article_id' => $cable->sqid, 'description' => 'Kabel', 'quantity' => '50', 'unit' => 'm', 'unit_price' => '1.90', 'add_copper_surcharge' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        // Angebotspreise haben zwei Nachkommastellen: Zuschlag als Gesamtbetrag, 50 × 0,0215 = 1,08 €.
        $surcharge = $quote->items()->get()->sortBy('position')->last();
        $this->assertSame(2, $quote->items()->count());
        $this->assertSame('1.08', $surcharge?->unit_price?->getAmount());
        $this->assertSame(1.0, (float) $surcharge?->quantity);
    }
}
