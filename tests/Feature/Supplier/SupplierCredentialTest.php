<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Supplier;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\Supplier\CredentialStatus;
use App\Models\{Document, IncomingEInvoice, Organization, PurchaseOrder, Supplier, User};
use App\Models\Notification\NotificationRule;
use App\Models\Supplier\{SupplierCredential, SupplierCredentialType};
use App\Services\Supplier\SupplierCredentialService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Subunternehmer-Pflichtnachweise (Feature 117, MVP-606).
 *
 * Der gefährliche Fall ist nicht das fehlende, sondern das **abgelaufene**
 * Dokument: Es ist da, sieht vollständig aus und trägt trotzdem nicht mehr.
 */
class SupplierCredentialTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->org->id]);

        // Der Katalog liegt installationsweit vor (organization_id NULL) und
        // wird wie die Prüfmittel-Profile je Test geseedet.
        $this->seed(\Database\Seeders\SupplierCredentialCatalogSeeder::class);

        // Für die Tests nur EIN Pflichttyp, damit die Ampel eindeutig ist.
        SupplierCredentialType::query()->update(['is_required_default' => false]);
        $this->type()->update(['is_required_default' => true]);
    }

    private function type(string $code = 'freistellung_48b'): SupplierCredentialType {
        return SupplierCredentialType::query()->whereNull('organization_id')->where('code', $code)->sole();
    }

    private function credential(?string $validUntil, string $code = 'freistellung_48b'): SupplierCredential {
        return SupplierCredential::query()->create([
            'organization_id' => $this->org->id,
            'supplier_id' => $this->supplier->id,
            'supplier_credential_type_id' => $this->type($code)->id,
            'issuer' => 'Finanzamt Musterstadt',
            'issued_on' => now()->subYear()->toDateString(),
            'valid_until' => $validUntil,
        ]);
    }

    public function test_catalog_is_seeded_installation_wide(): void {
        $this->assertGreaterThanOrEqual(5, SupplierCredentialType::query()->whereNull('organization_id')->count());
    }

    public function test_missing_credential_is_red(): void {
        $this->assertSame(CredentialStatus::Missing, app(SupplierCredentialService::class)->overallStatus($this->supplier));
    }

    public function test_valid_credential_is_green(): void {
        $this->credential(now()->addYears(2)->toDateString());

        $this->assertSame(CredentialStatus::Ok, app(SupplierCredentialService::class)->overallStatus($this->supplier));
    }

    public function test_credential_within_the_warning_window_is_amber(): void {
        $this->credential(now()->addDays(20)->toDateString());

        $this->assertSame(CredentialStatus::Expiring, app(SupplierCredentialService::class)->overallStatus($this->supplier));
    }

    /** Das abgelaufene Dokument ist genauso schwer wie das fehlende. */
    public function test_expired_credential_is_red_like_a_missing_one(): void {
        $this->credential(now()->subDay()->toDateString());

        $status = app(SupplierCredentialService::class)->overallStatus($this->supplier);
        $this->assertSame(CredentialStatus::Expired, $status);
        $this->assertTrue($status->isBlocking());
    }

    public function test_credential_without_an_end_date_counts_as_unlimited(): void {
        $this->credential(null);

        $this->assertSame(CredentialStatus::Ok, app(SupplierCredentialService::class)->overallStatus($this->supplier));
    }

    // ── Sperrwirkung ────────────────────────────────────────────────────

    /** Standard ist WARNUNG — eine Sperre ab Werk legt Betriebe still. */
    public function test_blocking_is_off_by_default(): void {
        $this->assertFalse(app(SupplierCredentialService::class)->blockingEnabled($this->supplier));
        $this->assertSame([], app(SupplierCredentialService::class)->blockingReasons($this->supplier));
    }

    public function test_enabled_blocking_reports_the_missing_records(): void {
        Setting::set('procurement.credential_blocking', true, SettingScope::Organization, $this->org);

        $reasons = app(SupplierCredentialService::class)->blockingReasons($this->supplier->refresh());

        $this->assertNotEmpty($reasons);
    }

    /** Nur Typen mit `block` sperren — eine SOKA-Warnung hält den Bau nicht auf. */
    public function test_warn_only_types_never_block(): void {
        Setting::set('procurement.credential_blocking', true, SettingScope::Organization, $this->org);
        SupplierCredentialType::query()->update(['is_required_default' => false]);
        $soka = $this->type('soka_bau');
        $soka->update(['is_required_default' => true]);

        $this->assertSame([], app(SupplierCredentialService::class)->blockingReasons($this->supplier->refresh()));
    }

    public function test_purchase_order_submission_is_blocked_when_enabled(): void {
        Setting::set('procurement.credential_blocking', true, SettingScope::Organization, $this->org);
        $warehouse = \App\Models\Warehouse::create([
            'organization_id' => $this->org->id,
            'code' => 'HL',
            'name' => 'Hauptlager',
        ]);
        $order = PurchaseOrder::create([
            'organization_id' => $this->org->id,
            'number' => 'BE-6001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => \App\Enums\Procurement\PurchaseOrderStatus::Draft->value,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->admin)
            ->post(route('purchase-orders.submit', $order))
            ->assertSessionHas('error');
    }

    public function test_scanner_warns_about_an_expiring_credential(): void {
        \Illuminate\Support\Facades\Notification::fake();
        NotificationRule::factory()->forEvent(NotificationEvent::SupplierCredentialExpiring)->create([
            'organization_id' => $this->org->id,
            'channels' => [NotificationChannel::InApp->value],
            'recipient_user_ids' => [$this->admin->id],
        ]);
        $this->credential(now()->addDays(10)->toDateString());

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::SupplierCredentialExpiring->value,
        ]);
    }

    public function test_index_shows_the_traffic_light(): void {
        $this->credential(now()->subDay()->toDateString());

        $response = $this->actingAs($this->admin)->get(route('suppliers.credentials.index'));

        $response->assertOk();
        $rows = $response->viewData('rows');
        $this->assertSame(CredentialStatus::Expired, $rows->first()['status']);
    }

    public function test_endpoint_stores_a_credential(): void {
        $this->actingAs($this->admin)->post(route('suppliers.credentials.store', $this->supplier), [
            'supplier_credential_type_id' => $this->type()->sqid,
            'issuer' => 'Finanzamt Musterstadt',
            'valid_until' => now()->addYears(3)->toDateString(),
        ])->assertRedirect();

        $credential = SupplierCredential::query()->sole();
        $this->assertSame((int) $this->supplier->id, (int) $credential->supplier_id);
        $this->assertSame((int) $this->admin->id, (int) $credential->checked_by);
    }

    /** Ein Typ einer FREMDEN Organisation darf nicht hinterlegt werden. */
    public function test_foreign_organization_type_is_refused(): void {
        $foreign = Organization::factory()->create();
        $foreignType = SupplierCredentialType::query()->create([
            'organization_id' => $foreign->id,
            'code' => 'fremd',
            'name' => 'Fremder Typ',
        ]);

        $this->actingAs($this->admin)->post(route('suppliers.credentials.store', $this->supplier), [
            'supplier_credential_type_id' => $foreignType->sqid,
        ])->assertStatus(422);
    }

    public function test_missing_reasons_are_reported_even_without_the_blocking_switch(): void {
        // Die Sperre greift an der Bestellung; bei der Rechnungsfreigabe wäre
        // sie zu spät. Gemeldet werden muss der fehlende Nachweis trotzdem.
        $service = app(SupplierCredentialService::class);

        $this->assertSame([], $service->blockingReasons($this->supplier));
        $this->assertNotSame([], $service->missingReasons($this->supplier));
    }

    public function test_payment_release_warns_about_missing_credentials(): void {
        $supplier = $this->supplier;
        $document = Document::factory()->create(['organization_id' => $this->org->id]);
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', 'beleg'),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoice::STATUS_APPROVED,
            'seller_name' => $supplier->name,
        ]);

        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'payment_released'])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(IncomingEInvoice::STATUS_PAYMENT_RELEASED, $incoming->fresh()?->status);
    }

    public function test_complete_credentials_release_without_a_warning(): void {
        $supplier = $this->supplier;
        foreach (SupplierCredentialType::query()->get() as $type) {
            SupplierCredential::query()->create([
                'organization_id' => $this->org->id,
                'supplier_id' => $supplier->id,
                'supplier_credential_type_id' => $type->id,
                'valid_until' => now()->addYear()->toDateString(),
            ]);
        }

        $document = Document::factory()->create(['organization_id' => $this->org->id]);
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', 'beleg-2'),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoice::STATUS_APPROVED,
            'seller_name' => $supplier->name,
        ]);

        $this->actingAs($this->admin)
            ->post(route('finance.incoming-invoices.decide', $incoming), ['decision' => 'payment_released'])
            ->assertRedirect()
            ->assertSessionMissing('warning');
    }
}
