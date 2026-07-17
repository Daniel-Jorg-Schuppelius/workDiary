<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Contract;

use App\Enums\Contract\{ContractKind, ContractObligationKind, ContractPartnerType, ContractStatus, ContractTermKind, IndexationMethod};
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Contract\Contract;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{Organization, User};
use App\Services\Contract\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Welle D — Allgemeines Contract-Lifecycle-Management: Anlage/Bearbeitung,
 * Berechnung des nächstmöglichen Kündigungstermins (befristet/auto-Verlängerung/
 * unbefristet), Vertragskalender-Obligationen mit Fristen-/Eskalationsscan,
 * additive Verknüpfung zum Leasing-Modell (org-konsistent), Statusmaschine,
 * Org-Isolation, Recht und additive/portable Migration.
 */
final class ContractLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeContract(array $overrides = [], ?Organization $org = null): Contract {
        $org ??= $this->organization;

        return app(ContractService::class)->create($org, $this->admin, array_merge([
            'title' => 'Wartung Aufzugsanlage',
            'kind' => ContractKind::Maintenance->value,
            'status' => ContractStatus::Draft->value,
            'partner_type' => ContractPartnerType::Other->value,
            'partner_name' => 'TechWart GmbH',
            'term_kind' => ContractTermKind::Fixed->value,
            'starts_on' => '2026-01-01',
            'ends_on' => '2027-06-30',
            'notice_period_days' => 90,
            'indexation_method' => IndexationMethod::None->value,
            'currency' => 'EUR',
            'value_period' => 'yearly',
        ], $overrides));
    }

    public function test_contract_gets_number_and_can_be_created_and_updated_via_http(): void {
        $this->actingAs($this->admin)->post(route('contracts.store'), [
            'title' => 'Bürolizenz',
            'kind' => ContractKind::License->value,
            'partner_type' => ContractPartnerType::Supplier->value,
            'partner_name' => 'SaaS AG',
            'term_kind' => ContractTermKind::OpenEnded->value,
            'starts_on' => '2026-02-01',
            'notice_period_days' => 30,
            'indexation_method' => IndexationMethod::FixedPercent->value,
            'indexation_value' => 3.5,
            'currency' => 'EUR',
            'value_period' => 'monthly',
            'value_amount' => 199,
        ])->assertRedirect();

        $contract = Contract::query()->where('title', 'Bürolizenz')->firstOrFail();
        $this->assertMatchesRegularExpression('/^VTR-\d{4}-\d{4}$/', $contract->number);
        $this->assertSame(ContractStatus::Draft, $contract->status);

        $this->actingAs($this->admin)->put(route('contracts.update', $contract), [
            'title' => 'Bürolizenz Pro',
            'kind' => ContractKind::License->value,
            'partner_type' => ContractPartnerType::Supplier->value,
            'partner_name' => 'SaaS AG',
            'term_kind' => ContractTermKind::OpenEnded->value,
            'starts_on' => '2026-02-01',
            'notice_period_days' => 60,
            'indexation_method' => IndexationMethod::FixedPercent->value,
            'indexation_value' => 3.5,
            'currency' => 'EUR',
            'value_period' => 'monthly',
        ])->assertRedirect();

        $this->assertSame('Bürolizenz Pro', $contract->fresh()->title);
        $this->assertSame(60, $contract->fresh()->notice_period_days);
    }

    public function test_create_and_show_views_render(): void {
        $contract = $this->makeContract();
        app(ContractService::class)->generateNoticeObligation(app(ContractService::class)->activate($contract, $this->admin));

        $this->actingAs($this->admin)->get(route('contracts.create'))
            ->assertOk()->assertSee((string) __('Neuer Vertrag'));
        $this->actingAs($this->admin)->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee($contract->number)
            ->assertSee((string) __('Nächstmöglich kündbar zum'));
    }

    public function test_next_termination_fixed_term_without_auto_renew_is_end_date(): void {
        $contract = $this->makeContract(['auto_renew' => false, 'ends_on' => '2027-06-30', 'notice_period_days' => 90]);
        $service = app(ContractService::class);

        $this->assertSame('2027-06-30', $service->nextTerminationDate($contract)?->toDateString());
        // Kündigung muss 90 Tage vorher eingehen.
        $this->assertSame('2027-04-01', $service->noticeDeadline($contract)?->toDateString());
    }

    public function test_next_termination_fixed_term_with_auto_renew_advances_to_next_period(): void {
        Carbon::setTestNow('2026-07-14');
        // Ende in 18 Tagen, Frist 90 Tage → laufende Periode nicht mehr kündbar,
        // Termin rückt eine Verlängerungsperiode (12 Monate) weiter.
        $contract = $this->makeContract([
            'ends_on' => '2026-08-01',
            'notice_period_days' => 90,
            'auto_renew' => true,
            'renew_period_months' => 12,
        ]);

        $this->assertSame('2027-08-01', app(ContractService::class)->nextTerminationDate($contract)?->toDateString());
        Carbon::setTestNow();
    }

    public function test_next_termination_open_ended_respects_min_term_and_notice(): void {
        Carbon::setTestNow('2026-07-14');
        $contract = $this->makeContract([
            'term_kind' => ContractTermKind::OpenEnded->value,
            'ends_on' => null,
            'starts_on' => '2026-01-01',
            'min_term_months' => 12,
            'notice_period_days' => 30,
        ]);

        // Mindestlaufzeit-Ende (2027-01-01) liegt nach heute+30 → maßgeblich.
        $this->assertSame('2027-01-01', app(ContractService::class)->nextTerminationDate($contract)?->toDateString());

        // Ohne Mindestlaufzeit: heute + Kündigungsfrist.
        $noMinTerm = $this->makeContract([
            'term_kind' => ContractTermKind::OpenEnded->value,
            'ends_on' => null,
            'min_term_months' => null,
            'notice_period_days' => 30,
        ]);
        $this->assertSame('2026-08-13', app(ContractService::class)->nextTerminationDate($noMinTerm)?->toDateString());
        Carbon::setTestNow();
    }

    public function test_obligation_warning_notifies_and_overdue_is_marked_missed_idempotently(): void {
        \Illuminate\Support\Facades\Notification::fake();
        $responsible = User::factory()->create(['organization_id' => $this->organization->id]);
        $contract = $this->makeContract();
        app(ContractService::class)->activate($contract, $this->admin);
        $service = app(ContractService::class);

        $warning = $service->addObligation($contract, [
            'kind' => ContractObligationKind::Payment->value,
            'title' => 'Quartalsrate',
            'due_on' => now()->addDays(10)->toDateString(),
            'warn_days_before' => 30,
            'responsible_user_id' => $responsible->id,
        ]);
        $missed = $service->addObligation($contract, [
            'kind' => ContractObligationKind::Review->value,
            'title' => 'Jährliche Prüfung',
            'due_on' => now()->subDay()->toDateString(),
            'warn_days_before' => 5,
        ]);

        $service->scanObligations($this->organization);

        $this->assertSame('missed', (string) $missed->fresh()->status);
        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::ContractDeadlineDue->value,
            'subject_id' => $warning->id,
            'stage' => NotificationDispatchLog::STAGE_INITIAL,
        ]);

        // Idempotent pro Obligation.
        $service->scanObligations($this->organization);
        $this->assertSame(1, NotificationDispatchLog::query()
            ->where('event', NotificationEvent::ContractDeadlineDue->value)
            ->where('subject_id', $warning->id)
            ->where('stage', NotificationDispatchLog::STAGE_INITIAL)
            ->count());
    }

    public function test_obligation_escalation_fires_when_configured(): void {
        \Illuminate\Support\Facades\Notification::fake();
        User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]); // Eskalations-Empfänger
        NotificationRule::factory()->forEvent(NotificationEvent::ContractDeadlineDue)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
            'recipient_roles' => [\App\Enums\User\UserRole::Teamleitung->value],
            'escalation_enabled' => true,
            'escalate_after_hours' => 2,
            'escalation_role' => \App\Enums\User\UserRole::Teamleitung->value,
        ]);

        $contract = $this->makeContract();
        app(ContractService::class)->activate($contract, $this->admin);
        $service = app(ContractService::class);
        $obligation = $service->addObligation($contract, [
            'kind' => ContractObligationKind::NoticeDeadline->value,
            'title' => 'Kündigungsfrist',
            'due_on' => now()->addDays(5)->toDateString(),
            'warn_days_before' => 30,
        ]);

        $service->scanObligations($this->organization); // Stufe initial

        // Erst-Benachrichtigung künstlich altern lassen → Eskalation greift.
        NotificationDispatchLog::query()->withoutGlobalScopes()->update(['created_at' => now()->subHours(3)]);
        $service->scanObligations($this->organization);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::ContractDeadlineDue->value,
            'subject_id' => $obligation->id,
            'stage' => NotificationDispatchLog::STAGE_ESCALATION,
        ]);
    }

    public function test_recurring_obligation_regenerates_next_occurrence_on_completion(): void {
        $contract = $this->makeContract();
        $service = app(ContractService::class);
        $obligation = $service->addObligation($contract, [
            'kind' => ContractObligationKind::Payment->value,
            'title' => 'Monatsrate',
            'due_on' => '2026-07-01',
            'warn_days_before' => 10,
            'recurring' => true,
            'recurrence_months' => 1,
        ]);

        $service->completeObligation($obligation, $this->admin);

        $this->assertSame('done', (string) $obligation->fresh()->status);
        $this->assertSame(2, $contract->obligations()->count());
        $next = $contract->obligations()->where('status', 'open')->firstOrFail();
        $this->assertSame('2026-08-01', $next->due_on->toDateString());
        $this->assertTrue((bool) $next->recurring);
    }

    public function test_asset_finance_link_requires_same_organization(): void {
        $contract = $this->makeContract();

        $foreignOrg = Organization::factory()->create();
        $foreignAf = AssetFinanceContract::query()->create([
            'organization_id' => $foreignOrg->id,
            'number' => 'LEA-2026-9001',
            'kind' => 'operating_lease',
            'status' => 'draft',
            'partner_name' => 'Fremd-Leasing',
            'starts_on' => '2026-01-01',
            'payment_rhythm' => 'monthly',
            'currency' => 'EUR',
        ]);

        $this->expectException(\RuntimeException::class);
        app(ContractService::class)->linkAssetFinanceContract($foreignAf, $contract);
    }

    public function test_asset_finance_link_succeeds_for_same_organization(): void {
        $contract = $this->makeContract();
        $af = AssetFinanceContract::query()->create([
            'organization_id' => $this->organization->id,
            'number' => 'LEA-2026-0001',
            'kind' => 'operating_lease',
            'status' => 'draft',
            'partner_name' => 'Haus-Leasing',
            'starts_on' => '2026-01-01',
            'payment_rhythm' => 'monthly',
            'currency' => 'EUR',
        ]);
        $this->assertNull($af->contract_id);

        $this->actingAs($this->admin)->post(route('contracts.asset-finance.link', $contract), [
            'asset_finance_id' => $af->sqid,
        ])->assertRedirect();

        $this->assertSame($contract->id, (int) $af->fresh()->contract_id);
    }

    public function test_status_transitions_are_enforced(): void {
        $contract = $this->makeContract();
        $service = app(ContractService::class);

        $service->activate($contract, $this->admin);
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);

        $service->terminate($contract->fresh(), $this->admin, 'Vertragspartner insolvent.');
        $this->assertSame(ContractStatus::Terminated, $contract->fresh()->status);

        // Gekündigt → aktivieren unzulässig.
        try {
            $service->activate($contract->fresh(), $this->admin);
            $this->fail('Ungültiger Statuswechsel darf nicht möglich sein.');
        } catch (\RuntimeException) {
            // erwartet
        }

        $service->end($contract->fresh(), $this->admin);
        $this->assertSame(ContractStatus::Ended, $contract->fresh()->status);
    }

    public function test_termination_via_http_requires_reason(): void {
        $contract = $this->makeContract();
        app(ContractService::class)->activate($contract, $this->admin);

        $this->actingAs($this->admin)->post(route('contracts.terminate', $contract), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_org_isolation_hides_foreign_contracts(): void {
        $this->makeContract(['title' => 'Eigener Vertrag']);

        $foreignOrg = Organization::factory()->create();
        $this->makeContract(['title' => 'Fremdvertrag'], $foreignOrg);

        $this->actingAs($this->admin)->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('Eigener Vertrag')
            ->assertDontSee('Fremdvertrag');
    }

    public function test_permission_is_required_for_index(): void {
        // Rolle „user" hat kein contract.viewAny → 403 (Modul ist im Enterprise-Default lizenziert).
        $plain = $this->userWithRole(\App\Enums\User\UserRole::User->value);
        $this->actingAs($plain)->get(route('contracts.index'))->assertForbidden();

        // Teamleitung hat das Vertragsverwaltungsrecht → Zugriff.
        $lead = $this->userWithRole(\App\Enums\User\UserRole::Teamleitung->value);
        $this->actingAs($lead)->get(route('contracts.index'))->assertOk();
    }

    public function test_module_gating_blocks_without_license(): void {
        // module.contracts ist ab Pro enthalten — eine free-Organisation hat es nicht.
        $freeOrg = Organization::factory()->create(['plan' => 'free']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($freeOrg->id);
        $freeAdmin = User::factory()->admin()->create(['organization_id' => $freeOrg->id]);

        $this->actingAs($freeAdmin)->get(route('contracts.index'))->assertStatus(423);
    }

    public function test_additive_fk_column_exists_and_is_nullable(): void {
        // Additive Verknüpfung: nullable FK ohne erzwungene Bestandsmigration.
        $this->assertTrue(Schema::hasColumn('asset_finance_contracts', 'contract_id'));

        $af = AssetFinanceContract::query()->create([
            'organization_id' => $this->organization->id,
            'number' => 'LEA-2026-0002',
            'kind' => 'operating_lease',
            'status' => 'draft',
            'partner_name' => 'Ohne Vertragsbezug',
            'starts_on' => '2026-01-01',
            'payment_rhythm' => 'monthly',
            'currency' => 'EUR',
        ]);

        $this->assertNull($af->contract_id);
    }
}
