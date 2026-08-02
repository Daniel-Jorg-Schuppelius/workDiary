<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Kundenkonto-Monatsabrechnung — Übertrags-Kette (Excel-Analogie
 * Gesamt/Abgerechnet/Vormonat/Offen), Abschluss-/Wiedereröffnungs-Regeln,
 * Nachtrags-Erkennung. 2026-03-01 = Sonntag.
 */
class StatementServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    private CustomerBillingAgreement $agreement;

    private CustomerBillingRate $weekdayRate;

    private CustomerAccountStatementService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'workdays_per_week' => 6,
            'opening_balance' => 100.00,
            'opening_balance_date' => '2026-01-31',
        ]);
        $this->weekdayRate = CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'day_type' => 'weekday',
            'hourly_rate' => 20.00,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'day_type' => 'weekend',
            'hourly_rate' => 30.00,
        ]);
        $this->service = app(CustomerAccountStatementService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function makeEntry(string $day, int $hours = 2, array $attributes = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => $day . ' 10:00:00',
            'ended_at' => $day . ' ' . (10 + $hours) . ':00:00',
        ], $attributes));
    }

    private function statement(int $year, int $month): \App\Models\Billing\CustomerBillingStatement {
        return $this->agreement->statements()->where('year', $year)->where('month', $month)->firstOrFail();
    }

    public function test_carry_chain_over_three_months_with_opening_balance(): void {
        $this->makeEntry('2026-02-10');                      // 2h Werktag à 20 = 40
        $this->makeEntry('2026-03-01', 1);                   // 1h Sonntag à 30 = 30
        $this->service->bookPayment($this->agreement, ['paid_on' => '2026-02-15', 'amount' => 30.00]);

        $feb = $this->statement(2026, 2);
        $this->assertSame('100.00', $feb->carry_in?->getAmount());
        $this->assertSame('40.00', $feb->gross_value?->getAmount());
        $this->assertSame('30.00', $feb->payments_total?->getAmount());
        $this->assertSame('110.00', $feb->balance?->getAmount());
        $this->assertSame(120, $feb->total_minutes);

        $mar = $this->statement(2026, 3);
        $this->assertSame('110.00', $mar->carry_in?->getAmount());
        $this->assertSame('30.00', $mar->gross_value?->getAmount());
        $this->assertSame('140.00', $mar->balance?->getAmount());

        // Leere Folgemonate schleppen den Saldo unverändert weiter.
        $apr = $this->statement(2026, 4);
        $this->assertSame('140.00', $apr->carry_in?->getAmount());
        $this->assertSame('140.00', $apr->balance?->getAmount());
    }

    public function test_close_requires_oldest_open_month_and_exports_entries(): void {
        $entry = $this->makeEntry('2026-02-10');
        $this->makeEntry('2026-03-02');
        $this->service->recalculateOpen($this->agreement);

        $mar = $this->statement(2026, 3);
        try {
            $this->service->close($mar, $this->user);
            $this->fail('Abschluss eines jüngeren Monats vor dem älteren muss scheitern.');
        } catch (ValidationException) {
            // erwartet
        }

        $feb = $this->service->close($this->statement(2026, 2), $this->user);

        $this->assertTrue($feb->locked);
        $this->assertContains($entry->id, (array) $feb->totals['entry_ids']);
        $this->assertNotEmpty($feb->totals['rows']);
        $this->assertTrue($entry->fresh()->exported);

        // Danach ist der März der älteste offene Monat und abschließbar.
        $mar = $this->service->close($this->statement(2026, 3), $this->user);
        $this->assertTrue($mar->locked);
    }

    public function test_reopen_only_youngest_locked_month(): void {
        $entry = $this->makeEntry('2026-02-10');
        $this->makeEntry('2026-03-02');
        $this->service->recalculateOpen($this->agreement);
        $this->service->close($this->statement(2026, 2), $this->user);
        $this->service->close($this->statement(2026, 3), $this->user);

        try {
            $this->service->reopen($this->statement(2026, 2), $this->user);
            $this->fail('Wiedereröffnung unter einem jüngeren gesperrten Monat muss scheitern.');
        } catch (ValidationException) {
            // erwartet
        }

        $mar = $this->service->reopen($this->statement(2026, 3), $this->user);
        $this->assertFalse($mar->locked);
        $this->assertNull($mar->totals);

        $feb = $this->service->reopen($this->statement(2026, 2), $this->user);
        $this->assertFalse($feb->locked);
        $this->assertFalse($entry->fresh()->exported);
    }

    public function test_payment_into_locked_month_is_rejected(): void {
        $this->makeEntry('2026-02-10');
        $this->service->recalculateOpen($this->agreement);
        $this->service->close($this->statement(2026, 2), $this->user);

        $this->expectException(ValidationException::class);
        $this->service->bookPayment($this->agreement, ['paid_on' => '2026-02-20', 'amount' => 10.00]);
    }

    public function test_reapply_rates_respects_manual_overrides(): void {
        $agreementEntry = $this->makeEntry('2026-02-10');
        $manualEntry = $this->makeEntry('2026-02-11', 2, ['hourly_rate' => 50.00]);
        $this->assertSame('20.00', $agreementEntry->fresh()->hourly_rate?->getAmount());

        $this->weekdayRate->update(['hourly_rate' => 25.00]);
        $this->service->reapplyRates($this->agreement);

        $this->assertSame('25.00', $agreementEntry->fresh()->hourly_rate?->getAmount());
        $this->assertSame('50.00', $manualEntry->fresh()->hourly_rate?->getAmount());

        $feb = $this->statement(2026, 2);
        $this->assertSame('150.00', $feb->gross_value?->getAmount()); // 2h×25 + 2h×50
    }

    public function test_reapply_rates_values_entries_recorded_before_the_agreement_existed(): void {
        // Bestandszeit ohne jeden Satz — so sieht ein Eintrag aus, der erfasst
        // wurde, bevor es die Kondition gab: er stünde sonst dauerhaft mit
        // 0,00 € im Saldo, weil ohne dirty-Feld kein Snapshot neu rechnet.
        $entry = $this->makeEntry('2026-02-10');
        $entry->forceFill(['hourly_rate' => null, 'customer_billing_rate_id' => null, 'rate' => 0])->saveQuietly();
        $this->assertSame('0.00', $entry->fresh()->rate?->getAmount());

        $this->service->reapplyRates($this->agreement);

        $this->assertSame('20.00', $entry->fresh()->hourly_rate?->getAmount());
        $this->assertSame('40.00', $entry->fresh()->rate?->getAmount());
        $this->assertSame('40.00', $this->statement(2026, 2)->gross_value?->getAmount());
    }

    public function test_stray_entries_in_locked_month_are_reported_without_changing_balance(): void {
        $this->makeEntry('2026-02-10');
        $this->service->recalculateOpen($this->agreement);
        $feb = $this->service->close($this->statement(2026, 2), $this->user);
        $lockedBalance = $feb->balance?->getAmount();

        $stray = $this->makeEntry('2026-02-20');
        $warnings = $this->service->recalculateOpen($this->agreement);

        $this->assertSame([$stray->id], array_column($warnings['stray_entries'], 'id'));
        $this->assertSame($lockedBalance, $this->statement(2026, 2)->balance?->getAmount());
    }
}
