<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceMovementsWidgetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Dashboard\Widgets\FinanceMovementsWidget;
use App\Enums\Finance\{MatchStatus, TransactionDirection};
use App\Enums\User\UserRole;
use App\Models\Finance\{BankStatement, BankTransaction};
use App\Models\Platform\PluginSetting;
use App\Plugins\Firefly\Enums\{FireflyMatchStatus, FireflyTransactionKind};
use App\Plugins\Firefly\FireflyPlugin;
use App\Plugins\Firefly\Http\Controllers\FireflyAdminController;
use App\Plugins\Firefly\Models\FireflyTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Kachel „Finanzbewegungen“ (MVP-1617, F9-01). */
class FinanceMovementsWidgetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function firefly(string $id, FireflyMatchStatus $status, string $counterparty, FireflyTransactionKind $kind = FireflyTransactionKind::Deposit): FireflyTransaction {
        return FireflyTransaction::query()->forceCreate([
            'organization_id' => $this->organization->id, 'remote_journal_id' => $id, 'kind' => $kind,
            'booked_on' => CarbonImmutable::today()->subDays(2)->toDateString(), 'booked_amount' => '42.50', 'currency' => 'EUR',
            'counterparty_name' => $counterparty, 'match_status' => $status,
        ]);
    }

    public function test_tile_counts_open_assignments_per_source_and_lists_latest_movements(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => FireflyPlugin::ID, 'enabled' => true, 'settings' => []]);
        $this->firefly('1', FireflyMatchStatus::Unmatched, 'Zephyr Kundin');
        $this->firefly('2', FireflyMatchStatus::Ambiguous, 'Zephyr Zweifel');
        $this->firefly('3', FireflyMatchStatus::Matched, 'Zephyr Bezahlt', FireflyTransactionKind::Withdrawal);
        $statement = BankStatement::factory()->create(['organization_id' => $this->organization->id]);
        BankTransaction::factory()->create(['organization_id' => $this->organization->id, 'bank_statement_id' => $statement->id, 'counterparty_name' => 'Zephyr Bank', 'match_status' => MatchStatus::Unmatched]);
        BankTransaction::factory()->create(['organization_id' => $this->organization->id, 'bank_statement_id' => $statement->id, 'counterparty_name' => 'Zephyr Miete', 'direction' => TransactionDirection::Debit, 'match_status' => MatchStatus::Matched]);
        $admin = $this->orgAdmin();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->actingAs($admin);
        $widget = app(FinanceMovementsWidget::class);

        $this->assertTrue($widget->availableFor($admin));
        $html = (string) $widget->render($admin)->render();

        $this->assertStringContainsString(e(__('dashboard.widget.finance_movements.unassigned', ['source' => 'Firefly III'])), $html);
        $this->assertStringContainsString(e(route('admin.firefly.index', ['match' => FireflyAdminController::OPEN])), $html);
        $this->assertStringContainsString(e(route('finance.reconciliation.index')), $html);
        foreach (['Zephyr Kundin', 'Zephyr Zweifel', 'Zephyr Bezahlt', 'Zephyr Bank', 'Zephyr Miete'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
        $this->assertMatchesRegularExpression('/text-error[^>]*>-42,50/', $html);
    }

    public function test_firefly_list_filters_open_assignments(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => FireflyPlugin::ID, 'enabled' => true, 'settings' => []]);
        $this->firefly('1', FireflyMatchStatus::Unmatched, 'Zephyr Kundin');
        $this->firefly('2', FireflyMatchStatus::Ambiguous, 'Zephyr Zweifel');
        $this->firefly('3', FireflyMatchStatus::Matched, 'Zephyr Bezahlt');

        $this->actingAs($this->orgAdmin())->get(route('admin.firefly.index', ['match' => FireflyAdminController::OPEN]))
            ->assertOk()->assertSee('Zephyr Kundin')->assertSee('Zephyr Zweifel')->assertDontSee('Zephyr Bezahlt');
    }

    public function test_settled_firefly_movement_leaves_tile_work_queue_and_open_filter(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => FireflyPlugin::ID, 'enabled' => true, 'settings' => []]);
        $fee = $this->firefly('1', FireflyMatchStatus::Unmatched, 'Zephyr Gebühr', FireflyTransactionKind::Withdrawal);
        $this->firefly('2', FireflyMatchStatus::Unmatched, 'Zephyr Kundin');
        $admin = $this->orgAdmin();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->actingAs($admin)->get(route('admin.firefly.transactions.settle.form', $fee))->assertOk()->assertSee(__('firefly::firefly.settle.title'));
        $this->actingAs($admin)->post(route('admin.firefly.transactions.settle', $fee), [])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('admin.firefly.transactions.settle', $fee), ['note' => 'Kontoführungsgebühr ohne Beleg'])
            ->assertSessionHas('success', __('firefly::firefly.settle.done'));

        $fee->refresh();
        $this->assertNotNull($fee->settled_at);
        $this->assertSame((int) $admin->id, (int) $fee->settled_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'firefly.transaction_settled', 'auditable_id' => $fee->id]);
        $this->assertSame(1, app(\App\Plugins\Firefly\Services\FireflyFinanceMovements::class)->summary($this->organization, CarbonImmutable::today()->subDays(30), 5)->unassigned);
        $this->actingAs($admin)->get(route('admin.firefly.index', ['match' => FireflyAdminController::OPEN]))
            ->assertOk()->assertSee('Zephyr Kundin')->assertDontSee('Zephyr Gebühr');
        $this->actingAs($admin)->get(route('admin.firefly.index', ['match' => FireflyMatchStatus::Unmatched->value]))
            ->assertOk()->assertDontSee('Zephyr Gebühr');
        $this->actingAs($admin)->get(route('admin.firefly.index', ['match' => FireflyAdminController::SETTLED]))
            ->assertOk()->assertSee('Zephyr Gebühr')->assertDontSee('Zephyr Kundin');
        $items = iterator_to_array(app(\App\Plugins\Firefly\Services\FireflyWorkItemSource::class)->items($admin, $this->organization), false);
        $this->assertSame(1, $items[0]->count);

        // Der Abgleich setzt nur die Zuordnung neu, nie die Erledigung; Wieder öffnen zählt sie zurück.
        $fee->forceFill(['match_status' => FireflyMatchStatus::Ambiguous])->save();
        $this->assertNotNull($fee->fresh()->settled_at);
        $this->actingAs($admin)->post(route('admin.firefly.transactions.reopen', $fee))->assertSessionHas('success');
        $this->assertNull($fee->fresh()->settled_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'firefly.transaction_reopened', 'auditable_id' => $fee->id]);

        $foreign = \App\Models\Platform\Organization::factory()->create();
        $other = FireflyTransaction::query()->withoutGlobalScopes()->forceCreate([
            'organization_id' => $foreign->id, 'remote_journal_id' => '9', 'kind' => FireflyTransactionKind::Deposit,
            'booked_on' => CarbonImmutable::today()->toDateString(), 'booked_amount' => '1.00', 'currency' => 'EUR', 'match_status' => FireflyMatchStatus::Unmatched,
        ]);
        $this->actingAs($admin)->post(route('admin.firefly.transactions.settle', $other), ['note' => 'Fremde Bewegung'])->assertNotFound();
    }

    public function test_tile_needs_a_source_the_viewer_may_open(): void {
        $worker = $this->userWithRole(UserRole::User->value);

        $this->assertFalse(app(FinanceMovementsWidget::class)->availableFor($worker));
    }
}
