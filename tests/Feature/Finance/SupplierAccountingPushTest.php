<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierAccountingPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Integration\ExternalReference;
use App\Models\Supplier\Supplier;
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\SupplierContactSyncer;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Stammdaten\CollectiveContacts;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\InteractsWithPlugins;
use Tests\TestCase;

/**
 * Bestehende Lieferanten an die Buchhaltung (MVP-1526): Aktion an der
 * Lieferantenakte und Sammellauf `accounting:push-contacts --suppliers`,
 * beide nur, solange WorkDiary die Stammdaten führt; der Sammellieferant nie.
 */
final class SupplierAccountingPushTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private SupplierPushFakeSyncer $syncer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->registerPlugins($this->syncer = new SupplierPushFakeSyncer);
        $this->enablePluginFor($this->organization, SupplierPushFakeSyncer::ID);
    }

    /** @param  array<string, mixed>  $attributes */
    private function supplier(string $name, array $attributes = []): Supplier {
        return Supplier::factory()->create($attributes + ['organization_id' => $this->organization->id, 'name' => $name]);
    }

    public function test_supplier_record_offers_the_transfer_and_pushes_the_supplier(): void {
        $supplier = $this->supplier('Baustoffe Lindenau KG');
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee(route('accounting.suppliers.push', $supplier), false)
            ->assertSee('An Buchhaltung übertragen');

        $this->actingAs($admin)->from(route('suppliers.show', $supplier))
            ->post(route('accounting.suppliers.push', $supplier))
            ->assertRedirect(route('suppliers.show', $supplier))
            ->assertSessionHas('status', 'Lieferant an die Buchhaltung übertragen (ID fake-' . $supplier->id . ').');

        $this->assertSame([$supplier->id], $this->syncer->pushed);
        $this->assertSame('fake-' . $supplier->id, ExternalReference::query()
            ->forPlugin($this->organization->id, SupplierPushFakeSyncer::ID, ExternalReference::TYPE_CONTACT)
            ->forReferenceable($supplier)->value('external_id'));
    }

    public function test_collective_supplier_is_never_transferred(): void {
        $collective = app(CollectiveContacts::class)->supplier($this->organization);
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('suppliers.show', $collective))
            ->assertOk()
            ->assertDontSee(route('accounting.suppliers.push', $collective), false);
        $this->actingAs($admin)->post(route('accounting.suppliers.push', $collective))
            ->assertSessionHas('error');

        $this->assertSame([], $this->syncer->pushed);
    }

    public function test_without_master_data_lead_neither_the_button_nor_the_push_runs(): void {
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->organization);
        $supplier = $this->supplier('Baustoffe Lindenau KG');
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertDontSee(route('accounting.suppliers.push', $supplier), false);
        $this->actingAs($admin)->post(route('accounting.suppliers.push', $supplier))
            ->assertSessionHas('error', 'Übertragung fehlgeschlagen: Die Buchhaltung führt die Stammdaten — es wird nicht übertragen (Einstellung „Stammdaten-Hoheit").');

        $this->assertSame([], $this->syncer->pushed);
    }

    public function test_user_without_update_right_cannot_push(): void {
        $supplier = $this->supplier('Baustoffe Lindenau KG');

        $this->actingAs($this->orgUser())->post(route('accounting.suppliers.push', $supplier))->assertForbidden();

        $this->assertSame([], $this->syncer->pushed);
    }

    public function test_command_pushes_all_active_suppliers_with_the_option(): void {
        $first = $this->supplier('Baustoffe Lindenau KG');
        $second = $this->supplier('Holzhof Rabenau GmbH');
        $this->supplier('Altlieferant Beispiel', ['archived_at' => now()]);
        app(CollectiveContacts::class)->supplier($this->organization);

        $this->artisan('accounting:push-contacts', ['plugin' => SupplierPushFakeSyncer::ID, '--suppliers' => true, '--organization' => $this->organization->id])
            ->expectsOutput('Lieferanten-Push (' . SupplierPushFakeSyncer::ID . '): 2 übertragen, 0 übersprungen, 0 Fehler')
            ->assertExitCode(0);

        $this->assertSame([$first->id, $second->id], $this->syncer->pushed);
    }

    public function test_command_without_the_option_leaves_suppliers_alone(): void {
        $this->supplier('Baustoffe Lindenau KG');

        $this->artisan('accounting:push-contacts', ['plugin' => SupplierPushFakeSyncer::ID, '--organization' => $this->organization->id])->assertExitCode(0);

        $this->assertSame([], $this->syncer->pushed);
    }
}

/** Buchhaltungs-Plugin mit Lieferantenkontakten für den Test; wacht wie die echten über den Sammelkontakt. */
final class SupplierPushFakeSyncer extends AbstractPlugin implements SupplierContactSyncer {
    public const string ID = 'supplierpushfake';

    /** @var list<int> */
    public array $pushed = [];

    public function name(): string {
        return 'Buchhaltung Test';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return 'Test';
    }

    public function capabilities(): array {
        return [];
    }

    public function settingsSchema(): array {
        return [];
    }

    public function pushSupplierContact(Supplier $supplier): string {
        CollectiveContacts::assertPushable($supplier);
        $this->pushed[] = (int) $supplier->id;

        return 'fake-' . $supplier->id;
    }
}
