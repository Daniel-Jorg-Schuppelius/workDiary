<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntityCockpitGapsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Cockpit;

use App\Enums\AssetFinance\AssetFinanceKind;
use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\Claims\ClaimStatus;
use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Enums\User\Permission;
use App\Enums\Warranty\{WarrantyBasis, WarrantySide, WarrantyStatus};
use App\Models\Asset\Asset;
use App\Models\AssetFinance\{AssetFinanceContract, AssetFinanceContractAsset};
use App\Models\Calendar\AppointmentRequest;
use App\Models\Claims\ClaimCase;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Supplier\{Supplier, SupplierCatalogSource};
use App\Models\Warranty\WarrantyPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Lieferanten-360°, Asset- und Lead-Cockpit (MVP-1617, F9-02/F9-03). */
class EntityCockpitGapsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    public function test_supplier_cockpit_shows_catalogs_and_scorecard_with_inventory_right(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $catalog = SupplierCatalogSource::query()->create(['organization_id' => $this->organization->id, 'supplier_id' => $supplier->id, 'name' => 'Zephyr-Preisliste']);

        $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Zephyr-Preisliste')
            ->assertSee(route('supplier-catalogs.show', $catalog), false)
            ->assertSee(__('cockpit.supplier_catalogs.title'));

        $viewer = $this->member([Permission::SupplierViewAny, Permission::SupplierView]);
        $this->actingAs($viewer)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertDontSee('Zephyr-Preisliste');
    }

    public function test_scorecard_panel_renders_the_performance_of_the_last_twelve_months(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $panel = app(\App\Services\Procurement\Cockpit\SupplierScorecardCockpitPanel::class);

        $this->assertTrue($panel->supports($supplier));
        $this->assertFalse($panel->supports(Asset::factory()->create(['organization_id' => $this->organization->id])));
        // Ohne Bestellungen, Reklamationen und Preise gibt es keine Kennzahl — kein leeres Panel.
        $this->assertNull($panel->render($supplier, $this->admin));
    }

    public function test_asset_cockpit_shows_tickets_leasing_claims_and_warranties(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'asset_id' => $asset->id, 'ticket_no' => 'ST-ZEPHYR', 'title' => 'Lüfter laut']);
        $contract = AssetFinanceContract::query()->create([
            'organization_id' => $this->organization->id, 'number' => 'LV-ZEPHYR', 'kind' => AssetFinanceKind::OperatingLease->value,
            'partner_name' => 'Musterleasing GmbH', 'starts_on' => '2026-01-01', 'ends_on' => '2029-12-31',
        ]);
        AssetFinanceContractAsset::query()->create(['organization_id' => $this->organization->id, 'asset_finance_contract_id' => $contract->id, 'asset_id' => $asset->id]);
        ClaimCase::query()->create(['organization_id' => $this->organization->id, 'number' => 'RK-ZEPHYR', 'title' => 'Display defekt', 'reported_at' => now(), 'asset_id' => $asset->id]);
        ClaimCase::query()->create(['organization_id' => $this->organization->id, 'number' => 'RK-ERLEDIGT', 'title' => 'Alt', 'reported_at' => now(), 'asset_id' => $asset->id, 'status' => ClaimStatus::Closed->value]);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'asset_id' => $asset->id]);
        WarrantyPeriod::query()->create([
            'organization_id' => $this->organization->id, 'side' => WarrantySide::Owed, 'basis' => WarrantyBasis::Bgb5Years,
            'starts_on' => '2026-01-01', 'ends_on' => '2031-01-01', 'diary_entry_id' => $entry->id, 'trade' => 'Montage Zephyr', 'status' => WarrantyStatus::Open,
        ]);

        $this->actingAs($this->admin)->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('ST-ZEPHYR · Lüfter laut')
            ->assertSee('LV-ZEPHYR')
            ->assertSee(route('asset-finance.show', $contract), false)
            ->assertSee('RK-ZEPHYR · Display defekt')
            ->assertDontSee('RK-ERLEDIGT')
            ->assertSee('Montage Zephyr');
    }

    public function test_asset_panels_follow_rights_and_organization(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        ClaimCase::query()->create(['organization_id' => $this->organization->id, 'number' => 'RK-ZEPHYR', 'title' => 'Display defekt', 'reported_at' => now(), 'asset_id' => $asset->id]);
        $foreignAsset = Asset::factory()->create();
        ServiceTicket::factory()->create(['organization_id' => $foreignAsset->organization_id, 'asset_id' => $foreignAsset->id, 'ticket_no' => 'ST-FREMD']);
        $viewer = $this->member([Permission::AssetView]);

        $this->actingAs($viewer)->get(route('assets.show', $asset))->assertOk()->assertDontSee('RK-ZEPHYR');
        $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertOk()->assertSee('RK-ZEPHYR')->assertDontSee('ST-FREMD');
    }

    public function test_lead_cockpit_shows_appointments_without_superseded_ones(): void {
        $lead = Lead::query()->create([
            'organization_id' => $this->organization->id, 'company' => 'Zephyr Interessent', 'contact_first_name' => 'Max', 'contact_last_name' => 'Muster',
            'source' => LeadSource::Web->value, 'status' => LeadStatus::New->value, 'created_by' => $this->admin->id,
        ]);
        AppointmentRequest::query()->create([
            'organization_id' => $this->organization->id, 'source_uri' => 'https://calendly.example/zephyr-1', 'lead_id' => $lead->id,
            'service_label' => 'Erstberatung Zephyr', 'start_at' => now()->addDays(3), 'status' => AppointmentRequestStatus::Confirmed->value,
        ]);
        AppointmentRequest::query()->create([
            'organization_id' => $this->organization->id, 'source_uri' => 'https://calendly.example/zephyr-0', 'lead_id' => $lead->id,
            'service_label' => 'Ersetzter Termin', 'start_at' => now()->addDay(), 'status' => AppointmentRequestStatus::Superseded->value,
        ]);

        $this->actingAs($this->admin)->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee(__('cockpit.appointments.title'))
            ->assertSee('Erstberatung Zephyr')
            ->assertDontSee('Ersetzter Termin');
    }

    /** @param list<Permission> $permissions */
    private function member(array $permissions): User {
        $user = $this->orgUser();
        $user->givePermissionTo(array_map(static fn (Permission $p): string => $p->value, $permissions));
        $user->unsetRelation('permissions');

        return $user;
    }
}
