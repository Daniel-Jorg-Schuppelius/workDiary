<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceTransferHeaderRangeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Project, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Services\Finance\BillingTransferService;
use App\Services\UI\DateRangeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Header-Zeitraum vs. Faktura-Übergabe: die Anlage sammelt unabhängig vom
 * global gewählten Zeitraum (nur der Dialog-Zeitraum zählt), und AKTIVE
 * Nachweise (Entwurf/Bestätigt/Fehlgeschlagen) bleiben in der Liste sichtbar,
 * auch wenn ihr Leistungszeitraum nicht ins Header-Fenster fällt.
 */
class FinanceTransferHeaderRangeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    private function entry(string $date): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => $date,
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ]);
    }

    public function test_anlage_und_bestaetigung_ignorieren_den_header_zeitraum(): void {
        $this->entry('2026-01-10');
        $this->entry('2026-03-10');

        // Header-Fenster deckt die Zeiten bewusst NICHT ab.
        app(DateRangeContext::class)->set('custom', '2026-07-01', '2026-07-31');

        $this->post(route('finance.transfers.store'), [
            'customer_id' => (string) $this->customer->sqid,
            'channel' => TransferChannel::Time->value,
            'target' => TransferTarget::File->value,
            'from' => '2026-01-01',
            'to' => '2026-06-30',
        ])->assertRedirect();

        $transfer = BillingTransfer::query()->firstOrFail();
        $this->assertSame(2, $transfer->items()->count());

        $this->post(route('finance.transfers.confirm', $transfer))->assertRedirect();
        $transfer->refresh();
        $this->assertSame(TransferStatus::Confirmed, $transfer->status);
        $this->assertSame(2.0, (float) $transfer->total_quantity);
    }

    public function test_aktive_uebergabe_bleibt_trotz_header_fenster_in_der_liste(): void {
        $this->entry('2026-01-10');

        $transfer = app(BillingTransferService::class)->createDraft(
            $this->customer,
            TransferChannel::Time,
            TransferTarget::File,
            ['from' => '2026-01-01', 'to' => '2026-06-30'],
            null,
            $this->admin,
        );

        app(DateRangeContext::class)->set('custom', '2026-07-01', '2026-07-31');

        // Entwurf: sichtbar, obwohl der Leistungszeitraum ausserhalb liegt.
        $this->get(route('finance.transfers.index'))
            ->assertOk()
            ->assertSee($transfer->sqid);

        // Abgeschlossen (voided): unterliegt wieder dem Header-Fenster.
        app(BillingTransferService::class)->void($transfer->fresh(), $this->admin);

        $this->get(route('finance.transfers.index'))
            ->assertOk()
            ->assertDontSee($transfer->sqid);
    }
}
