<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationToleranceSettingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Expense\PaymentMethod;
use App\Enums\Finance\{AllocationKind, MatchStatus, TransactionDirection};
use App\Models\Finance\{BankStatement, BankTransaction};
use App\Models\Platform\User;
use App\Models\Travel\Expense;
use App\Services\Finance\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623 (gl-137): Cent- und Skonto-Toleranz des Zahlungsabgleichs sind
 * Einstellungen der Organisation (Feature 045); ohne Angabe gelten die
 * bisherigen Werte 0,02 und 3 %.
 */
final class ReconciliationToleranceSettingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_defaults_match_the_former_constants(): void {
        $matching = app(MatchingService::class);

        $this->assertSame(0.02, $matching->centTolerance());
        $this->assertSame(3.0, $matching->skontoPercent());
        $this->assertSame(AllocationKind::Payment, $matching->kindForInvoice(100.01, 100.0));
        $this->assertSame(AllocationKind::Payment, $matching->kindForInvoice(97.0, 100.0));
        $this->assertSame(AllocationKind::Partial, $matching->kindForInvoice(96.5, 100.0));
    }

    public function test_organization_setting_changes_the_tolerances(): void {
        $this->organization->update(['settings' => ['finance' => ['reconciliation' => ['cent_tolerance' => '0', 'skonto_percent' => '2']]]]);
        $matching = app(MatchingService::class);

        // Ohne Cent-Toleranz ist ein Cent zu viel eine Überzahlung.
        $this->assertSame(AllocationKind::Overpayment, $matching->kindForInvoice(100.01, 100.0));
        $this->assertSame(AllocationKind::Partial, $matching->kindForInvoice(97.0, 100.0));
        $this->assertSame(AllocationKind::Payment, $matching->kindForInvoice(98.0, 100.0));
    }

    /** gl-135: Erstattet wird nur privat Verauslagtes; Barkasse und Firmenkarte sind Firmenmittel — wie die Kennzahl „Erstattungsfähig“. */
    public function test_only_privately_paid_expenses_are_suggested_for_reimbursement(): void {
        $this->assertSame([PaymentMethod::PrivatePaid->value], PaymentMethod::reimbursableValues());
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $expense = fn (PaymentMethod $method): Expense => Expense::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $admin->id,
            'amount_net' => '100.00',
            'tax_rate' => '19.00',
            'amount_gross' => '119.00',
            'currency' => 'EUR',
            'payment_method' => $method->value,
            'reimbursed_at' => null,
        ]);
        $private = $expense(PaymentMethod::PrivatePaid);
        $expense(PaymentMethod::Cash);
        $expense(PaymentMethod::CompanyCard);
        $transaction = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->organization->id])->id,
            'amount' => '119.00',
            'currency' => 'EUR',
            'direction' => TransactionDirection::Debit,
            'match_status' => MatchStatus::Unmatched,
        ]);

        $suggested = array_map(static fn (array $suggestion): int => (int) $suggestion['target']->getKey(), app(MatchingService::class)->suggestFor($transaction));

        $this->assertSame([(int) $private->id], $suggested);

        $this->actingAs($admin)->get(route('expenses.index'))->assertOk()->assertViewHas('totals', static fn (array $totals): bool => $totals['reimbursement_pending'] === 119.0);
    }

    public function test_organization_form_saves_and_validates_the_tolerances(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $base = ['name' => $this->organization->name, 'plan' => $this->organization->plan, 'locale' => 'de', 'timezone' => 'Europe/Berlin', 'is_active' => 1];

        $this->actingAs($admin)->get(route('admin.organizations.edit', $this->organization))
            ->assertOk()
            ->assertSee('name="settings[finance][reconciliation][cent_tolerance]"', false)
            ->assertSee('name="settings[finance][reconciliation][skonto_percent]"', false);

        $this->actingAs($admin)->put(route('admin.organizations.update', $this->organization), $base + [
            'settings' => ['finance' => ['reconciliation' => ['cent_tolerance' => '0.05', 'skonto_percent' => '2.5']]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.05', (string) data_get($this->organization->fresh()->settings, 'finance.reconciliation.cent_tolerance'));
        $this->assertSame('2.5', (string) data_get($this->organization->fresh()->settings, 'finance.reconciliation.skonto_percent'));

        $this->actingAs($admin)->put(route('admin.organizations.update', $this->organization), $base + [
            'settings' => ['finance' => ['reconciliation' => ['cent_tolerance' => '5', 'skonto_percent' => '25']]],
        ])->assertSessionHasErrors(['settings.finance.reconciliation.cent_tolerance', 'settings.finance.reconciliation.skonto_percent']);
    }
}
