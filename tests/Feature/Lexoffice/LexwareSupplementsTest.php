<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwareSupplementsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Lexoffice;

use App\Enums\Lexoffice\{LexofficeHandoverStatus, LexwareCoverage, LexwareFeature, LexwarePlan};
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\{Invoice, InvoiceSchedule};
use App\Models\Platform\{Organization, User};
use App\Models\Plugins\Lexoffice\LexofficeInvoiceHandover;
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use App\Plugins\Lexoffice\Tariff\{FeatureAvailability, LexwareFeatureResolver, LexwarePlanMatrix};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lexware-Office-Tarifergänzungen (Feature 158, MVP-831–833): Funktionsmatrix
 * je Tarif, Tarifprofil mit Zuständen und Voraussetzungen, Übergabeliste mit
 * Export (PDF + Hash + Zuordnungsliste) und manueller Bestätigung — inklusive
 * Rechte- und Mandantengrenzen.
 */
final class LexwareSupplementsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Müller GmbH']);
    }

    public function test_matrix_follows_the_published_plan_table(): void {
        $matrix = new LexwarePlanMatrix;

        $this->assertSame(LexwareCoverage::Supplement, $matrix->coverage(LexwarePlan::S, LexwareFeature::Invoices));
        $this->assertSame(LexwareCoverage::Lexware, $matrix->coverage(LexwarePlan::M, LexwareFeature::Invoices));
        $this->assertSame(LexwareCoverage::Supplement, $matrix->coverage(LexwarePlan::L, LexwareFeature::RecurringInvoices));
        $this->assertSame(LexwareCoverage::Lexware, $matrix->coverage(LexwarePlan::XL, LexwareFeature::RecurringInvoices));
        $this->assertSame(LexwareCoverage::Expansion, $matrix->coverage(LexwarePlan::M, LexwareFeature::Accounting));
        $this->assertSame(LexwareCoverage::Lexware, $matrix->coverage(LexwarePlan::L, LexwareFeature::Accounting));
        $this->assertSame(LexwareCoverage::Unknown, $matrix->coverage(LexwarePlan::Unknown, LexwareFeature::Invoices));
        $this->assertTrue($matrix->allowsOwnApiKey(LexwarePlan::XL));
        $this->assertFalse($matrix->allowsOwnApiKey(LexwarePlan::L));
    }

    public function test_plan_page_saves_profile_and_resolves_states(): void {
        $this->actingAs($this->admin)->get(route('lexoffice.plan.index'))
            ->assertOk()
            ->assertSee((string) __('lexware.plan_page.matrix_title'))
            ->assertSee((string) __('lexware.state.check_availability'));

        $this->actingAs($this->admin)->post(route('lexoffice.plan.update'), [
            'plan' => 's', 'plan_source' => 'user', 'handover_channel' => 'manual',
            'local_features' => ['invoices', 'recurring_invoices'],
        ])->assertRedirect(route('lexoffice.plan.index'));

        $this->assertSame('s', Setting::get('lexware.plan'));
        $this->assertSame(['invoices', 'recurring_invoices'], Setting::get('lexware.local_features'));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $this->organization->id, 'event' => 'lexware.tariffChanged']);

        $states = collect(app(LexwareFeatureResolver::class)->resolve($this->organization->fresh(), $this->admin))->keyBy(fn ($a) => $a->feature->value);
        $this->assertSame(FeatureAvailability::STATE_AVAILABLE, $states['invoices']->state);
        $this->assertSame(FeatureAvailability::STATE_SETUP_REQUIRED, $states['quotes']->state);
        $this->assertSame('lexware.reason.not_activated', $states['quotes']->reasonKey);
        $this->assertSame(FeatureAvailability::STATE_PLANNED, $states['accounting']->state);

        $this->actingAs($this->admin)->get(route('lexoffice.plan.index'))
            ->assertOk()
            ->assertSee((string) __('lexware.state.available'))
            ->assertSee((string) __('lexware.reason.not_activated'));

        // Kanal „automatisch" ohne XL: abgewiesen, Profil unverändert.
        $this->actingAs($this->admin)->post(route('lexoffice.plan.update'), [
            'plan' => 's', 'plan_source' => 'user', 'handover_channel' => 'api',
        ])->assertSessionHasErrors('handover_channel');
        $this->assertSame('manual', Setting::get('lexware.handover_channel', 'manual'));

        // Ungültiger Tarif.
        $this->actingAs($this->admin)->post(route('lexoffice.plan.update'), ['plan' => 'xxl', 'plan_source' => 'user', 'handover_channel' => 'manual'])
            ->assertSessionHasErrors('plan');

        // M: Rechnungen in Lexware enthalten, Serien weiterhin Ergänzung.
        $this->actingAs($this->admin)->post(route('lexoffice.plan.update'), [
            'plan' => 'm', 'plan_source' => 'provider', 'plan_confirmed_on' => '2026-09-01', 'handover_channel' => 'manual', 'local_features' => ['recurring_invoices'],
        ])->assertRedirect();
        $states = collect(app(LexwareFeatureResolver::class)->resolve($this->organization->fresh(), $this->admin))->keyBy(fn ($a) => $a->feature->value);
        $this->assertSame(FeatureAvailability::STATE_LEXWARE, $states['invoices']->state);
        $this->assertSame(FeatureAvailability::STATE_AVAILABLE, $states['recurring_invoices']->state);
    }

    public function test_external_billing_authority_blocks_local_supplements_and_expired_trial_falls_back(): void {
        Setting::set('lexware.plan', 's', SettingScope::Organization, $this->organization, $this->admin->id);
        Setting::set('lexware.local_features', ['invoices'], SettingScope::Organization, $this->organization, $this->admin->id);
        $settings = (array) $this->organization->fresh()->settings;
        $settings['billing_mode'] = 'lexoffice';
        $this->organization->forceFill(['settings' => $settings])->save();
        app()->instance('currentOrganization', $this->organization->fresh());

        $states = collect(app(LexwareFeatureResolver::class)->resolve($this->organization->fresh(), $this->admin))->keyBy(fn ($a) => $a->feature->value);
        $this->assertSame(FeatureAvailability::STATE_SETUP_REQUIRED, $states['invoices']->state);
        $this->assertSame('lexware.reason.billing_external', $states['invoices']->reasonKey);

        // Abgelaufener Testzugang: der bestätigte Folgetarif gilt.
        Setting::set('lexware.plan', 'xl', SettingScope::Organization, $this->organization, $this->admin->id);
        Setting::set('lexware.trial_ends_on', now()->subDay()->toDateString(), SettingScope::Organization, $this->organization, $this->admin->id);
        Setting::set('lexware.trial_successor_plan', 's', SettingScope::Organization, $this->organization, $this->admin->id);
        app()->instance('currentOrganization', $this->organization->fresh());
        $this->assertSame(LexwarePlan::S, app(\App\Plugins\Lexoffice\Tariff\LexwareTariffService::class)->profile()->effectivePlan());
    }

    public function test_handover_list_exports_with_hash_and_confirms_manually(): void {
        Setting::set('lexware.plan', 's', SettingScope::Organization, $this->organization, $this->admin->id);
        Setting::set('lexware.local_features', ['invoices', 'recurring_invoices'], SettingScope::Organization, $this->organization, $this->admin->id);
        app()->instance('currentOrganization', $this->organization->fresh());

        $issued = $this->makeInvoice('R2026-0100', Invoice::STATUS_ISSUED);
        $draft = $this->makeInvoice('R2026-0101', Invoice::STATUS_DRAFT);
        $published = $this->makeInvoice('R2026-0102', Invoice::STATUS_ISSUED);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficeInvoiceService::EXT_TYPE_INVOICE,
            'referenceable_type' => $published->getMorphClass(),
            'referenceable_id' => $published->id,
            'external_id' => 'lx-1',
        ]);

        $this->actingAs($this->admin)->get(route('lexoffice.handover.index'))
            ->assertOk()
            ->assertSee('R2026-0100')
            ->assertDontSee('R2026-0101')
            ->assertDontSee('R2026-0102')
            ->assertSee((string) __('lexware.handover.status.pending'));

        $response = $this->actingAs($this->admin)->post(route('lexoffice.handover.export'), ['invoices' => [$issued->sqid]]);
        $response->assertOk()->assertHeader('Content-Type', 'application/zip');

        $handover = LexofficeInvoiceHandover::query()->where('invoice_id', $issued->id)->firstOrFail();
        $this->assertSame(LexofficeHandoverStatus::Exported, $handover->status);
        $this->assertSame(64, strlen((string) $handover->document_sha256));
        $this->assertSame($this->admin->id, $handover->exported_by);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $issued->id, 'event' => 'invoice.lexwareHandover.exported']);

        // Entwurf ist nicht exportierbar.
        $this->actingAs($this->admin)->post(route('lexoffice.handover.export'), ['invoices' => [$draft->sqid]])->assertSessionHasErrors('invoices');

        $this->actingAs($this->admin)->post(route('lexoffice.handover.confirm', $issued), ['confirmation_note' => 'Im Lexware-Belegeingang hochgeladen'])
            ->assertRedirect();
        $handover->refresh();
        $this->assertSame(LexofficeHandoverStatus::Confirmed, $handover->status);
        $this->assertSame($this->admin->id, $handover->confirmed_by);
        $this->assertNotNull($handover->confirmed_at);

        // Erneuter Export stuft die Bestätigung nicht zurück.
        $this->actingAs($this->admin)->get(route('lexoffice.handover.export-one', $issued))->assertOk();
        $this->assertSame(LexofficeHandoverStatus::Confirmed, $handover->fresh()->status);

        $this->actingAs($this->admin)->get(route('lexoffice.handover.index', ['status' => 'confirmed']))->assertOk()->assertSee('R2026-0100');
        $this->actingAs($this->admin)->get(route('lexoffice.handover.index', ['status' => 'pending']))->assertOk()->assertDontSee('R2026-0100');

        // Rechnungsseite und Abrechnungsplan zeigen den Übergabestand.
        $this->actingAs($this->admin)->get(route('invoices.show', $issued))->assertOk()->assertSee((string) __('lexware.handover.status.confirmed'));
        $schedule = InvoiceSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'title' => 'Wartung monatlich',
            'interval_unit' => 'month',
            'interval_count' => 1,
            'billing_period_mode' => 'previous',
            'next_run_on' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)->get(route('invoice-schedules.index'))->assertOk()->assertSee((string) __('lexware.handover.title'));
        $this->actingAs($this->admin)->get(route('invoice-schedules.show', $schedule))->assertOk()->assertSee((string) __('lexware.field.handover_state'));
    }

    public function test_rights_and_tenant_boundaries(): void {
        Setting::set('lexware.local_features', ['invoices'], SettingScope::Organization, $this->organization, $this->admin->id);
        app()->instance('currentOrganization', $this->organization->fresh());
        $issued = $this->makeInvoice('R2026-0200', Invoice::STATUS_ISSUED);

        // Factories verstellen den Spatie-Team-Kontext — vor der Rechtevergabe zurücksetzen.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $reader = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $reader->givePermissionTo([Permission::InvoiceViewAny->value]);
        $this->actingAs($reader)->get(route('lexoffice.handover.index'))->assertOk()->assertSee('R2026-0200');
        $this->actingAs($reader)->post(route('lexoffice.handover.export'), ['invoices' => [$issued->sqid]])->assertForbidden();
        $this->actingAs($reader)->post(route('lexoffice.handover.confirm', $issued))->assertForbidden();
        $this->actingAs($reader)->post(route('lexoffice.plan.update'), ['plan' => 's', 'plan_source' => 'user', 'handover_channel' => 'manual'])->assertForbidden();
        $this->actingAs($reader)->get(route('lexoffice.plan.index'))->assertOk()->assertSee((string) __('lexware.plan_page.read_only'));

        $otherOrg = Organization::factory()->create();
        $stranger = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($stranger)->get(route('lexoffice.handover.index'))->assertOk()->assertDontSee('R2026-0200');
        $this->actingAs($stranger)->post(route('lexoffice.handover.confirm', $issued))->assertNotFound();
        $this->assertDatabaseMissing('lexoffice_invoice_handovers', ['invoice_id' => $issued->id]);
    }

    private function makeInvoice(string $number, string $status): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => $status,
            'issued_on' => $status === Invoice::STATUS_DRAFT ? null : now()->toDateString(),
            'due_on' => $status === Invoice::STATUS_DRAFT ? null : now()->addDays(14)->toDateString(),
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'service_date' => now()->toDateString(),
            'description' => 'Wartung',
            'quantity' => '1',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'tax_rate' => '19.00',
            'position' => 1,
        ]);

        return $invoice->fresh() ?? $invoice;
    }
}
