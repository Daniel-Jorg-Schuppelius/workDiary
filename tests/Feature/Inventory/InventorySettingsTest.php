<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventorySettingsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{ScanAction, ValuationMethod};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockValuation, StockValuationLayer, Warehouse};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Inventory\{FifoValuationService, InventoryValuationManager, ScanActionService, SerialNumberGenerator, ValuationMethodResolver, ValuationService};
use App\Services\Procurement\{GoodsReceiptService, PurchaseOrderService};
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1579/1580: Bewertungsverfahren je Organisation und Artikel mit
 * Bestandsübertrag, Seriennummernschema, MHD-Pflicht, Reservierungszeitpunkt.
 */
final class InventorySettingsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private Article $article;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default', 'sku' => 'SKU-SET',
        ]);
    }

    public function test_settings_dialog_saves_method_and_backfills_layers(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '2.50');

        $this->actingAs($this->admin)->get(route('inventory.settings.edit'))->assertOk();
        $this->actingAs($this->admin)->put(route('inventory.settings.update'), [
            'valuation_method' => 'fifo', 'reservation_timing' => 'start',
        ])->assertRedirect(route('inventory.stock'))->assertSessionHasNoErrors();

        $organization = $this->organization->fresh();
        $this->assertSame('fifo', data_get($organization->settings, 'inventory.valuation_method'));
        $this->assertSame(ValuationMethod::Fifo, app(ValuationMethodResolver::class)->methodFor($organization));
        $this->assertInstanceOf(FifoValuationService::class, app(InventoryValuationManager::class)->for($organization));
        $this->assertSame('start', data_get($organization->settings, 'inventory.reservation_timing'));

        $layer = StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->sole();
        $this->assertSame('10.0000', $layer->qty_remaining);
        $this->assertSame('2.5000', $layer->unit_cost?->getAmount());
    }

    public function test_switch_back_to_average_is_blocked_while_layers_hold_stock(): void {
        $this->organization->update(['settings' => ['inventory' => ['valuation_method' => 'fifo']]]);
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '4', '3');

        $this->actingAs($this->admin)->put(route('inventory.settings.update'), [
            'valuation_method' => 'moving_average', 'reservation_timing' => 'release',
        ])->assertSessionHasErrors('valuation_method');
        $this->assertSame('fifo', data_get($this->organization->fresh()->settings, 'inventory.valuation_method'));

        // Ohne Schichtbestand geht der Wechsel; der stehengebliebene Durchschnittsbestand wird genullt.
        app(FifoValuationService::class)->issue($this->variant, $this->warehouse, '4');
        StockValuation::query()->create([
            'organization_id' => $this->organization->id, 'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id, 'qty_on_hand' => '7', 'avg_cost' => '1', 'currency' => 'EUR',
        ]);
        $this->actingAs($this->admin)->put(route('inventory.settings.update'), [
            'valuation_method' => 'moving_average', 'reservation_timing' => 'release',
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.0000', app(ValuationService::class)->onHand($this->variant, $this->warehouse));
    }

    public function test_settings_require_configure_permission(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('inventory.settings.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('inventory.settings.update'), [
            'valuation_method' => 'fifo', 'reservation_timing' => 'start',
        ])->assertForbidden();
    }

    public function test_legacy_top_level_setting_still_resolves(): void {
        $this->organization->update(['settings' => ['valuation_method' => 'fefo']]);

        $this->assertSame(ValuationMethod::Fefo, app(ValuationMethodResolver::class)->methodFor($this->organization->fresh()));
    }

    public function test_article_form_saves_valuation_method_and_serial_scheme(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '6', '1.50');

        $this->actingAs($this->admin)->put(route('articles.update', $this->article), [
            'name' => $this->article->name, 'type' => $this->article->type->value, 'base_unit' => 'Stk',
            'status' => $this->article->status->value,
            'valuation_method' => 'fefo',
            'serial_scheme' => ['prefix' => 'WD', 'padding' => '4', 'check_digit' => '0'],
        ])->assertSessionHasNoErrors();

        $article = $this->article->fresh();
        $this->assertSame('fefo', $article->valuation_method);
        $this->assertSame(['prefix' => 'WD', 'padding' => 4, 'check_digit' => false], $article->serial_scheme);
        $this->assertSame('WD-0001', app(SerialNumberGenerator::class)->generateFor($this->variant->fresh()));
        // Der Wechsel am Artikel überträgt dessen Bestand in eine Anfangsschicht.
        $this->assertSame('6.0000', StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->sole()->qty_remaining);

        // Leeres Präfix räumt das Schema ab, leeres Verfahren heißt „wie Organisation“ — hier gesperrt, weil Schichten Bestand tragen.
        $this->actingAs($this->admin)->put(route('articles.update', $this->article), [
            'name' => $this->article->name, 'type' => $this->article->type->value, 'base_unit' => 'Stk',
            'status' => $this->article->status->value,
            'valuation_method' => '',
            'serial_scheme' => ['prefix' => '', 'padding' => '', 'check_digit' => '0'],
        ])->assertSessionHasErrors('valuation_method');
        $this->assertSame('fefo', $this->article->fresh()->valuation_method);
    }

    public function test_shelf_life_required_blocks_receipt_without_best_before(): void {
        $this->article->update(['shelf_life_required' => true]);
        $variant = $this->variant->fresh();
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, Supplier::factory()->create(['organization_id' => $this->organization->id]), $this->warehouse);
        $line = $orders->addLine($po, $this->article, '5', ['variant' => $variant, 'unit_price' => '2']);
        $orders->submit($po);
        $receipts = app(GoodsReceiptService::class);

        foreach ([[null, null], ['L-1', null]] as [$lot, $bestBefore]) {
            try {
                $receipts->receive($line, '2', lotNo: $lot, bestBefore: $bestBefore);
                $this->fail('Wareneingang ohne Mindesthaltbarkeitsdatum wurde nicht abgewiesen.');
            } catch (RuntimeException $e) {
                $this->assertSame(__('inventory.error.shelf_life_required'), $e->getMessage());
            }
        }
        $this->assertSame('0.0000', $line->fresh()->received_qty?->getNumericValue());

        $receipts->receive($line, '2', lotNo: 'L-1', bestBefore: '2031-01-31');
        // Die bekannte Charge bringt ihr Datum mit.
        $receipts->receive($line, '1', lotNo: 'L-1');
        $this->assertSame('3.0000', $line->fresh()->received_qty?->getNumericValue());

        // Manueller Zugang und Scan-Eingang kennen kein Datum.
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid, 'variant' => $variant->sqid, 'movement' => 'receipt',
            'qty' => '1', 'ownership' => 'own',
        ])->assertSessionHas('error', __('inventory.error.shelf_life_manual_receipt'));

        $this->expectExceptionMessage(__('inventory.error.shelf_life_manual_receipt'));
        app(ScanActionService::class)->book('SKU-SET', ScanAction::Receipt, $this->warehouse, '1');
    }

    public function test_reservation_timing_is_read_from_registry_setting(): void {
        $this->assertSame('release', Setting::get('inventory.reservation_timing', 'release'));

        $this->actingAs($this->admin)->put(route('inventory.settings.update'), [
            'valuation_method' => 'moving_average', 'reservation_timing' => 'confirmed',
        ])->assertSessionHasErrors('reservation_timing');

        $this->actingAs($this->admin)->put(route('inventory.settings.update'), [
            'valuation_method' => 'moving_average', 'reservation_timing' => 'start',
        ])->assertSessionHasNoErrors();
        app()->instance('currentOrganization', $this->organization->fresh());
        $this->assertSame('start', Setting::get('inventory.reservation_timing', 'release'));
    }

    /** Reste 9a: das Bewertungsverfahren schreibt nur der Fachdienst — die generischen Wege nehmen es nicht an. */
    public function test_generic_settings_paths_cannot_write_the_valuation_method(): void {
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '4', '3');
        $this->organization->update(['settings' => ['inventory' => ['valuation_method' => 'fifo']]]);

        $this->actingAs($this->admin)->put(route('admin.organizations.update', $this->organization), [
            'name' => $this->organization->name, 'plan' => $this->organization->plan, 'locale' => 'de', 'timezone' => 'Europe/Berlin', 'is_active' => 1,
            'settings' => ['inventory' => ['valuation_method' => 'moving_average', 'reservation_timing' => 'start']],
        ])->assertSessionHasNoErrors();
        $settings = $this->organization->fresh()->settings;
        $this->assertSame('fifo', data_get($settings, 'inventory.valuation_method'));
        $this->assertSame('start', data_get($settings, 'inventory.reservation_timing'));

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'inventory.valuation_method'), [
            'scope' => 'organization', 'value' => 'moving_average',
        ])->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('admin.settings.reset', 'inventory.valuation_method'), ['scope' => 'organization'])
            ->assertSessionHasErrors();
        $this->assertSame('fifo', data_get($this->organization->fresh()->settings, 'inventory.valuation_method'));

        $this->assertArrayNotHasKey(
            'settings.inventory.valuation_method',
            app(\App\Settings\SettingsRegistry::class)->formRulesForScope(\App\Settings\SettingScope::Organization),
        );
    }

    /** MVP-1583: führt eine Warenwirtschaft die Artikelnummern, sind SKU und GTIN im Artikelstamm gesperrt. */
    public function test_article_numbers_are_locked_when_a_merchandise_system_leads(): void {
        $authority = app(\App\Services\Numbering\NumberAuthority::class);
        $this->assertNull($authority->articleNumbersLockedBy($this->organization));

        \App\Models\Platform\PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => 'jtl_wawi', 'enabled' => true, 'settings' => []]);
        $this->assertArrayHasKey('jtl_wawi', $authority->sourceOptions($this->organization, \App\Enums\Numbering\NumberScope::Article));
        \App\Models\Numbering\NumberFormat::query()->updateOrCreate(
            ['organization_id' => $this->organization->id, 'scope' => \App\Enums\Numbering\NumberScope::Article->value],
            ['source' => 'jtl_wawi'],
        );
        $this->assertNotNull($authority->articleNumbersLockedBy($this->organization));

        $payload = [
            'name' => $this->article->name, 'type' => $this->article->type->value, 'base_unit' => 'Stk',
            'status' => $this->article->status->value,
        ];
        $number = $this->article->number;
        $this->actingAs($this->admin)->put(route('articles.update', $this->article), $payload + ['number' => 'EIGENE-1'])
            ->assertSessionHasErrors('number');
        $this->actingAs($this->admin)->put(route('articles.update', $this->article), $payload + ['gtin' => '4006381333931'])
            ->assertSessionHasErrors('gtin');
        $this->assertSame($number, $this->article->fresh()->number);
        $this->actingAs($this->admin)->put(route('articles.update', $this->article), $payload)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('articles.edit', $this->article) . '?dialog=1')
            ->assertOk()->assertSee(__('article.field.numbers_locked_hint', ['program' => $authority->sourceLabel('jtl_wawi')]));

        // Neue Artikel und Varianten bekommen keine eigene Nummer; der Abgleich des Programms schreibt weiter.
        $articles = app(\App\Services\Article\ArticleService::class);
        $created = $articles->createArticle($this->organization, ['name' => 'Aus Wawi', 'type' => $this->article->type->value, 'base_unit' => 'Stk', 'status' => $this->article->status->value]);
        $this->assertNull($created->number);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $created->id, 'option_signature' => 'x', 'sku' => null]);
        $this->assertNull($articles->assignVariantSku($variant)->sku);
        $created->update(['number' => 'JTL-4711', 'gtin' => '4006381333931']);
        $this->assertSame('JTL-4711', $created->fresh()->number);

        // Ist das Programm für die Organisation aus, führt wieder workDiary.
        \App\Models\Platform\PluginSetting::query()->where('plugin_id', 'jtl_wawi')->update(['enabled' => false]);
        $this->assertNull(app(\App\Services\Numbering\NumberAuthority::class)->articleNumbersLockedBy($this->organization));
    }
}
