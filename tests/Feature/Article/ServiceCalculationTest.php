<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceCalculationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Article;

use App\Enums\Article\{ArticleType, CostKind};
use App\Models\Article\{Article, WageGroup};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Article\ServiceCalculationService;
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1055: Kalkulation von Leistungen — Mittellohn aus Lohngruppen,
 * Zuschläge je Kostenart, kalkulierter Preis und Lohnanteil, Schnappschuss an
 * der Angebotsposition, Deckungsbeitrag und Zuschlagsverteilung.
 */
class ServiceCalculationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Article $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Kalkulation Test']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->put(route('articles.calculation-scheme.update'), [
            'wage_related_percent' => '80',
            'wage_ancillary_amount' => '2.00',
            'markups' => [
                'labour' => ['site_overhead_percent' => '10', 'general_overhead_percent' => '10', 'risk_profit_percent' => '5'],
                'material' => ['site_overhead_percent' => '5', 'general_overhead_percent' => '10', 'risk_profit_percent' => '5'],
                'equipment' => ['site_overhead_percent' => '0', 'general_overhead_percent' => '0', 'risk_profit_percent' => '0'],
                'other' => ['site_overhead_percent' => '0', 'general_overhead_percent' => '0', 'risk_profit_percent' => '0'],
                'subcontract' => ['site_overhead_percent' => '0', 'general_overhead_percent' => '0', 'risk_profit_percent' => '0'],
            ],
        ])->assertRedirect(route('articles.calculation-scheme.edit'));
        $this->post(route('articles.wage-groups.store'), ['name' => 'Meister', 'hourly_wage_amount' => '30.00', 'headcount' => 1, 'is_active' => 1])->assertRedirect();
        $this->post(route('articles.wage-groups.store'), ['name' => 'Geselle', 'hourly_wage_amount' => '20.00', 'headcount' => 3, 'is_active' => 1])->assertRedirect();

        $paint = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Wandfarbe',
            'type' => ArticleType::Consumable->value,
            'purchasable' => true,
            'default_purchase_price' => '4.0000',
        ]);
        $this->service = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Wand streichen',
            'type' => ArticleType::Service->value,
            'base_unit' => 'm²',
            'sellable' => true,
        ]);
        $this->post(route('articles.cost-approaches.store', $this->service), ['cost_kind' => 'labour', 'minutes' => '12', 'quantity' => '1'])->assertRedirect();
        $this->post(route('articles.cost-approaches.store', $this->service), ['cost_kind' => 'material', 'component_article_id' => $paint->sqid, 'quantity' => '0.25', 'unit' => 'l'])->assertRedirect();
    }

    public function test_scheme_and_approaches_yield_price_and_labour_share(): void {
        $calculation = app(ServiceCalculationService::class);
        $scheme = $calculation->scheme($this->organization->fresh());

        // Mittellohn (30 × 1 + 20 × 3) / 4 = 22,50; × 1,8 + 2,00 = 42,50 €/h.
        $this->assertSame('22.50', $calculation->averageWage($scheme)->getAmount());
        $this->assertSame('42.5000', $calculation->labourHourCost($scheme)->getAmount());

        $result = $calculation->calculate($this->service->fresh());
        $this->assertNotNull($result);
        // Lohn 0,2 h × 42,50 = 8,50 → +25 % = 10,63; Material 0,25 × 4,00 = 1,00 → +20 % = 1,20.
        $this->assertSame('10.63', $result->priceOf(CostKind::Labour)?->getAmount());
        $this->assertSame('1.20', $result->priceOf(CostKind::Material)?->getAmount());
        $this->assertSame('11.83', $result->price->getAmount());
        $this->assertSame('9.5000', $result->cost->getAmount());
        $this->assertSame('89.86', $result->labourShare?->getNumericValue());
        $this->assertSame(12.0, $result->labourMinutes);
    }

    public function test_price_can_be_adopted_and_tab_renders(): void {
        $this->get(route('articles.calculation', $this->service))->assertOk()->assertSee('11,83');

        $this->post(route('articles.calculation.adopt-price', $this->service))->assertRedirect();

        $this->assertSame('11.83', $this->service->fresh()->default_sale_price?->withScale(2)->getAmount());
        $this->get(route('articles.calculation-scheme.edit'))->assertOk()->assertSee('Geselle');
    }

    public function test_quote_line_keeps_calculation_snapshot_and_shows_contribution(): void {
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Privat', 'currency' => 'EUR', 'created_by' => $this->admin->id]);
        $quote = app(QuoteService::class)->create(['customer_id' => $customer->id], [
            ['article_id' => $this->service->id, 'description' => 'Wand streichen', 'quantity' => '10', 'unit' => 'm²', 'unit_price' => '15.00'],
        ], $this->admin);
        $item = $quote->items->first();

        $this->assertSame('9.5000', $item->unit_cost_amount?->getAmount());
        $this->assertSame('11.83', $item->calculation['price'] ?? null);
        $this->assertSame('89.86', $item->labour_share_percent?->getNumericValue());

        $contribution = $quote->fresh('items')->contribution();
        $this->assertSame('95.00', $contribution['cost']->getAmount());
        $this->assertSame('55.00', $contribution['margin']->getAmount());

        // Spätere Änderungen am Artikel ändern das Angebot nicht.
        $this->service->costApproaches()->delete();
        $this->assertSame('9.5000', $quote->fresh('items')->items->first()->unit_cost_amount?->getAmount());
    }

    public function test_markup_is_distributed_to_unit_prices_of_a_title(): void {
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Privat', 'currency' => 'EUR', 'created_by' => $this->admin->id]);
        $quote = app(QuoteService::class)->create(['customer_id' => $customer->id], [
            ['description' => 'Vorab', 'quantity' => '1', 'unit_price' => '100.00'],
            ['line_kind' => 'title', 'description' => 'Bad', 'quantity' => '0', 'unit_price' => '0'],
            ['description' => 'Fliesen', 'quantity' => '1', 'unit_price' => '200.00'],
        ], $this->admin);
        $title = $quote->items->firstWhere('description', 'Bad');

        $this->post(route('quotes.markup', $quote), ['markup_percent' => '10', 'title_id' => $title->sqid])->assertRedirect();

        $prices = $quote->fresh('items')->items->pluck('unit_price')->map(fn ($p) => $p?->getAmount())->all();
        $this->assertSame(['100.00', '0.00', '220.00'], $prices);
        $this->assertSame('320.00', $quote->fresh()->subtotal?->getAmount());
    }

    public function test_economics_dimension_compares_quote_calculation_with_actuals(): void {
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Privat', 'currency' => 'EUR', 'created_by' => $this->admin->id]);
        $project = \App\Models\Project\Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $customer->id, 'project_id' => $project->id], [
            ['article_id' => $this->service->id, 'description' => 'Wand streichen', 'quantity' => '10', 'unit_price' => '15.00'],
        ], $this->admin);
        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->fresh(), $this->admin);
        $quotes->accept($quote->fresh());

        $result = app(\App\Services\Sales\Reporting\QuoteCalculationEconomicsDimension::class)
            ->build(\Carbon\CarbonImmutable::parse('2026-01-01'), \Carbon\CarbonImmutable::parse('2026-12-31'), (int) $project->id);

        $this->assertSame(1, $result['calculatedLines']);
        $this->assertSame(120.0, $result['planned']['minutes']);
        $this->assertSame(85.0, $result['planned']['costs']['labour']);
        $this->assertSame(95.0, $result['planned']['cost']);
        $this->assertSame(150.0, $result['planned']['revenue']);
    }

    public function test_wage_group_from_other_organization_is_rejected(): void {
        $foreign = WageGroup::query()->withoutGlobalScopes()->create([
            'organization_id' => \App\Models\Platform\Organization::factory()->create()->id,
            'name' => 'Fremd',
            'hourly_wage_amount' => '99.00',
        ]);

        $this->post(route('articles.cost-approaches.store', $this->service), ['cost_kind' => 'labour', 'minutes' => '5', 'quantity' => '1', 'wage_group_id' => $foreign->sqid])
            ->assertSessionHasErrors('wage_group_id');
    }
}
