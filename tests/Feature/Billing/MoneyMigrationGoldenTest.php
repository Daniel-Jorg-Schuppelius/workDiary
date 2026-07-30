<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MoneyMigrationGoldenTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Golden-Vergleich der Money-Migration (W1.2): Die Übertrags-Kette muss nach
 * der Umstellung von float+round auf {@see \CommonToolkit\ValueObjects\Money}
 * exakt die Werte der float-Fassung liefern — inklusive Rundungskante
 * (1,5 h × 33,33 = 49,995 → 50,00) und float-Summenfalle (10,10 + 20,20 +
 * 30,30 = 60,599999… → 60,60).
 */
class MoneyMigrationGoldenTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    private CustomerBillingAgreement $agreement;

    private CustomerAccountStatementService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'workdays_per_week' => 6,
            'opening_balance' => 100.05,
            'opening_balance_date' => '2026-01-31',
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'day_type' => 'weekday',
            'hourly_rate' => 33.33,
        ]);
        $this->service = app(CustomerAccountStatementService::class);
    }

    private function makeEntry(string $day, string $from, string $to): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => $day . ' ' . $from,
            'ended_at' => $day . ' ' . $to,
        ]);
    }

    public function test_statement_chain_matches_float_era_values(): void {
        // Drei 90-Minuten-Einträge à 33,33 €/h → je 49,995 → 50,00 (HalfUp).
        $this->makeEntry('2026-02-10', '10:00:00', '11:30:00');
        $this->makeEntry('2026-02-11', '10:00:00', '11:30:00');
        $this->makeEntry('2026-02-12', '10:00:00', '11:30:00');

        foreach (['10.10', '20.20', '30.30'] as $amount) {
            $this->service->bookPayment($this->agreement, ['paid_on' => '2026-02-15', 'amount' => $amount]);
        }

        $feb = $this->agreement->statements()->where('year', 2026)->where('month', 2)->firstOrFail();
        $this->assertSame('100.05', $feb->carry_in?->getAmount());
        $this->assertSame('150.00', $feb->gross_value?->getAmount());
        $this->assertSame('60.60', $feb->payments_total?->getAmount());
        $this->assertSame('189.45', $feb->balance?->getAmount()); // 100,05 + 150,00 − 60,60
        $this->assertSame(270, $feb->total_minutes);

        // Leerer Folgemonat schleppt den Saldo centgenau weiter.
        $mar = $this->agreement->statements()->where('year', 2026)->where('month', 3)->firstOrFail();
        $this->assertSame('189.45', $mar->carry_in?->getAmount());
        $this->assertSame('189.45', $mar->balance?->getAmount());
    }

    public function test_month_data_and_locked_snapshot_keep_float_boundary_values(): void {
        $this->makeEntry('2026-02-10', '10:00:00', '11:30:00');
        $this->makeEntry('2026-02-11', '10:00:00', '11:30:00');
        $this->service->bookPayment($this->agreement, ['paid_on' => '2026-02-15', 'amount' => '10.10']);

        $data = $this->service->monthData($this->agreement, 2026, 2);
        $this->assertSame([50.0, 50.0], array_column($data['rows'], 'amount'));
        $this->assertSame([10.1], array_column($data['payments'], 'amount'));
        $this->assertSame(100.0, $data['by_category'][0]['amount']);

        // Abschluss friert dieselben Werte im Snapshot ein; DB-Spalten bleiben
        // kanonische Dezimalstrings.
        $feb = $this->service->close(
            $this->agreement->statements()->where('year', 2026)->where('month', 2)->firstOrFail(),
            $this->user,
        );
        // JSON-Grenze: json_encode kürzt 50.0 zu 50 (wie in der float-Fassung) —
        // daher Wertevergleich statt Typvergleich.
        $this->assertEquals([50.0, 50.0], array_column((array) $feb->totals['rows'], 'amount'));
        $this->assertSame('189.95', $feb->balance?->getAmount()); // 100,05 + 100,00 − 10,10
    }
}
