<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntityCockpitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Cockpit;

use App\Enums\Contract\{ContractObligationKind, ContractObligationStatus};
use App\Enums\Guarantee\{GuaranteeDirection, GuaranteeKind};
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Enums\Survey\SurveyInvitationStatus;
use App\Enums\Warranty\{WarrantyBasis, WarrantySide, WarrantyStatus};
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Customer\Customer;
use App\Models\Guarantee\Guarantee;
use App\Models\Inventory\Warehouse;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Procurement\PurchaseOrder;
use App\Models\Project\Project;
use App\Models\Sales\Lead;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Supplier\{Supplier, SupplierCredentialType};
use App\Models\Survey\{Survey, SurveyAnswer, SurveyInvitation, SurveyQuestion, SurveyResponse};
use App\Models\Warranty\WarrantyPeriod;
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Models\EtsyReceipt;
use App\Services\Fields\CustomFieldService;
use App\Services\Sales\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Entitäts-Cockpits (Feature 182, MVP-1370–1375): Panels der Module, Lieferant, Lead und Projekt. */
class EntityCockpitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function customer(?Organization $organization = null): Customer {
        return Customer::factory()->create(['organization_id' => ($organization ?? $this->organization)->id]);
    }

    private function supplier(): Supplier {
        return Supplier::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function lead(): Lead {
        return Lead::query()->create([
            'organization_id' => $this->organization->id, 'company' => 'Zephyr Interessent', 'contact_first_name' => 'Max', 'contact_last_name' => 'Muster',
            'source' => LeadSource::Web->value, 'status' => LeadStatus::New->value, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_customer_cockpit_shows_tickets_contracts_deadlines_and_nps(): void {
        $customer = $this->customer();
        ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'ticket_no' => 'ST-ZEPHYR', 'title' => 'Drucker defekt']);
        $contract = Contract::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'title' => 'Wartung Zephyr']);
        ContractObligation::query()->create([
            'organization_id' => $this->organization->id, 'contract_id' => $contract->id, 'kind' => ContractObligationKind::NoticeDeadline,
            'title' => 'Kündigung Zephyr', 'due_on' => '2030-03-31', 'warn_days_before' => 30, 'status' => ContractObligationStatus::Open,
        ]);
        WarrantyPeriod::query()->create([
            'organization_id' => $this->organization->id, 'side' => WarrantySide::Owed, 'basis' => WarrantyBasis::Bgb5Years,
            'starts_on' => '2026-01-01', 'ends_on' => '2031-01-01', 'customer_id' => $customer->id, 'trade' => 'Dach Zephyr', 'status' => WarrantyStatus::Open,
        ]);
        Guarantee::query()->create([
            'organization_id' => $this->organization->id, 'direction' => GuaranteeDirection::Received->value, 'kind' => GuaranteeKind::Warranty->value,
            'reference' => 'BG-ZEPHYR', 'amount' => '500.00', 'currency' => 'EUR', 'issuer_name' => 'Musterbank AG', 'customer_id' => $customer->id,
        ]);
        $survey = Survey::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Zufriedenheit Zephyr']);
        $question = SurveyQuestion::factory()->create(['organization_id' => $this->organization->id, 'survey_id' => $survey->id]);
        foreach ([10, 9, 3] as $score) {
            $invitation = SurveyInvitation::factory()->create([
                'organization_id' => $this->organization->id, 'survey_id' => $survey->id, 'customer_id' => $customer->id, 'status' => SurveyInvitationStatus::Responded,
            ]);
            $response = SurveyResponse::query()->create(['organization_id' => $this->organization->id, 'survey_id' => $survey->id, 'survey_invitation_id' => $invitation->id, 'context_kind' => 'manual']);
            SurveyAnswer::query()->create(['organization_id' => $this->organization->id, 'survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'value_int' => $score]);
        }

        $this->actingAs($this->admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('ST-ZEPHYR · Drucker defekt')
            ->assertSee('Wartung Zephyr')
            ->assertSee('Kündigung Zephyr')
            ->assertSee('Dach Zephyr')
            ->assertSee('BG-ZEPHYR')
            // (2 Promotoren − 1 Detraktor) / 3 = 33.
            ->assertSee(__('cockpit.surveys.summary_nps', ['nps' => 33, 'answers' => 3, 'responded' => 3, 'total' => 3]));
    }

    public function test_panels_follow_rights_and_organization(): void {
        $customer = $this->customer();
        ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'ticket_no' => 'ST-ZEPHYR']);
        Guarantee::query()->create([
            'organization_id' => $this->organization->id, 'direction' => GuaranteeDirection::Received->value, 'kind' => GuaranteeKind::Warranty->value,
            'reference' => 'BG-ZEPHYR', 'amount' => '500.00', 'currency' => 'EUR', 'issuer_name' => 'Musterbank AG', 'customer_id' => $customer->id,
        ]);
        $foreign = $this->customer(Organization::factory()->create());
        ServiceTicket::factory()->create(['organization_id' => $foreign->organization_id, 'customer_id' => $foreign->id, 'ticket_no' => 'ST-FREMD']);
        $viewer = $this->member([\App\Enums\User\Permission::CustomerViewAny, \App\Enums\User\Permission::CustomerView]);

        $this->actingAs($viewer)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('ST-ZEPHYR')
            ->assertDontSee('BG-ZEPHYR');
        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertSee('ST-ZEPHYR')->assertDontSee('ST-FREMD');
    }

    public function test_supplier_cockpit_shows_orders_and_credentials_and_keeps_custom_fields(): void {
        $supplier = $this->supplier();
        PurchaseOrder::query()->create([
            'organization_id' => $this->organization->id, 'number' => 'BE-ZEPHYR', 'supplier_id' => $supplier->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => PurchaseOrderStatus::Ordered->value, 'currency' => 'EUR',
        ]);
        SupplierCredentialType::query()->create(['organization_id' => $this->organization->id, 'code' => 'zephyr', 'name' => 'Zephyr-Nachweis', 'is_required_default' => true, 'is_active' => true]);
        $key = app(CustomFieldService::class)->saveDefinition($this->organization, 'suppliers', [['label' => 'Rahmenvertrag', 'type' => 'text']])->schema->keys()[0];

        $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
            'name' => $supplier->name, 'custom' => [$key => 'RV-ZEPHYR'],
        ])->assertRedirect();

        $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('BE-ZEPHYR')
            ->assertSee('Zephyr-Nachweis')
            ->assertSee('RV-ZEPHYR');
    }

    public function test_lead_keeps_custom_fields_tags_and_attachments_until_anonymized(): void {
        Storage::fake('local');
        $lead = $this->lead();
        $key = app(CustomFieldService::class)->saveDefinition($this->organization, 'leads', [['label' => 'Budget', 'type' => 'text']])->schema->keys()[0];

        $this->actingAs($this->admin)->put(route('leads.update', $lead), [
            'company' => 'Zephyr Interessent', 'source' => LeadSource::Web->value, 'new_tags' => 'Messe Zephyr', 'custom' => [$key => '20 TEUR'],
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('attachments.store', ['type' => 'lead', 'id' => $lead->sqid]), [
            'file' => UploadedFile::fake()->create('angebot.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame(['Messe Zephyr'], $lead->tags()->pluck('name')->all());
        $this->assertSame('20 TEUR', $lead->customValues()->get($key));
        $this->assertSame(1, $lead->attachments()->count());
        $this->actingAs($this->admin)->get(route('leads.show', $lead))->assertOk()->assertSee('20 TEUR')->assertSee('angebot.pdf');

        app(LeadService::class)->anonymize($lead);

        $this->assertSame(0, $lead->attachments()->count());
        $this->assertSame([], $lead->tags()->pluck('name')->all());
        $this->assertNull($lead->customFieldValue()->first());
    }

    public function test_project_overview_shows_economics_only_with_report_right(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $worker = $this->member([\App\Enums\User\Permission::ProjectViewAny, \App\Enums\User\Permission::ProjectView]);

        $this->actingAs($this->admin)->get(route('projects.show', $project))
            ->assertOk()->assertSee(__('Deckungsbeitrag'))->assertSee(route('reports.economics', ['project' => $project->sqid]), false);
        $this->actingAs($worker)->get(route('projects.show', $project))
            ->assertOk()->assertDontSee(__('Deckungsbeitrag'));
    }

    public function test_etsy_orders_appear_on_the_customer_cockpit_for_admins(): void {
        $customer = $this->customer();
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => EtsyPlugin::ID, 'enabled' => true, 'settings' => []]);
        EtsyReceipt::create([
            'organization_id' => $this->organization->id, 'receipt_id' => 4711, 'status' => 'paid', 'was_paid' => true, 'was_shipped' => false,
            'currency' => 'EUR', 'total_gross' => '49.90', 'customer_id' => $customer->id, 'ordered_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk()->assertSee('#4711');
    }

    /** @param list<\App\Enums\User\Permission> $permissions */
    private function member(array $permissions): User {
        $user = $this->orgUser();
        $user->givePermissionTo(array_map(static fn ($p): string => $p->value, $permissions));
        $user->unsetRelation('permissions');

        return $user;
    }
}
