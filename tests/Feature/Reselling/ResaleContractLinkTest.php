<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleContractLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractPartnerType, ContractStatus, ContractTermKind};
use App\Enums\User\Permission;
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\{Customer, ForeignCustomer, Organization, User};
use App\Models\Reselling\ResaleSubscription;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reselling\Register\ResaleContractObligationSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abo ↔ Vertrag (Feature 152, 079-Anbindung): Verknüpfung im Dialog, Anzeige
 * auf Abo- und Vertragsseite (Modul-/Rechte-Gate), Partner = Rechnungs-
 * empfänger, und der Obligationen-Abgleich (Kündigungsfrist/Verlängerungs-
 * warnung, idempotent, folgt dem Datum, schließt bei Beendigung).
 */
class ResaleContractLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $this->travelTo('2026-09-11');
    }

    /** @param array<string, mixed> $overrides */
    private function contract(?Customer $customer = null, array $overrides = []): Contract {
        return Contract::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'number' => 'V-2026-0007',
            'title' => 'Microsoft 365 Rahmenvertrag',
            'kind' => ContractKind::License,
            'status' => ContractStatus::Active,
            'partner_type' => ContractPartnerType::Customer,
            'customer_id' => ($customer ?? $this->customer)->id,
            'term_kind' => ContractTermKind::OpenEnded,
            'starts_on' => '2025-01-01',
            'ends_on' => null,
            'notice_period_days' => 30,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function subscription(array $overrides = []): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'kind' => 'license',
            'provider' => 'manual',
            'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id,
            'quantity' => 3,
            'starts_on' => '2025-11-01',
            'term_months' => 12,
            'interval' => 'yearly',
            'renewal' => 'auto',
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array {
        return array_merge([
            'label' => 'Microsoft 365 Business Premium',
            'kind' => 'license',
            'provider' => 'manual',
            'holder' => 'customer',
            'customer_id' => $this->customer->sqid,
            'quantity' => 3,
            'starts_on' => '2025-11-01',
            'term_months' => 12,
            'interval' => 'yearly',
            'renewal' => 'auto',
            'status' => 'active',
        ], $overrides);
    }

    public function test_dialog_offers_customer_contracts_and_the_link_shows_on_both_sides(): void {
        $contract = $this->contract();
        $this->contract(null, ['number' => 'V-2026-0008', 'title' => 'Beendeter Vertrag', 'status' => ContractStatus::Ended]);
        $supplierContract = $this->contract(null, ['number' => 'V-2026-0009', 'title' => 'Lieferantenvertrag', 'partner_type' => ContractPartnerType::Supplier, 'customer_id' => null]);

        $this->actingAs($this->admin)->get(route('finance.resale.create'))
            ->assertOk()
            ->assertSee(__('resale.contract.field'))
            ->assertSee('V-2026-0007 · Microsoft 365 Rahmenvertrag — Klimpel Bäder GmbH')
            ->assertDontSee('Beendeter Vertrag')
            ->assertDontSee($supplierContract->title);

        $this->actingAs($this->admin)->post(route('finance.resale.store'), $this->payload(['contract_id' => $contract->sqid]))->assertSessionHasNoErrors();
        $subscription = ResaleSubscription::query()->firstOrFail();
        $this->assertSame($contract->id, $subscription->contract_id);

        $this->actingAs($this->admin)->get(route('finance.resale.show', $subscription->sqid))
            ->assertOk()
            ->assertSee('V-2026-0007 · Microsoft 365 Rahmenvertrag')
            ->assertSee(route('contracts.show', $contract));
        $this->actingAs($this->admin)->get(route('finance.resale.edit', $subscription->sqid))
            ->assertOk()
            ->assertSee('value="' . $contract->sqid . '" selected', false);
        $this->actingAs($this->admin)->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(__('resale.contract.panel.title'))
            ->assertSee('Microsoft 365 Business Premium')
            ->assertSee('Klimpel Bäder GmbH')
            ->assertSee(route('finance.resale.show', $subscription->sqid));

        // Lösen: leeres Feld nimmt den Vertrag wieder weg.
        $this->actingAs($this->admin)->put(route('finance.resale.update', $subscription->sqid), $this->payload(['contract_id' => '']))->assertSessionHasNoErrors();
        $this->assertNull($subscription->fresh()?->contract_id);
        $this->actingAs($this->admin)->get(route('contracts.show', $contract))->assertOk()->assertSee(__('resale.contract.panel.empty'));
    }

    public function test_contract_partner_must_be_the_invoice_recipient(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Andere GmbH']);
        $foreignContract = $this->contract($other, ['number' => 'V-2026-0010']);
        $this->actingAs($this->admin)->postJson(route('finance.resale.store'), $this->payload(['contract_id' => $foreignContract->sqid]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_id' => __('resale.contract.error.recipient_mismatch')]);

        // Eigener Bestand hat keinen Rechnungsempfänger — kein Vertrag.
        $own = $this->contract();
        $this->actingAs($this->admin)->postJson(route('finance.resale.store'), $this->payload(['holder' => 'own', 'customer_id' => '', 'contract_id' => $own->sqid]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_id' => __('resale.contract.error.no_recipient')]);

        // Fremder Kunde als Halter: Rechnung an den Partner, dessen Vertrag passt.
        $end = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Steuerbüro Kaik']);
        $this->actingAs($this->admin)->postJson(route('finance.resale.store'), $this->payload(['holder' => 'foreign', 'customer_id' => '', 'foreign_customer_id' => $end->sqid, 'contract_id' => $own->sqid]))
            ->assertRedirect();
        $this->assertSame($own->id, ResaleSubscription::query()->firstOrFail()->contract_id);

        // Vertrag einer fremden Organisation: org-gescopte Existenzregel.
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrg->id]);
        $alien = Contract::factory()->create(['organization_id' => $otherOrg->id, 'partner_type' => ContractPartnerType::Customer, 'customer_id' => $otherCustomer->id, 'status' => ContractStatus::Active]);
        $this->actingAs($this->admin)->postJson(route('finance.resale.store'), $this->payload(['contract_id' => $alien->sqid]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contract_id']);
        $this->assertSame(1, ResaleSubscription::query()->count());
    }

    public function test_contract_panel_needs_the_reselling_module_and_permission(): void {
        $contract = $this->contract();
        $this->subscription(['contract_id' => $contract->id]);

        // Die Contract-Factory legt einen Verantwortlichen samt Organisation an und
        // verschiebt dabei die Spatie-Team-ID — vor der Rechtevergabe zurücksetzen.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(Permission::ContractView->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($viewer)->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertDontSee(__('resale.contract.panel.title'))
            ->assertDontSee('Microsoft 365 Business Premium');

        config(['license.feature_overrides' => ['module.reselling' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->actingAs($this->admin)->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertDontSee(__('resale.contract.panel.title'))
            ->assertDontSee('Microsoft 365 Business Premium');
    }

    public function test_cancelled_subscription_gets_a_notice_deadline_that_follows_the_end(): void {
        $contract = $this->contract();
        $subscription = $this->subscription(['contract_id' => $contract->id, 'renewal' => 'cancel', 'ends_on' => '2026-12-31', 'status' => 'cancelled']);
        $sync = app(ResaleContractObligationSync::class);

        $this->assertSame(['created' => 1, 'updated' => 0, 'closed' => 0], $sync->sync($this->organization));
        $obligation = ContractObligation::query()->firstOrFail();
        $this->assertSame($contract->id, $obligation->contract_id);
        $this->assertSame(ContractObligationKind::NoticeDeadline, $obligation->kind);
        $this->assertSame('2026-12-01', $obligation->due_on->toDateString(), 'Ende minus 30 Tage Kündigungsfrist');
        $this->assertSame(14, $obligation->warn_days_before);
        $this->assertSame('open', $obligation->status);
        $this->assertSame('resale:' . $subscription->id, $obligation->note);
        $this->assertStringContainsString('Microsoft 365 Business Premium', (string) $obligation->title);
        $this->assertStringContainsString('31.12.2026', (string) $obligation->title);

        // Zweiter Lauf: nichts Neues.
        $this->assertSame(['created' => 0, 'updated' => 0, 'closed' => 0], $sync->sync($this->organization));
        $this->assertSame(1, ContractObligation::query()->count());

        // Ende verschoben: derselbe Termin wandert mit.
        $subscription->forceFill(['ends_on' => '2027-01-31'])->save();
        $this->assertSame(['created' => 0, 'updated' => 1, 'closed' => 0], $sync->sync($this->organization));
        $this->assertSame(1, ContractObligation::query()->count());
        $obligation->refresh();
        $this->assertSame('2027-01-01', $obligation->due_on->toDateString());
        $this->assertStringContainsString('31.01.2027', (string) $obligation->title);

        // Erledigt bleibt erledigt — auch bei unverändertem Termin kein neuer.
        $obligation->forceFill(['status' => 'done', 'done_at' => now()])->save();
        $this->assertSame(['created' => 0, 'updated' => 0, 'closed' => 0], $sync->sync($this->organization));
        $this->assertSame(1, ContractObligation::query()->count());

        // Neuer Termin nach einem erledigten: neue Obligation.
        $subscription->forceFill(['ends_on' => '2027-03-31'])->save();
        $this->assertSame(['created' => 1, 'updated' => 0, 'closed' => 0], $sync->sync($this->organization));
        $this->assertSame(2, ContractObligation::query()->count());
        $this->assertSame('2027-03-01', ContractObligation::query()->open()->firstOrFail()->due_on->toDateString());
    }

    public function test_auto_renewal_gets_a_renewal_warning_and_ending_closes_it(): void {
        $contract = $this->contract(null, ['notice_period_days' => null]);
        $subscription = $this->subscription(['contract_id' => $contract->id]);
        $sync = app(ResaleContractObligationSync::class);

        $this->assertSame(['created' => 1, 'updated' => 0, 'closed' => 0], $sync->sync($this->organization));
        $obligation = ContractObligation::query()->firstOrFail();
        $this->assertSame(ContractObligationKind::RenewalWarning, $obligation->kind);
        $this->assertSame('2026-10-02', $obligation->due_on->toDateString(), 'nächste Periode 01.11.2026 minus 30 Tage (Vertrag ohne Frist)');
        $this->assertStringContainsString('01.11.2026', (string) $obligation->title);

        // Kündigung des Abos: aus der Verlängerungswarnung wird die Kündigungsfrist zum Ende.
        $subscription->forceFill(['renewal' => 'cancel', 'ends_on' => '2026-10-31', 'status' => 'cancelled'])->save();
        $this->assertSame(['created' => 0, 'updated' => 1, 'closed' => 0], $sync->sync($this->organization));
        $obligation->refresh();
        $this->assertSame(ContractObligationKind::NoticeDeadline, $obligation->kind);
        $this->assertSame('2026-10-01', $obligation->due_on->toDateString());

        // Abo beendet: der offene Termin wird geschlossen, nicht gelöscht.
        $subscription->forceFill(['status' => 'ended'])->save();
        $this->assertSame(['created' => 0, 'updated' => 0, 'closed' => 1], $sync->sync($this->organization));
        $obligation->refresh();
        $this->assertSame('done', $obligation->status);
        $this->assertNotNull($obligation->done_at);
        $this->assertSame(['created' => 0, 'updated' => 0, 'closed' => 0], $sync->sync($this->organization), 'geschlossen bleibt geschlossen');

        // Abo ohne Vertrag: ebenfalls schließen; Vertragswechsel: alter Termin zu, neuer am neuen Vertrag.
        $second = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'contract_id' => $contract->id]);
        $sync->sync($this->organization);
        $second->forceFill(['contract_id' => null])->save();
        $this->assertSame(['created' => 0, 'updated' => 0, 'closed' => 1], $sync->sync($this->organization));
        $newContract = $this->contract(null, ['number' => 'V-2026-0011']);
        $second->forceFill(['contract_id' => $newContract->id])->save();
        $this->assertSame(['created' => 1, 'updated' => 0, 'closed' => 0], $sync->sync($this->organization));
        $this->assertSame(1, ContractObligation::query()->open()->where('contract_id', $newContract->id)->count());
    }

    public function test_sync_command_reports_the_contract_calendar_step(): void {
        $contract = $this->contract();
        $this->subscription(['contract_id' => $contract->id]);

        $this->artisan('resale:sync-periods')
            ->expectsOutputToContain('Vertragsobligationen: 1 neu, 0 aktualisiert, 0 geschlossen.')
            ->assertSuccessful();
        $this->artisan('resale:sync-periods', ['--org' => $this->organization->id])
            ->expectsOutputToContain('Vertragsobligationen: 0 neu, 0 aktualisiert, 0 geschlossen.')
            ->assertSuccessful();
        $this->assertSame(1, ContractObligation::query()->count());
    }
}
