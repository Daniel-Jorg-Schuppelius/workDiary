<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\AssetFinance;

use App\Enums\AssetFinance\{AssetFinanceDeadlineKind, AssetFinanceEndKind, AssetFinanceKind, AssetFinanceStatus, AssetFinanceUsageLimitKind};
use App\Models\{Asset, IncomingEInvoice, User};
use App\Models\AssetFinance\AssetFinanceContract;
use App\Services\AssetFinance\AssetFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 074 (MVP-270–281): Leasingakte mit Nummernkreis, Konditionen-
 * Snapshot bei Aktivierung (P2/D11), Ratenplan, Fristenkalender mit
 * Vorwarnung, referenzierte Ist-Werte (Eingangsrechnung/Zähler),
 * Nutzungslimits mit Überschreitung, Optionen, Rückgabe-/Ende-Prozess,
 * Rechte-Trennung (finance) und Modul-Gating.
 */
final class AssetFinanceLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Radlader']);
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        $orgRole = Role::query()->where('name', $role)->where('team_id', $this->organization->id)->firstOrFail();
        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function createContract(array $overrides = []): AssetFinanceContract {
        return app(AssetFinanceService::class)->create($this->organization, $this->admin, array_merge([
            'kind' => AssetFinanceKind::OperatingLease->value,
            'partner_name' => 'Muster-Leasing GmbH',
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->startOfMonth()->addMonths(11)->toDateString(),
            'payment_rhythm' => 'monthly',
            'rate_amount' => '400.00',
            'residual_value' => '5000.00',
        ], $overrides), [$this->asset->id]);
    }

    public function test_contract_gets_number_and_activation_freezes_terms_and_generates_schedule(): void {
        $contract = $this->createContract();
        $this->assertMatchesRegularExpression('/^LEA-\d{4}-\d{4}$/', $contract->number);
        $this->assertSame(AssetFinanceStatus::Draft, $contract->status);
        $this->assertDatabaseHas('asset_finance_contract_assets', ['asset_id' => $this->asset->id]);

        $service = app(AssetFinanceService::class);
        $service->activate($contract, $this->admin);

        $fresh = $contract->fresh();
        $this->assertSame(AssetFinanceStatus::Active, $fresh->status);
        // P2: Konditionen eingefroren.
        $this->assertSame('400.00', (string) data_get($fresh->terms_snapshot, 'rate_amount'));
        // Ratenplan: 12 Monatsraten über die Laufzeit.
        $this->assertSame(12, $fresh->rateSchedules()->count());

        // Idempotent: erneute Generierung erzeugt keine Duplikate.
        $this->assertSame(0, $service->generateRateSchedule($fresh));
    }

    public function test_incoming_invoice_is_referenced_not_booked(): void {
        $contract = $this->createContract();
        $service = app(AssetFinanceService::class);
        $service->activate($contract, $this->admin);

        $document = \App\Models\Document::factory()->create(['organization_id' => $this->organization->id]);
        $invoice = IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id,
            'document_id' => $document->id,
            'sha256' => hash('sha256', 'demo'),
            'source' => 'upload',
            'received_at' => now(),
            'status' => 'approved',
            'summary' => ['gross' => 400.0],
        ]);

        $schedule = $contract->rateSchedules()->firstOrFail();
        $service->linkIncomingInvoice($schedule, $invoice);

        $this->assertSame('paid', (string) $schedule->fresh()->status);
        $this->assertSame($invoice->id, (int) $schedule->fresh()->incoming_einvoice_id);

        $projection = $service->projection($contract->fresh());
        $this->assertSame(4800.0, $projection['planned']);
        $this->assertSame(400.0, $projection['referenced']);
        $this->assertSame(4400.0, $projection['open']);
    }

    public function test_deadline_warning_notifies_and_overdue_is_marked_missed(): void {
        \Illuminate\Support\Facades\Notification::fake();
        $responsible = User::factory()->create(['organization_id' => $this->organization->id]);
        $contract = $this->createContract(['responsible_user_id' => $responsible->id]);

        // Innerhalb der Vorwarnzeit → Warnung.
        $warning = $contract->deadlines()->create([
            'organization_id' => $this->organization->id,
            'kind' => AssetFinanceDeadlineKind::Termination->value,
            'due_on' => now()->addDays(10)->toDateString(),
            'warn_days_before' => 30,
            'responsible_user_id' => $responsible->id,
        ]);
        // Abgelaufen → versäumt.
        $missed = $contract->deadlines()->create([
            'organization_id' => $this->organization->id,
            'kind' => AssetFinanceDeadlineKind::Insurance->value,
            'due_on' => now()->subDay()->toDateString(),
            'warn_days_before' => 5,
        ]);

        $service = app(AssetFinanceService::class);
        $service->scanDeadlines($this->organization);

        $this->assertSame('missed', (string) $missed->fresh()->status);
        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => \App\Enums\Notification\NotificationEvent::AssetFinanceDeadline->value,
            'subject_id' => $warning->id,
        ]);

        // Idempotent pro Frist.
        $service->scanDeadlines($this->organization);
        $this->assertSame(1, \App\Models\Notification\NotificationDispatchLog::query()
            ->where('event', \App\Enums\Notification\NotificationEvent::AssetFinanceDeadline->value)
            ->where('subject_id', $warning->id)
            ->where('stage', \App\Models\Notification\NotificationDispatchLog::STAGE_INITIAL)
            ->count());
    }

    public function test_usage_limit_records_actual_from_meter_reading_and_computes_overrun(): void {
        $contract = $this->createContract();
        $limit = $contract->usageLimits()->create([
            'organization_id' => $this->organization->id,
            'kind' => AssetFinanceUsageLimitKind::OperatingHours->value,
            'limit_value' => '1000.00',
            'period' => 'total',
            'overrun_fee_per_unit' => '2.0000',
        ]);

        \App\Models\MeterReading::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $this->asset->id,
            'read_at' => now(),
            'value' => '1250.0000',
            'unit' => 'h',
        ]);

        app(AssetFinanceService::class)->recordUsage($limit, $this->admin);

        $this->assertSame(250.0, $limit->fresh()->overrun());
        $projection = app(AssetFinanceService::class)->projection($contract->fresh());
        $this->assertSame(500.0, $projection['overruns'][0]['estimated_fee']);
    }

    public function test_end_process_extension_moves_contract_end(): void {
        $contract = $this->createContract();
        $service = app(AssetFinanceService::class);
        $service->activate($contract, $this->admin);

        $newEnd = now()->startOfMonth()->addMonths(17)->toDateString();
        $endProcess = $contract->endProcesses()->create([
            'organization_id' => $this->organization->id,
            'kind' => AssetFinanceEndKind::Extension->value,
            'status' => 'in_progress',
            'new_ends_on' => $newEnd,
        ]);

        $service->completeEndProcess($endProcess, $this->admin);

        $fresh = $contract->fresh();
        $this->assertSame(AssetFinanceStatus::Extended, $fresh->status);
        $this->assertSame($newEnd, $fresh->ends_on->toDateString());
    }

    public function test_end_process_return_and_close(): void {
        $contract = $this->createContract();
        $service = app(AssetFinanceService::class);
        $service->activate($contract, $this->admin);

        $this->actingAs($this->admin)->post(route('asset-finance.ends.store', $contract), [
            'kind' => AssetFinanceEndKind::Return->value,
            'condition_note' => 'Normale Gebrauchsspuren',
            'meter_value' => 45210,
            'follow_up_amount' => 350,
        ])->assertRedirect();

        $this->assertSame(AssetFinanceStatus::Ending, $contract->fresh()->status);

        $endProcess = $contract->endProcesses()->firstOrFail();
        $service->completeEndProcess($endProcess, $this->admin);
        $this->assertSame(AssetFinanceStatus::Returned, $contract->fresh()->status);

        $service->close($contract->fresh(), $this->admin);
        $this->assertSame(AssetFinanceStatus::Closed, $contract->fresh()->status);
    }

    public function test_option_exercise_requires_window(): void {
        $contract = $this->createContract();
        $service = app(AssetFinanceService::class);

        $expired = $contract->options()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'purchase',
            'exercisable_from' => now()->subMonths(2)->toDateString(),
            'exercisable_until' => now()->subMonth()->toDateString(),
            'amount' => '5000.00',
        ]);

        try {
            $service->exerciseOption($expired, $this->admin);
            $this->fail('Abgelaufene Option darf nicht ausübbar sein.');
        } catch (\RuntimeException) {
            // erwartet
        }

        $open = $contract->options()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'extension',
            'exercisable_from' => now()->subDay()->toDateString(),
            'exercisable_until' => now()->addMonth()->toDateString(),
        ]);
        $service->exerciseOption($open, $this->admin);
        $this->assertNotNull($open->fresh()->exercised_at);
    }

    public function test_finance_permission_gates_confidential_terms(): void {
        $contract = $this->createContract();

        // Teamleitung: sieht die Akte (viewAny/view via GF-Heuristik? nein —
        // Teamleitung hat keine assetFinance-Rechte) → 403.
        $lead = $this->userWithRole(\App\Enums\User\UserRole::Teamleitung->value);
        $this->actingAs($lead)->get(route('asset-finance.show', $contract))->assertForbidden();

        // Buchhaltung: volle Konditionssicht inkl. Aktivierung.
        $accounting = $this->userWithRole(\App\Enums\User\UserRole::Buchhaltung->value);
        $this->actingAs($accounting)->get(route('asset-finance.show', $contract))
            ->assertOk()
            ->assertSee('400,00');
        $this->actingAs($accounting)->post(route('asset-finance.activate', $contract))->assertRedirect();
        $this->assertSame(AssetFinanceStatus::Active, $contract->fresh()->status);

        // Geschäftsführung: view + finance (Konditionen), aber kein manage.
        $gf = $this->userWithRole(\App\Enums\User\UserRole::Geschaeftsfuehrung->value);
        $this->actingAs($gf)->get(route('asset-finance.show', $contract))->assertOk();
    }

    public function test_contract_can_be_created_via_http_with_investment_link(): void {
        $investment = \App\Models\Investments\InvestmentCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Neuer Radlader',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('asset-finance.store'), [
            'kind' => AssetFinanceKind::FinanceLease->value,
            'partner_name' => 'Leasingbank AG',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYears(3)->toDateString(),
            'payment_rhythm' => 'quarterly',
            'rate_amount' => 1200,
            'asset_ids' => [$this->asset->sqid],
            'investment_case_id' => $investment->sqid,
        ])->assertRedirect();

        $contract = AssetFinanceContract::query()->where('partner_name', 'Leasingbank AG')->firstOrFail();
        $this->assertDatabaseHas('investment_links', [
            'investment_case_id' => $investment->id,
            'linkable_type' => $contract->getMorphClass(),
            'linkable_id' => $contract->id,
        ]);
    }

    public function test_termination_requires_reason_and_status_model_blocks_invalid_transitions(): void {
        $contract = $this->createContract();
        app(AssetFinanceService::class)->activate($contract, $this->admin);

        $this->actingAs($this->admin)->post(route('asset-finance.terminate', $contract), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)->post(route('asset-finance.terminate', $contract), [
            'reason' => 'Betrieb stellt Baustellenbetrieb ein.',
        ])->assertRedirect();
        $this->assertSame(AssetFinanceStatus::Terminated, $contract->fresh()->status);

        // Gekündigt → aktivieren unzulässig.
        try {
            app(AssetFinanceService::class)->activate($contract->fresh(), $this->admin);
            $this->fail('Ungültiger Statuswechsel darf nicht möglich sein.');
        } catch (\RuntimeException) {
            // erwartet
        }
    }

    public function test_module_gating_blocks_without_license(): void {
        // module.asset_finance ist NUR im Enterprise-Tier enthalten.
        $proOrg = \App\Models\Organization::factory()->create(['plan' => 'pro']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($proOrg->id);
        $proAdmin = User::factory()->admin()->create(['organization_id' => $proOrg->id]);

        $this->actingAs($proAdmin)->get(route('asset-finance.index'))->assertStatus(423);
    }
}
