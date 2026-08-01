<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LateTimeEntryDetectorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, Invoice, Project, TimeEntry, User};
use App\Services\Invoicing\LateTimeEntryDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachzügler-Erkennung (MVP-461): offene Zeiten in bereits abgerechneten
 * Zeiträumen des Kunden.
 */
class LateTimeEntryDetectorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    private LateTimeEntryDetector $detector;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->detector = app(LateTimeEntryDetector::class);
    }

    private function invoiceWithServiceDate(string $serviceDate, string $status = Invoice::STATUS_ISSUED, ?TimeEntry $timeEntry = null): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-' . fake()->unique()->numerify('####'),
            'status' => $status,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Leistung',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '19.00',
            'position' => 1,
            'service_date' => $serviceDate,
            'time_entry_id' => $timeEntry?->id,
        ]);

        return $invoice;
    }

    private function openEntry(string $date): TimeEntry {
        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'exported' => false,
            'minutes' => 60,
            'date' => $date,
        ]);
    }

    public function test_latest_billed_service_date_ignores_draft_and_cancelled(): void {
        $this->invoiceWithServiceDate('2026-07-10');
        $this->invoiceWithServiceDate('2026-07-20', Invoice::STATUS_DRAFT);
        $this->invoiceWithServiceDate('2026-07-25', Invoice::STATUS_CANCELLED);

        $latest = $this->detector->latestBilledServiceDate($this->customer);

        $this->assertNotNull($latest);
        $this->assertSame('2026-07-10', $latest->toDateString());
    }

    public function test_latest_billed_service_date_is_null_without_invoices(): void {
        $this->assertNull($this->detector->latestBilledServiceDate($this->customer));
    }

    public function test_detect_flags_entries_on_or_before_latest_billed_date(): void {
        $this->invoiceWithServiceDate('2026-07-10');

        $late = $this->openEntry('2026-07-05');
        $onEdge = $this->openEntry('2026-07-10');
        $fresh = $this->openEntry('2026-07-15');

        $detected = $this->detector->detect(
            collect([$late, $onEdge, $fresh]),
            $this->customer,
        );

        $this->assertEqualsCanonicalizing([$late->id, $onEdge->id], $detected->pluck('id')->all());
    }

    public function test_project_precision_uses_source_time_entries(): void {
        // Rechnung entstand aus Zeiten eines ANDEREN Projekts desselben Kunden.
        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $billedEntry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $otherProject->id,
            'user_id' => $this->user->id,
            'billable' => true,
            'exported' => true,
            'date' => '2026-07-10',
        ]);
        $this->invoiceWithServiceDate('2026-07-10', Invoice::STATUS_ISSUED, $billedEntry);

        $this->assertNull($this->detector->latestBilledServiceDate($this->customer, $this->project));
        $this->assertNotNull($this->detector->latestBilledServiceDate($this->customer, $otherProject));
    }

    public function test_count_late_in_query_matches_detect(): void {
        $this->invoiceWithServiceDate('2026-07-10');
        $this->openEntry('2026-07-05');
        $this->openEntry('2026-07-15');

        $count = $this->detector->countLateInQuery(
            TimeEntry::query()->where('billable', true)->where('exported', false)
        );

        $this->assertSame(1, $count);
    }
}
