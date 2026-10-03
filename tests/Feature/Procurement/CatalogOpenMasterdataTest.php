<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogOpenMasterdataTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\CatalogItemStatus;
use App\Models\Platform\User;
use App\Models\Supplier\{Supplier, SupplierCatalogImport, SupplierCatalogItem, SupplierCatalogItemPrice, SupplierCatalogSource};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-1072: Open Masterdata — Anmeldung, Artikelabfrage, Übernahme, Preisabgleich und fälliger Lauf. */
final class CatalogOpenMasterdataTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const TOKEN_URL = 'https://omd.example.com/oauth2/token';

    private const PRODUCT_URL = 'https://omd.example.com/openmasterdata/product/bySupplierPID';

    private User $admin;

    private Supplier $supplier;

    private SupplierCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'Großhandel', 'format' => 'omd', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
            'omd_config' => [
                'token_url' => self::TOKEN_URL, 'base_url' => 'https://omd.example.com/openmasterdata',
                'grant_type' => 'password', 'client_id' => 'wd-install', 'client_secret' => '',
                'username' => 'einkauf', 'password' => 'geheim', 'customer_number' => '4711', 'customer_number_in_login' => true,
                'scope' => 'openMasterdata', 'package_mode' => 'pipe', 'customer_id' => '',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function fixture(): array {
        return (array) JsonHelper::decode((string) file_get_contents(base_path('tests/Fixtures/procurement/open-masterdata-product.json')));
    }

    /** @param array<string, mixed> $stubs */
    private function fake(array $stubs = []): FakePluginHttp {
        return FakePluginHttp::fake($stubs + [
            self::TOKEN_URL => ['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600, 'token_type' => 'Bearer'],
            self::PRODUCT_URL . '*' => $this->fixture(),
        ]);
    }

    public function test_lookup_logs_in_with_the_password_grant_and_shows_the_product(): void {
        $http = $this->fake();

        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.show', [$this->source, 'omd_by' => 'pid', 'omd_value' => 'S12345']))
            ->assertOk()
            ->assertSee('Hochwertiger Akkubohrer')
            ->assertSee('150,00')
            ->assertSee('Bedienungsanleitung')
            ->assertSee('Spannung: 18 V');

        $http->assertSent(static function (RequestInterface $request): bool {
            parse_str((string) $request->getBody(), $form);

            return str_ends_with((string) $request->getUri(), '/oauth2/token')
                && ($form['grant_type'] ?? null) === 'password'
                && ($form['username'] ?? null) === "einkauf\t4711"
                && ($form['password'] ?? null) === 'geheim'
                && ($form['scope'] ?? null) === 'openMasterdata'
                && ($form['client_id'] ?? null) === 'wd-install';
        });
        $http->assertSent(static fn (RequestInterface $request): bool => str_contains((string) $request->getUri(), '/product/bySupplierPID?supplierPid=S12345&datapackage=basic%7Cadditional%7Cprices')
            && $request->getHeaderLine('Authorization') === 'Bearer at');
    }

    public function test_adopt_creates_the_catalog_item_with_mapped_fields_once(): void {
        $this->fake();

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.omd.adopt', $this->source), ['omd_by' => 'pid', 'omd_value' => 'S12345'])
            ->assertRedirect(route('supplier-catalogs.show', [$this->source, 'q' => 'S12345']))
            ->assertSessionHas('success');

        $item = SupplierCatalogItem::query()->where('external_no', 'S12345')->firstOrFail();
        $this->assertSame(CatalogItemStatus::New, $item->status);
        $this->assertSame('Hochwertiger Akkubohrer', $item->name);
        $this->assertSame('150.0000', $item->purchase_price?->getAmount());
        $this->assertSame(100, (int) $item->price_unit_amount, 'Nettopreis je 100 Stück');
        $this->assertSame('net', $item->price_type);
        $this->assertSame('200.0000', $item->list_price?->getAmount());
        $this->assertSame('PCE', $item->unit);
        $this->assertSame('01234567890123', $item->gtin);
        $this->assertSame('MPID12345', $item->manufacturer_no);
        $this->assertSame('https://example.com/product12345', $item->product_url);
        $this->assertSame('https://example.com/product12345_main.jpg', $item->image_url);
        $this->assertSame('https://example.com/manual12345.pdf', $item->datasheet_url);
        $this->assertSame(5, (int) $item->lead_time_days);
        $this->assertSame('18 V', $item->extra_attributes['etim']['Spannung'] ?? null);
        $this->assertSame('S12349', $item->extra_attributes['omd_successor'] ?? null);
        $this->assertSame(1, SupplierCatalogItemPrice::query()->where('supplier_catalog_item_id', $item->id)->count());

        // Noch einmal übernehmen: nachführen statt verdoppeln.
        $this->actingAs($this->admin)->post(route('supplier-catalogs.omd.adopt', $this->source), ['omd_by' => 'gtin', 'omd_value' => '01234567890123']);
        $this->assertSame(1, SupplierCatalogItem::query()->count());
        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.show', [$this->source, 'omd_by' => 'pid', 'omd_value' => 'S12345']))
            ->assertSee(__('procurement.omd.lookup.update_item'));
    }

    public function test_unknown_article_and_replacement_are_reported(): void {
        $this->fake([self::PRODUCT_URL . '*' => FakePluginHttp::response(['message' => 'not found'], 404)]);
        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.show', [$this->source, 'omd_by' => 'pid', 'omd_value' => 'NOPE']))
            ->assertOk()
            ->assertSee(__('procurement.omd.error.not_found'));

        // Ab Open Masterdata 11 steht der Status im Antwortkörper.
        $this->fake([self::PRODUCT_URL . '*' => ['status' => 950] + $this->fixture()]);
        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.show', [$this->source, 'omd_by' => 'pid', 'omd_value' => 'OLD-1']))
            ->assertOk()
            ->assertSee(__('procurement.omd.lookup.alternative', ['no' => 'S12345']));

        // Fassung 1.x sendet HTTP 950 — PSR-7 lehnt den Status ab, die Anbindung meldet den Ersatz.
        $this->fake([self::PRODUCT_URL . '*' => static fn () => throw new \InvalidArgumentException('Status code must be an integer value between 1xx and 5xx.')]);
        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.show', [$this->source, 'omd_by' => 'pid', 'omd_value' => 'OLD-2']))
            ->assertOk()
            ->assertSee(__('procurement.omd.error.replaced'));
    }

    public function test_refresh_updates_prices_discontinues_missing_items_and_logs_the_run(): void {
        foreach (['S12345' => '100.0000', 'GONE' => '9.0000'] as $no => $price) {
            SupplierCatalogItem::query()->create([
                'organization_id' => $this->organization->id, 'supplier_catalog_source_id' => $this->source->id, 'supplier_id' => $this->supplier->id,
                'external_no' => $no, 'name' => $no, 'currency' => 'EUR', 'purchase_price' => $price, 'price_unit_amount' => 1,
                'status' => CatalogItemStatus::New->value, 'raw_hash' => 'seed', 'last_seen_at' => now()->subDay(),
            ]);
        }
        $this->fake(['*supplierPid=GONE*' => FakePluginHttp::response(['message' => 'not found'], 404)]);

        $this->actingAs($this->admin)->post(route('supplier-catalogs.omd.refresh', $this->source))->assertSessionHas('success');

        $item = SupplierCatalogItem::query()->where('external_no', 'S12345')->firstOrFail();
        $this->assertSame('150.0000', $item->purchase_price?->getAmount());
        $this->assertSame(100, (int) $item->price_unit_amount);
        $this->assertNull($item->description, 'der Preisabgleich fasst Texte nicht an');
        $this->assertSame(CatalogItemStatus::Discontinued, SupplierCatalogItem::query()->where('external_no', 'GONE')->firstOrFail()->status);
        $run = SupplierCatalogImport::query()->firstOrFail();
        $this->assertSame(SupplierCatalogImport::TRIGGER_MANUAL, $run->trigger);
        $this->assertSame(1, (int) $run->price_changed);
        $this->assertSame(1, (int) $run->discontinued);
    }

    public function test_due_run_refreshes_open_masterdata_sources(): void {
        $this->source->forceFill(['fetch_interval_minutes' => 60])->save();
        SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id, 'supplier_catalog_source_id' => $this->source->id, 'supplier_id' => $this->supplier->id,
            'external_no' => 'S12345', 'name' => 'Alt', 'currency' => 'EUR', 'purchase_price' => '100.0000', 'price_unit_amount' => 1,
            'status' => CatalogItemStatus::New->value, 'raw_hash' => 'seed',
        ]);
        $this->fake();

        $this->artisan('catalog:fetch-due')->assertSuccessful();

        $this->assertSame(SupplierCatalogImport::TRIGGER_SCHEDULED, SupplierCatalogImport::query()->firstOrFail()->trigger);
        $this->assertNotNull($this->source->refresh()->next_fetch_at);
        $this->assertSame('150.0000', SupplierCatalogItem::query()->firstOrFail()->purchase_price?->getAmount());
    }

    public function test_form_keeps_secrets_and_never_shows_them(): void {
        $this->actingAs($this->admin)
            ->put(route('supplier-catalogs.update', $this->source), [
                'supplier' => $this->supplier->sqid, 'name' => 'Großhandel', 'format' => 'omd',
                'omd' => ['token_url' => self::TOKEN_URL, 'base_url' => 'https://omd.example.com/openmasterdata', 'client_id' => 'wd-install-2', 'username' => 'einkauf', 'customer_number' => '4711', 'password' => '', 'client_secret' => ''],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('supplier-catalogs.show', $this->source));

        $config = (array) $this->source->refresh()->omd_config;
        $this->assertSame('wd-install-2', $config['client_id']);
        $this->assertSame('geheim', $config['password'], 'leeres Passwortfeld behält das gespeicherte');
        $this->assertFalse((bool) $config['customer_number_in_login']);

        $this->actingAs($this->admin)->get(route('supplier-catalogs.edit', $this->source))->assertOk()->assertDontSee('geheim');
        $this->actingAs($this->admin)
            ->put(route('supplier-catalogs.update', $this->source), ['supplier' => $this->supplier->sqid, 'name' => 'X', 'format' => 'omd', 'omd' => ['token_url' => 'http://10.0.0.1/token', 'base_url' => 'https://omd.example.com', 'client_id' => 'c']])
            ->assertSessionHasErrors(['omd.token_url', 'omd.username']);
    }
}
