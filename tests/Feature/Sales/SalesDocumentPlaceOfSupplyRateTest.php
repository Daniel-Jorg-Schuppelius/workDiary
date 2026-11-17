<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesDocumentPlaceOfSupplyRateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Article\ArticleType;
use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\PlaceOfSupplyKind;
use App\Enums\Project\ProjectStatus;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Sales\{OrderConfirmation, OrderConfirmationItem, Quote, QuoteItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1705: Angebot und Auftragsbestätigung rechnen Positionen ohne eigenen
 * Steuersatz mit dem Satz nach dem Leistungsort ihrer Leistungsartikel, ohne
 * Leistungsartikel nach der Leistung des Projekts — wie die Rechnung, die
 * daraus entsteht. Fall: Unternehmen in Österreich mit USt-IdNr.; eine
 * Grundstücksleistung im Inland trägt 19 %, eine allgemeine Leistung geht
 * als Reverse Charge mit 0 %.
 */
final class SalesDocumentPlaceOfSupplyRateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['country' => 'DE', 'vat_id' => 'DE136695976']]]);
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
        $this->customer = Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bauträger Alpenblick',
            'company' => 'Bauträger Alpenblick GmbH',
            'currency' => 'EUR',
            'country' => 'AT',
            'vat_id' => 'ATU13585627',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_quote_with_a_property_article_carries_german_vat(): void {
        $quote = $this->quote([$this->article(PlaceOfSupplyKind::Property)]);

        $this->assertSame('38.00', $quote->documentTotals()['tax_amount']->getAmount(), '2 × 100 € zu 19 %: Grundstück im Inland.');
    }

    /** Gegenprobe: eine allgemeine Leistung an das Unternehmen im EU-Ausland bleibt Reverse Charge (0 %). */
    public function test_quote_with_a_general_article_stays_reverse_charge(): void {
        $quote = $this->quote([$this->article(PlaceOfSupplyKind::General)]);

        $this->assertSame('0.00', $quote->documentTotals()['tax_amount']->getAmount());
    }

    /** Ohne Leistungsartikel zählt die Leistung des Projekts (Abrechnungsregel). */
    public function test_quote_without_service_article_follows_the_project_rule(): void {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'place_of_supply_kind' => PlaceOfSupplyKind::Property->value,
        ]);

        $withProject = $this->quote([null], ['project_id' => $project->id]);
        $withoutProject = $this->quote([null]);

        $this->assertSame('38.00', $withProject->documentTotals()['tax_amount']->getAmount());
        $this->assertSame('0.00', $withoutProject->documentTotals()['tax_amount']->getAmount(), 'Gegenprobe: Vorgabe der Organisation.');
    }

    /** Eine Position mit eigenem Satz behält ihn; nur der Rückfall folgt dem Leistungsort. */
    public function test_own_line_rate_stays(): void {
        $quote = $this->quote([$this->article(PlaceOfSupplyKind::General)], [], '7.00');

        $this->assertSame('14.00', $quote->documentTotals()['tax_amount']->getAmount());
    }

    public function test_order_confirmation_follows_the_same_place_of_supply(): void {
        $property = $this->confirmation($this->article(PlaceOfSupplyKind::Property));
        $general = $this->confirmation($this->article(PlaceOfSupplyKind::General));

        $this->assertSame('38.00', $property->documentTotals()['tax_amount']->getAmount());
        $this->assertSame('0.00', $general->documentTotals()['tax_amount']->getAmount(), 'Gegenprobe: allgemeine Leistung, Reverse Charge.');
    }

    /**
     * @param  list<Article|null>  $articles
     * @param  array<string, mixed>  $attributes
     */
    private function quote(array $articles, array $attributes = [], ?string $rate = null): Quote {
        $quote = Quote::factory()->create($attributes + ['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        foreach ($articles as $index => $article) {
            QuoteItem::factory()->create([
                'organization_id' => $this->organization->id,
                'quote_id' => $quote->id,
                'article_id' => $article?->id,
                'position' => $index + 1,
                'description' => 'Fliesenarbeiten Bad',
                'quantity' => '2.00',
                'unit_price' => '100.00',
                'tax_rate' => $rate,
            ]);
        }

        return $quote->refresh()->load('items');
    }

    private function confirmation(Article $article): OrderConfirmation {
        $confirmation = OrderConfirmation::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        OrderConfirmationItem::factory()->create([
            'organization_id' => $this->organization->id,
            'order_confirmation_id' => $confirmation->id,
            'article_id' => $article->id,
            'description' => 'Fliesenarbeiten Bad',
            'quantity' => '2.0000',
            'unit_price' => '100.00',
            'tax_rate' => null,
        ]);

        return $confirmation->refresh()->load('items');
    }

    private function article(PlaceOfSupplyKind $kind): Article {
        return Article::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => ArticleType::Service->value,
            'base_unit' => 'Std.',
            'place_of_supply_kind' => $kind->value,
        ]);
    }
}
