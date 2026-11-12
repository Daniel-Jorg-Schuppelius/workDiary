<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCommandReconcileTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainProviderCommandStatus, DomainSyncStatus};
use App\Enums\Notification\NotificationEvent;
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainProjection, DomainProviderCommand, DomainProviderConnection, DomainResellerAccount};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Platform\User;
use App\Services\Domain\DeadlineScans\DomainExpiryScan;
use App\Services\Domain\{DomainCommandReconciler, DomainSyncService};
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/** MVP-1614: Abgleich unklarer Provider-Befehle und Kosten-/Saldowarnung. */
class DomainCommandReconcileTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private DomainProviderConnection $connection;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actor = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function command(string $command, array $payload, array $attributes = []): DomainProviderCommand {
        return DomainProviderCommand::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'connection_id' => $this->connection->id,
            'command_id' => (string) Str::uuid(),
            'capability' => DomainCapabilityArea::Domains->value,
            'command' => $command,
            'target' => 'beispiel-shop.de',
            'payload' => $payload,
            'payload_hash' => hash('sha256', $command),
            'status' => DomainProviderCommandStatus::Unknown,
            'requested_by_user_id' => $this->actor->id,
            'dispatched_at' => now(),
            'attempts' => 1,
        ]);
    }

    private function projection(array $attributes = []): DomainProjection {
        return DomainProjection::factory()->create($attributes + [
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id,
            'external_domain' => 'beispiel-shop.de', 'domain_hash' => DomainProjection::hashFor('beispiel-shop.de'),
            'status' => 'ACTIVE', 'renewal_mode' => 'AUTORENEW', 'synced_at' => now(),
        ]);
    }

    private function reconcile(): int {
        return app(DomainCommandReconciler::class)->reconcileOpen($this->connection);
    }

    public function test_modify_domain_is_confirmed_or_flagged_as_conflict_from_status_domain(): void {
        $projection = $this->projection(['transferlock' => false]);
        $mode = $this->command('SetDomainRenewalMode', ['domain' => 'beispiel-shop.de', 'renewalmode' => 'AUTOEXPIRE'], ['capability' => DomainCapabilityArea::Renewal->value]);
        $lock = $this->command('ModifyDomain', ['domain' => 'beispiel-shop.de', 'transferlock' => '1'], ['capability' => DomainCapabilityArea::Transfer->value]);
        FakeDomainResellingTransport::fake([
            'StatusDomain' => "code=200\ndescription=ok\nproperty[renewalmode][0]=AUTOEXPIRE\nproperty[status][0]=ACTIVE\nproperty[status][1]=clientDeleteProhibited\nEOF\n",
        ]);

        $this->assertSame(2, $this->reconcile());

        $mode->refresh();
        $this->assertSame(DomainProviderCommandStatus::Confirmed, $mode->status);
        $this->assertNotNull($mode->reconciled_at);
        $this->assertSame('AUTOEXPIRE', $projection->fresh()?->renewal_mode?->value);
        $this->assertDatabaseHas('audit_logs', ['event' => 'domain.commandReconciled', 'auditable_id' => $mode->id]);

        $lock->refresh();
        $this->assertSame(DomainProviderCommandStatus::Conflict, $lock->status);
        $this->assertSame(__('domain.reconciliation.transferlock_differs', ['provider' => '0', 'expected' => '1']), $lock->reconciliationText());
        $this->assertFalse((bool) $projection->fresh()?->transferlock, 'Ein Konflikt überschreibt die Projektion nicht.');
    }

    public function test_add_domain_is_failed_when_missing_and_confirmed_with_projection_when_present(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $missing = $this->command('AddDomain', ['domain' => 'beispiel-shop.de', 'renewalmode' => 'AUTORENEW'], ['customer_id' => $customer->id]);
        FakeDomainResellingTransport::fake(['StatusDomain' => "code=545\ndescription=Object not found\nEOF\n"]);
        $this->reconcile();
        $this->assertSame(DomainProviderCommandStatus::Failed, $missing->fresh()?->status);

        $present = $this->command('AddDomain', ['domain' => 'beispiel-shop.de', 'renewalmode' => 'AUTORENEW'], ['customer_id' => $customer->id, 'preflight_snapshot' => ['provider_user' => 'sub1']]);
        FakeDomainResellingTransport::fake(['StatusDomain' => FakeDomainResellingTransport::properties([['renewalmode' => 'AUTORENEW', 'status' => 'ACTIVE']])]);
        $this->reconcile();
        $this->assertSame(DomainProviderCommandStatus::Confirmed, $present->fresh()?->status);
        $this->assertSame($customer->id, DomainProjection::query()->where('external_domain', 'beispiel-shop.de')->sole()->customer_id);
    }

    public function test_incomplete_answers_and_unreadable_commands_stay_unclear(): void {
        $renew = $this->command('SetDomainRenewalMode', ['domain' => 'beispiel-shop.de', 'renewalmode' => 'AUTORENEW']);
        $transfer = $this->command('TransferDomain', ['domain' => 'beispiel-shop.de', 'action' => 'REQUEST', 'auth' => '[redacted]']);
        FakeDomainResellingTransport::fake(['StatusDomain' => "code=200\ndescription=ok\nproperty[renewalmode][0]=AUTORENEW\n"]);

        $this->assertSame(0, $this->reconcile());
        $this->assertSame(DomainProviderCommandStatus::Unknown, $renew->fresh()?->status);
        $this->assertStringContainsString('incomplete', (string) $renew->fresh()?->reconciliation_note);
        $this->assertSame(DomainProviderCommandStatus::Unknown, $transfer->fresh()?->status);
        $this->assertSame(__('domain.reconciliation.manual_check'), $transfer->fresh()?->reconciliationText());
    }

    public function test_unclear_command_is_resolved_manually_with_reason_and_audit(): void {
        FakeDomainResellingTransport::fake([]);
        $this->projection();
        $transfer = $this->command('TransferDomain', ['domain' => 'beispiel-shop.de', 'action' => 'REQUEST'], ['capability' => DomainCapabilityArea::Transfer->value]);
        $this->reconcile();
        $approver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $approver->givePermissionTo([Permission::DomainViewAny->value, Permission::DomainView->value, Permission::DomainDangerousApprove->value]);
        $domain = DomainProjection::query()->sole();

        $this->actingAs($approver)->get(route('domains.show', $domain))
            ->assertOk()
            ->assertSee(__('domain.reconciliation.manual_check'))
            ->assertSee(route('domains.commands.resolve.edit', $transfer), false);
        $this->actingAs($approver)->get(route('domains.commands.resolve.edit', $transfer))->assertOk();
        $this->actingAs($approver)->post(route('domains.commands.resolve', $transfer), ['outcome' => 'confirmed'])->assertSessionHasErrors('reconciliation_note');
        $this->actingAs($approver)->post(route('domains.commands.resolve', $transfer), ['outcome' => 'confirmed', 'reconciliation_note' => 'Im Anbieterportal als abgeschlossen sichtbar'])
            ->assertSessionHasNoErrors();

        $transfer->refresh();
        $this->assertSame(DomainProviderCommandStatus::Confirmed, $transfer->status);
        $this->assertStringContainsString('Im Anbieterportal als abgeschlossen sichtbar', (string) $transfer->reconciliationText());
        $this->assertDatabaseHas('audit_logs', ['event' => 'domain.commandResolved', 'auditable_id' => $transfer->id]);
        $this->actingAs($approver)->post(route('domains.commands.resolve', $transfer), ['outcome' => 'failed', 'reconciliation_note' => 'x'])->assertNotFound();

        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo([Permission::DomainViewAny->value, Permission::DomainView->value]);
        $other = $this->command('TransferDomain', ['domain' => 'beispiel-shop.de']);
        $this->actingAs($viewer)->post(route('domains.commands.resolve', $other), ['outcome' => 'confirmed', 'reconciliation_note' => 'x'])->assertForbidden();
    }

    /** MVP-1620: der Abgleichsstand der Domain folgt den offenen Befehlen, der Abgleich überschreibt ihn nicht. */
    public function test_projection_sync_status_follows_open_commands(): void {
        $projection = $this->projection(['sync_status' => DomainSyncStatus::Current]);
        $command = $this->command('ModifyDomain', ['domain' => 'beispiel-shop.de', 'transferlock' => '1'], ['status' => DomainProviderCommandStatus::Pending]);
        $this->assertSame(DomainSyncStatus::Pending, $projection->fresh()?->sync_status);

        $command->forceFill(['status' => DomainProviderCommandStatus::Unknown])->save();
        $this->assertSame(DomainSyncStatus::Unknown, $projection->fresh()?->sync_status);

        FakeDomainResellingTransport::fake(['StatusDomain' => "code=200\ndescription=ok\nproperty[status][0]=ACTIVE\nEOF\n"]);
        app(DomainSyncService::class)->refreshDomain($projection->fresh() ?? $projection);
        $this->assertSame(DomainSyncStatus::Unknown, $projection->fresh()?->sync_status, 'Ein offener Befehl bleibt sichtbar.');

        $command->forceFill(['status' => DomainProviderCommandStatus::Conflict])->save();
        $this->assertSame(DomainSyncStatus::Conflict, $projection->fresh()?->sync_status);
        $this->actingAs($this->orgAdmin())->get(route('domains.index', ['show' => 'sync_issues']))->assertOk()->assertSee('beispiel-shop.de');

        app(DomainCommandReconciler::class)->resolveManually($command, true, 'Im Portal geprüft', $this->actor);
        $this->assertSame(DomainSyncStatus::Stale, $projection->fresh()?->sync_status, 'Erledigt heißt: neu lesen.');

        app(DomainSyncService::class)->refreshDomain($projection->fresh() ?? $projection);
        $this->assertSame(DomainSyncStatus::Current, $projection->fresh()?->sync_status);
    }

    public function test_renewal_cost_and_balance_risk_notify_admins_once_per_cycle(): void {
        $this->travelTo('2026-11-11 09:00:00');
        $this->orgAdmin();
        $this->organization->forceFill(['settings' => array_merge((array) $this->organization->settings, ['domain' => ['renewal_cost_threshold' => '100', 'balance_threshold' => '50']])])->save();
        $account = DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_user' => 'sub1', 'currency' => 'EUR', 'balance_snapshot' => '80.00', 'credit_snapshot' => '0.00', 'active' => true]);
        $expensive = $this->projection(['reseller_account_id' => $account->id, 'renewal_price' => '120.00', 'renewal_currency' => 'EUR', 'accounting_at' => '2026-11-20']);
        $cheap = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_domain' => 'klein.de', 'domain_hash' => DomainProjection::hashFor('klein.de'),
            'reseller_account_id' => $account->id, 'renewal_price' => '15.00', 'renewal_currency' => 'EUR', 'accounting_at' => '2026-11-25', 'renewal_mode' => 'AUTORENEW', 'synced_at' => now(),
        ]);
        DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $this->connection->id, 'external_domain' => 'auslauf.de', 'domain_hash' => DomainProjection::hashFor('auslauf.de'),
            'reseller_account_id' => $account->id, 'renewal_price' => '500.00', 'renewal_currency' => 'EUR', 'accounting_at' => '2026-11-22', 'renewal_mode' => 'AUTODELETE', 'synced_at' => now(),
        ]);

        $scan = fn (): int => app(DomainExpiryScan::class)->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30));
        $this->assertGreaterThan(0, $scan());

        $costs = NotificationDispatchLog::query()->where('event', NotificationEvent::DomainRenewalCostHigh->value)->pluck('subject_id')->map('intval')->all();
        $this->assertSame([$expensive->id], $costs, 'Nur die Verlängerung ab der Schwelle; eine auslaufende Domain kostet nichts.');
        $this->assertNotContains($cheap->id, $costs);
        $this->assertSame(1, NotificationDispatchLog::query()->where('event', NotificationEvent::DomainBalanceRisk->value)->where('subject_id', $account->id)->count(), '80 € decken 135 € nicht.');

        $scan();
        $this->assertSame(1, NotificationDispatchLog::query()->where('event', NotificationEvent::DomainRenewalCostHigh->value)->count(), 'Je Abbuchungstermin einmal.');
        $this->assertSame(1, NotificationDispatchLog::query()->where('event', NotificationEvent::DomainBalanceRisk->value)->count(), 'Je Woche einmal.');

        // Gedeckt und über dem Mindestguthaben: keine Meldung.
        NotificationDispatchLog::query()->delete();
        $account->forceFill(['balance_snapshot' => '200.00'])->save();
        $this->organization->forceFill(['settings' => array_merge((array) $this->organization->settings, ['domain' => []])])->save();
        $scan();
        $this->assertSame(0, NotificationDispatchLog::query()->where('event', NotificationEvent::DomainBalanceRisk->value)->count());
        $this->assertSame(0, NotificationDispatchLog::query()->where('event', NotificationEvent::DomainRenewalCostHigh->value)->count(), 'Ohne Schwelle keine Kostenmeldung.');
    }
}
