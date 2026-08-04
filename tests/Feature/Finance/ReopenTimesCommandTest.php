<?php
/*
 * Created on   : Sun Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReopenTimesCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Billing\BillingAgreementMode;
use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Models\Billing\CustomerBillingAgreement;
use App\Models\{Customer, Invoice, InvoiceItem, Project, TimeEntry, User};
use App\Models\Finance\{BillingTransfer, BillingTransferItem};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rückholer für fälschlich abgeschlossene Zeiten: öffnet ab einem
 * Leistungsdatum wieder, lässt Rechnungs-, Übergabe- und saldo-geführte
 * Zeiten aber unangetastet.
 */
class ReopenTimesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = $this->orgUser();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    private function closedEntry(string $date, ?Project $project = null): TimeEntry {
        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => ($project ?? $this->project)->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'minutes' => 60,
            'billable' => true,
            'exported' => true,
        ]);
    }

    public function test_dry_run_oeffnet_nichts(): void {
        $entry = $this->closedEntry('2026-04-15');

        $this->artisan('billing:reopen-times', ['--from' => '2026-04-01'])->assertSuccessful();

        $this->assertTrue((bool) $entry->fresh()->exported);
    }

    public function test_apply_oeffnet_nur_ab_dem_stichtag(): void {
        $before = $this->closedEntry('2026-03-31');
        $after = $this->closedEntry('2026-04-01');

        $this->artisan('billing:reopen-times', ['--from' => '2026-04-01', '--apply' => true])->assertSuccessful();

        $this->assertTrue((bool) $before->fresh()->exported, 'Bereits fakturierter Altbestand bleibt geschlossen.');
        $this->assertFalse((bool) $after->fresh()->exported);
    }

    public function test_rechnungszeiten_bleiben_geschlossen(): void {
        $entry = $this->closedEntry('2026-04-10');

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-0001',
            'status' => Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
        $item = InvoiceItem::create([
            'organization_id' => $this->organization->id,
            'invoice_id' => $invoice->id,
            'description' => 'Leistung',
            'quantity' => '1.00',
            'unit_price' => '90.00',
            'position' => 1,
        ]);
        $item->timeEntries()->attach($entry->id);

        $this->artisan('billing:reopen-times', ['--from' => '2026-04-01', '--apply' => true])->assertSuccessful();

        $this->assertTrue((bool) $entry->fresh()->exported);
    }

    public function test_uebergebene_zeiten_bleiben_geschlossen(): void {
        $entry = $this->closedEntry('2026-04-11');

        $transfer = BillingTransfer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'channel' => TransferChannel::Time,
            'target' => TransferTarget::Lexoffice,
            'status' => \App\Enums\Finance\TransferStatus::Transferred,
        ]);
        BillingTransferItem::create([
            'billing_transfer_id' => $transfer->id,
            'source_type' => TimeEntry::class,
            'source_id' => $entry->id,
            'quantity' => '1.00',
            'amount' => '90.00',
        ]);

        $this->artisan('billing:reopen-times', ['--from' => '2026-04-01', '--apply' => true])->assertSuccessful();

        $this->assertTrue((bool) $entry->fresh()->exported);
    }

    public function test_saldo_gefuehrte_kunden_bleiben_unangetastet(): void {
        $ledgerCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        CustomerBillingAgreement::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $ledgerCustomer->id,
            'mode' => BillingAgreementMode::Retainer,
            'active' => true,
        ]);
        $ledgerProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $ledgerCustomer->id,
        ]);
        $ledgerEntry = $this->closedEntry('2026-04-12', $ledgerProject);
        $normal = $this->closedEntry('2026-04-12');

        $this->artisan('billing:reopen-times', ['--from' => '2026-04-01', '--apply' => true])->assertSuccessful();

        $this->assertTrue((bool) $ledgerEntry->fresh()->exported, 'Monatsabschluss darf nicht rückabgewickelt werden.');
        $this->assertFalse((bool) $normal->fresh()->exported);
    }

    public function test_kundenfilter_und_audit(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
        ]);
        $mine = $this->closedEntry('2026-04-13');
        $foreign = $this->closedEntry('2026-04-13', $otherProject);

        $this->artisan('billing:reopen-times', [
            '--from' => '2026-04-01',
            '--customer' => (string) $this->customer->sqid,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertFalse((bool) $mine->fresh()->exported);
        $this->assertTrue((bool) $foreign->fresh()->exported);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'time_entries.reopened',
        ]);
    }

    public function test_kundenfilter_akzeptiert_kundennummer(): void {
        $this->customer->update(['number' => 'K-1005']);

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
        ]);
        $mine = $this->closedEntry('2026-04-13');
        $foreign = $this->closedEntry('2026-04-13', $otherProject);

        $this->artisan('billing:reopen-times', [
            '--from' => '2026-04-01',
            '--customer' => 'K-1005',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertFalse((bool) $mine->fresh()->exported);
        $this->assertTrue((bool) $foreign->fresh()->exported);
    }

    public function test_numerische_kundennummer_gewinnt_vor_interner_id(): void {
        // Kundennummer des einen Kunden == interne ID des anderen: der im UI
        // sichtbare Wert (die Kundennummer) muss gewinnen.
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->customer->update(['number' => (string) $other->id]);

        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
        ]);
        $mine = $this->closedEntry('2026-04-13');
        $foreign = $this->closedEntry('2026-04-13', $otherProject);

        $this->artisan('billing:reopen-times', [
            '--from' => '2026-04-01',
            '--customer' => (string) $other->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertFalse((bool) $mine->fresh()->exported);
        $this->assertTrue((bool) $foreign->fresh()->exported);
    }

    public function test_ohne_from_bricht_ab(): void {
        $entry = $this->closedEntry('2026-04-14');

        $this->artisan('billing:reopen-times', ['--apply' => true])->assertFailed();

        $this->assertTrue((bool) $entry->fresh()->exported);
    }
}
