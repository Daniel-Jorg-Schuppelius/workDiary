<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseStockHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Enums\User\Permission;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleLicenseBatch, ResaleLicenseKey, ResaleLicenseProduct, ResaleLicenseUnit};
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, SubjectDataExporter};
use App\Services\Reselling\License\LicenseStockService;
use App\Services\UI\DateRangeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1024: Oberfläche, Rechte und Geheimnisschutz des Lizenzbestands (A7, A11, A12, A14). */
final class LicenseStockHttpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LicenseStockService $stock;

    private ResaleLicenseProduct $product;

    private ResaleLicenseBatch $batch;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->stock = app(LicenseStockService::class);
        $admin = $this->orgAdmin();
        $this->product = $this->stock->saveProduct($this->organization, null, ['name' => 'LANCOM Advanced VPN', 'key_labels' => ['Seriennummer', 'Aktivierung'], 'reorder_level' => 1], $admin);
        $this->batch = $this->stock->createBatch($this->product, ['reference' => 'P-2026-01', 'purchased_on' => '2026-09-01', 'quantity' => 3], null, $admin);
        foreach ([1, 2] as $position) {
            $this->stock->saveKeys($this->unit($position), ['key_1' => "GEHEIM-SN-{$position}", 'key_2' => "GEHEIM-AK-{$position}"], [], null, $admin);
        }
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function unit(int $position): ResaleLicenseUnit {
        return $this->batch->units()->where('position', $position)->sole();
    }

    /** @param  list<Permission>  $permissions */
    private function user(array $permissions): User {
        $user = $this->orgUser();
        $user->givePermissionTo(array_map(static fn (Permission $p): string => $p->value, $permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_pages_show_stock_but_never_keys_without_the_key_right(): void {
        $manager = $this->user([Permission::ResellingView, Permission::ResellingManage]);

        // A12: ein anderer Header-Zeitraum ändert den aktuellen Bestand nicht.
        $this->actingAs($manager);
        app(DateRangeContext::class)->set('custom', '2020-01-01', '2020-01-31');
        $index = $this->get(route('finance.resale.licenses.index'))->assertOk()
            ->assertSee('LANCOM Advanced VPN')->assertSee('P-2026-01 · 2/3')->assertSee(__('resale.license.title'));
        $batch = $this->get(route('finance.resale.licenses.batches.show', $this->batch))->assertOk()->assertSee('#3');
        $keysDialog = $this->get(route('finance.resale.licenses.units.keys.edit', $this->unit(1)))->assertOk()->assertSee(__('resale.license.hint.key_present'));
        foreach ([$index, $batch, $keysDialog] as $response) {
            $this->assertStringNotContainsString('GEHEIM', (string) $response->getContent());
        }
        $this->get(route('finance.resale.licenses.units.keys.show', $this->unit(1)))->assertForbidden();
        $this->get(route('finance.resale.licenses.index', ['reorder' => 1]))->assertOk()->assertSee(__('resale.license.empty.products'));

        // Dublette beim Pflegen: Fehler am Feld, kein Schlüssel im Old-Input.
        $this->from(route('finance.resale.licenses.batches.show', $this->batch))
            ->put(route('finance.resale.licenses.units.keys.update', $this->unit(3)), ['license_keys' => ['key_1' => 'GEHEIM-SN-1']])
            ->assertSessionHasErrors('license_keys.key_1');
        $this->assertArrayNotHasKey('license_keys', (array) session()->getOldInput());
        $json = $this->putJson(route('finance.resale.licenses.units.keys.update', $this->unit(3)), ['license_keys' => ['key_1' => 'GEHEIM-SN-2']])->assertUnprocessable();
        $this->assertStringNotContainsString('GEHEIM', (string) $json->getContent());

        $viewer = $this->user([Permission::ResellingView]);
        $this->actingAs($viewer)->get(route('finance.resale.licenses.units.keys.edit', $this->unit(1)))->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.licenses.sell.store'), [])->assertForbidden();
    }

    public function test_the_key_right_reveals_plain_text_with_no_store_and_an_audit_without_values(): void {
        $keyViewer = $this->user([Permission::ResellingView, Permission::ResellingKeysView]);

        $response = $this->actingAs($keyViewer)->get(route('finance.resale.licenses.units.keys.show', $this->unit(2)))->assertOk()
            ->assertSee('GEHEIM-SN-2')->assertSee('GEHEIM-AK-2');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('data-copy-text', (string) $response->getContent());
        $log = AuditLog::query()->where('event', 'resale_license.keys_viewed')->sole();
        $this->assertSame($keyViewer->id, $log->user_id);
        $this->assertStringNotContainsString('GEHEIM', (string) json_encode($log->changes));
        $this->assertStringNotContainsString('GEHEIM', (string) json_encode(ResaleLicenseKey::query()->get()->toArray()));
    }

    public function test_repeated_sales_create_one_sale_and_foreign_tenants_are_refused(): void {
        $manager = $this->user([Permission::ResellingView, Permission::ResellingManage]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kunde Nord']);
        $sale = ['unit_id' => $this->unit(1)->sqid, 'customer_id' => $customer->sqid, 'sold_on' => '2026-09-20', 'invoice_reference' => 'RE-7', 'token' => str_repeat('a', 32)];

        $this->actingAs($manager)->get(route('finance.resale.licenses.sell.create', ['product' => $this->product->sqid]))->assertOk()
            ->assertSee($this->unit(1)->label())->assertDontSee($this->unit(3)->label());
        // A7: Doppelklick und Retry mit demselben Token — ein Verkauf, ein Abgang.
        $this->actingAs($manager)->post(route('finance.resale.licenses.sell.store'), $sale)->assertRedirect()->assertSessionHas('success');
        $this->actingAs($manager)->post(route('finance.resale.licenses.sell.store'), $sale)->assertRedirect()->assertSessionHas('success');
        $this->actingAs($manager)->postJson(route('finance.resale.licenses.sell.store'), ['token' => str_repeat('b', 32)] + $sale)->assertUnprocessable()->assertJsonValidationErrors('license');
        $this->assertSame(1, ResaleLicenseAssignment::query()->count());
        $this->assertSame(1, $this->stock->productStock($this->organization, collect([$this->product]))[$this->product->id]['sold']);

        // Kundenakte zeigt den Verkauf, nie den Schlüssel.
        $customerPage = $this->actingAs($manager)->get(route('customers.show', $customer))->assertOk()->assertSee($this->unit(1)->label());
        $this->assertStringNotContainsString('GEHEIM', (string) $customerPage->getContent());

        // A11: fremder Mandant — weder sein Paket noch sein Kunde sind erreichbar.
        $other = Organization::factory()->create();
        $foreignCustomer = Customer::factory()->create(['organization_id' => $other->id]);
        $this->actingAs($manager)->postJson(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unit(2)->sqid, 'customer_id' => $foreignCustomer->sqid, 'token' => str_repeat('c', 32)] + $sale)
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $foreignBatch = ResaleLicenseBatch::query()->withoutGlobalScopes()->create([
            'organization_id' => $other->id, 'product_id' => ResaleLicenseProduct::query()->withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'Fremd', 'key_roles' => [['code' => 'key_1', 'label' => 'K']]])->id,
            'reference' => 'F-1', 'purchased_on' => '2026-09-01', 'quantity' => 1, 'key_roles' => [['code' => 'key_1', 'label' => 'K']], 'key_count' => 1,
        ]);
        $this->actingAs($manager)->get(route('finance.resale.licenses.batches.show', $foreignBatch->sqid))->assertNotFound();

        // Auskunft zum Kunden zählt den Verkauf — ohne Schlüssel.
        DataProtectionPermissions::seedOrganization($this->organization);
        $officer = $this->orgUser();
        $officer->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);
        $dsr = app(DataSubjectRequestService::class)->open($this->organization, DataSubjectRequestType::Access, 'Kunde Nord', 'Auskunft', null, $officer);
        $exporter = app(SubjectDataExporter::class);
        $payload = $exporter->build($dsr, DataSubjectKind::Customer, $exporter->resolve(DataSubjectKind::Customer, (int) $this->organization->id, (int) $customer->id));
        $families = collect(array_column($payload['sections'], null, 'key')['documents']['families'] ?? []);
        $this->assertSame(1, $families->firstWhere('table', 'resale_license_assignments')['count'] ?? null);
        $this->assertStringNotContainsString('GEHEIM', (string) json_encode($payload));
    }

    public function test_every_dialog_renders_and_the_forms_validate(): void {
        // A14: Dialoge, Pflichtfelder und Korrekturwege über die Oberfläche.
        $manager = $this->user([Permission::ResellingView, Permission::ResellingManage]);
        $this->actingAs($manager);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->get(route('finance.resale.licenses.products.create'))->assertOk()->assertSee('name="key_labels[0]"', false);
        $this->postJson(route('finance.resale.licenses.products.store'), ['name' => '', 'key_labels' => ['']])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('finance.resale.licenses.products.store'), ['name' => 'Leer', 'key_labels' => ['', '']])->assertUnprocessable()->assertJsonValidationErrors('key_labels');
        $this->post(route('finance.resale.licenses.products.store'), ['name' => 'Firewall-Lizenz', 'key_labels' => ['Lizenzcode'], 'reorder_level' => 0])->assertRedirect();
        // Artikel aus dem Katalog (MVP-1025): gespeichert wird der Katalogschlüssel, fremde Artikel scheitern.
        $article = \App\Models\Article\Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Office 2024 Home']);
        $this->get(route('finance.resale.licenses.products.create'))->assertSee('value="art:' . $article->sqid . '"', false);
        $this->post(route('finance.resale.licenses.products.store'), ['name' => 'Office 2024', 'key_labels' => ['Produktschlüssel'], 'article' => 'art:' . $article->sqid])->assertRedirect();
        $this->assertSame('art:' . $article->id, ResaleLicenseProduct::query()->where('name', 'Office 2024')->value('article_ref'));
        $foreign = \App\Models\Article\Article::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->postJson(route('finance.resale.licenses.products.store'), ['name' => 'Fremd', 'key_labels' => ['Code'], 'article' => 'art:' . $foreign->sqid])
            ->assertUnprocessable()->assertJsonValidationErrors('article');
        $this->get(route('finance.resale.licenses.products.edit', $this->product))->assertOk()->assertSee('Seriennummer');

        $this->get(route('finance.resale.licenses.batches.create', ['product' => $this->product->sqid]))->assertOk();
        $this->postJson(route('finance.resale.licenses.batches.store'), ['product_id' => $this->product->sqid, 'reference' => 'P-2026-01', 'purchased_on' => '2026-09-02', 'quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['reference', 'quantity']);
        $this->post(route('finance.resale.licenses.batches.store'), ['product_id' => $this->product->sqid, 'reference' => 'P-2026-02', 'purchased_on' => '2026-09-02', 'quantity' => 2, 'supplier_name' => 'Distributor AG'])
            ->assertRedirect();
        $second = ResaleLicenseBatch::query()->where('reference', 'P-2026-02')->sole();
        $this->assertSame(2, $second->units()->count());
        $this->delete(route('finance.resale.licenses.batches.destroy', $second))->assertRedirect();
        $this->assertNull($second->fresh());
        $this->get(route('finance.resale.licenses.batches.import.create', $this->batch))->assertOk();

        $this->post(route('finance.resale.licenses.sell.store'), ['unit_id' => $this->unit(1)->sqid, 'customer_id' => $customer->sqid, 'sold_on' => '2026-09-20', 'token' => str_repeat('d', 32)]);
        $assignment = ResaleLicenseAssignment::query()->sole();
        $this->get(route('finance.resale.licenses.sell.create'))->assertOk()->assertSee('optgroup', false);
        $this->get(route('finance.resale.licenses.assignments.reassign.create', $assignment))->assertOk();
        $this->postJson(route('finance.resale.licenses.assignments.reassign.store', $assignment), ['customer_id' => $other->sqid])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->post(route('finance.resale.licenses.assignments.reassign.store', $assignment), ['customer_id' => $other->sqid, 'reason' => 'Verwechselt'])->assertRedirect()->assertSessionHas('success');
        $current = ResaleLicenseAssignment::query()->whereNull('ended_at')->sole();
        $this->get(route('finance.resale.licenses.assignments.return.create', $current))->assertOk();
        $this->post(route('finance.resale.licenses.assignments.return.store', $current), ['reason' => 'Storniert'])->assertRedirect();
        $this->get(route('finance.resale.licenses.units.unblock.create', $this->unit(1)))->assertOk()->assertSee('Storniert');
        $this->postJson(route('finance.resale.licenses.units.unblock.store', $this->unit(1)), ['reason' => 'Geprüft'])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->post(route('finance.resale.licenses.units.unblock.store', $this->unit(1)), ['reason' => 'Geprüft', 'confirmed' => '1'])->assertRedirect();
        $this->get(route('finance.resale.licenses.units.block.create', $this->unit(2)))->assertOk();
        $this->post(route('finance.resale.licenses.units.block.store', $this->unit(2)), ['reason' => 'Schlüssel ungültig'])->assertRedirect();
        $this->get(route('finance.resale.licenses.batches.show', $this->batch))->assertOk()
            ->assertSee(__('resale.license.end.corrected'))->assertSee(__('resale.license.end.returned'))->assertSee('Schlüssel ungültig');
        $this->get(route('finance.resale.licenses.index', ['status' => 'blocked']))->assertOk()->assertSee($this->unit(2)->label());
        $this->get(route('finance.resale.licenses.index', ['q' => 'gibt-es-nicht']))->assertOk()->assertSee(__('resale.license.empty.units'));
    }

    public function test_csv_upload_previews_and_applies_only_after_confirmation(): void {
        $manager = $this->user([Permission::ResellingView, Permission::ResellingManage]);
        $file = UploadedFile::fake()->createWithContent('schluessel.csv', "position;key_1;key_2\n3;NEU-SN-3;NEU-AK-3\n");

        $preview = $this->actingAs($manager)->post(route('finance.resale.licenses.batches.import.preview', $this->batch), ['file' => $file])->assertOk()
            ->assertSee(trans_choice('resale.license.import.confirm', 2, ['count' => 2]));
        $this->assertStringNotContainsString('NEU-SN-3', (string) $preview->getContent());
        $this->assertSame(4, ResaleLicenseKey::query()->count());
        preg_match('/name="token" value="([A-Za-z0-9]{32})"/', (string) $preview->getContent(), $match);

        $this->actingAs($manager)->post(route('finance.resale.licenses.batches.import.store', $this->batch), ['token' => $match[1] ?? ''])
            ->assertRedirect(route('finance.resale.licenses.batches.show', $this->batch))->assertSessionHas('success');
        $this->assertSame(6, ResaleLicenseKey::query()->count());
        $this->actingAs($manager)->get(route('finance.resale.licenses.batches.template', $this->batch))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
