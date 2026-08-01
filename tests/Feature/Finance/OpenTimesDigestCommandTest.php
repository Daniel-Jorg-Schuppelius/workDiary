<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenTimesDigestCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, Invoice, Project, TimeEntry, User};
use App\Notifications\Finance\OpenTimesDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Offene-Zeiten-Digest (MVP-461): Benachrichtigung nur bei Befund, Empfänger
 * sind Nutzer mit Org-weiter Zeit-Sicht.
 */
class OpenTimesDigestCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private User $worker;

    private Project $project;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_no_notification_without_findings(): void {
        Notification::fake();

        // Offener Eintrag, aber weder Nachzügler noch überfällig.
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->worker->id,
            'billable' => true,
            'exported' => false,
            'date' => now()->subDays(2)->toDateString(),
        ]);

        $this->artisan('finance:open-times-digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_stale_entries_trigger_digest_for_accountant_only(): void {
        Notification::fake();

        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->worker->id,
            'billable' => true,
            'exported' => false,
            'date' => now()->subDays(60)->toDateString(),
        ]);

        $this->artisan('finance:open-times-digest')->assertSuccessful();

        Notification::assertSentTo($this->accountant, OpenTimesDigestNotification::class, function (OpenTimesDigestNotification $n): bool {
            return $n->staleCount === 1 && $n->openCount === 1;
        });
        Notification::assertNotSentTo($this->worker, OpenTimesDigestNotification::class);
    }

    public function test_late_entries_trigger_digest(): void {
        Notification::fake();

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-0002',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->accountant->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Leistung',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '19.00',
            'position' => 1,
            'service_date' => now()->subDays(3)->toDateString(),
        ]);
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->worker->id,
            'billable' => true,
            'exported' => false,
            'date' => now()->subDays(10)->toDateString(),
        ]);

        $this->artisan('finance:open-times-digest')->assertSuccessful();

        Notification::assertSentTo($this->accountant, OpenTimesDigestNotification::class, function (OpenTimesDigestNotification $n): bool {
            return $n->lateCount === 1;
        });
    }
}
