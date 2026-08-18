<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderGaebExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Models\{Article, ArticleVariant, Supplier, User, Warehouse};
use App\Services\Gaeb\GaebOrderExportService;
use App\Services\Procurement\PurchaseOrderService;
use ERechnungToolkit\Enums\GaebPhase;
use ERechnungToolkit\Validators\GaebSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Bestellungen als GAEB-Handelsdatei (X96): der dritte Bestellweg neben
 * OCI-Punchout und openTRANS — und der, den Baustoffhändler tatsächlich fahren.
 */
final class PurchaseOrderGaebExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        // Die eigene Anschrift stammt aus den E-Rechnungs-Stammdaten; ohne sie
        // weist die Handelsdatei den Besteller nicht aus.
        $this->organization->update(['settings' => array_replace((array) $this->organization->settings, [
            'einvoice' => ['seller_name' => 'Bau GmbH', 'street' => 'Schillerplatz 1', 'zip' => '99423', 'city' => 'Weimar'],
        ])]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Baustoff Meier',
            'address_street' => 'Coudraystr. 4',
            'address_zip' => '99423',
            'address_city' => 'Weimar',
            'tax_number' => '205/5711/0041',
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'purchasable' => true,
            'base_unit' => 'Stk',
        ]);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
    }

    private function orderWithLine(): \App\Models\PurchaseOrder {
        $order = app(PurchaseOrderService::class)->createDraft($this->organization, $this->supplier, $this->warehouse);

        $this->actingAs($this->admin)->post(route('purchase-orders.lines.add', $order), [
            'article' => $this->article->sqid,
            'qty' => '12',
            'unit_price' => '98.50',
        ])->assertRedirect();

        return $order->fresh(['lines', 'supplier', 'organization']);
    }

    /** Die erzeugte Datei ist schemavalide und trägt die Handelsangaben. */
    public function test_export_produces_a_valid_trade_document(): void {
        $result = app(GaebOrderExportService::class)->export($this->orderWithLine());

        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
        $this->assertStringContainsString('<Order>', $result['content']);
        $this->assertStringContainsString('<DP>96</DP>', $result['content']);
        $this->assertStringContainsString('<TaxNo>205/5711/0041</TaxNo>', $result['content']);
        $this->assertStringEndsWith('.x96', $result['filename']);
    }

    /**
     * Der Lieferant führt keine Handelsregisternummer — Einzelunternehmer haben
     * ohnehin keine. Das Element steht trotzdem da, nur leer.
     */
    public function test_missing_register_number_stays_valid(): void {
        $result = app(GaebOrderExportService::class)->export($this->orderWithLine());

        $this->assertStringContainsString('<RegNo/>', $result['content']);
        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
    }

    /** Die Preisanfrage fragt ohne Preis; erst das Angebot des Händlers nennt einen. */
    public function test_price_inquiry_carries_no_prices(): void {
        $result = app(GaebOrderExportService::class)->export($this->orderWithLine(), GaebPhase::PriceInquiry);

        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
        $this->assertStringNotContainsString('<NetPrice>', $result['content']);
    }

    /**
     * Fehlt die Artikelnummer des Lieferanten, findet dessen Warenwirtschaft
     * die Ware nicht — das wird benannt, nicht verschwiegen.
     */
    public function test_missing_supplier_article_number_is_reported(): void {
        $order = $this->orderWithLine();
        $order->lines->first()?->update(['supplier_sku' => null]);

        $result = app(GaebOrderExportService::class)->export($order->fresh(['lines', 'supplier', 'organization']));

        $this->assertNotSame([], $result['losses']);
        $this->assertStringContainsString('Artikelnummer', implode(' ', $result['losses']));
    }
}
