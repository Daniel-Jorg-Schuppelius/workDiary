<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTargetInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferTarget};
use App\Models\Customer\Customer;
use App\Models\Finance\BillingTransferPosition;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Services\Finance\{BillingPositionBuilder, BillingTransferException, BillingTransferService};
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abrechnung nach Soll in der lokalen Rechnung (Feature 194, MVP-1494):
 * dieselbe Auffüllung wie in der Übergabe, nie in beiden, Storno gibt frei.
 */
class ProjectTargetInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array PERIOD = ['from' => '2030-03-01', 'to' => '2030-03-31'];

    private User $accountant;

    private User $anna;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2030-03-20');
        $this->accountant = $this->orgAdmin();
        $this->actingAs($this->accountant);
        $this->anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Beispiel']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schulträger GmbH', 'billing_mode' => BillingMode::Workdiary, 'hourly_rate' => '80.00']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Schule Nord', 'bills_target_time' => true, 'billable' => true]);
        $this->project->members()->attach([$this->anna->id]);
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => 1, 'target_minutes' => 480]);
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11', 'minutes' => 390, 'billable' => true]);
        ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11', 'target_minutes' => 480, 'booked_minutes' => 390, 'shortfall_minutes' => 90]);
    }

    public function test_local_invoice_adds_the_top_up_and_marks_the_finding_billed(): void {
        $generator = app(InvoiceGenerator::class);
        $preview = $generator->previewTimeEntries($this->customer, null, self::PERIOD);
        $this->assertSame(1, $preview['target']['count']);
        $this->assertSame(90, $preview['target']['minutes']);
        $this->assertEqualsWithDelta(120.0, $preview['target']['amount'], 0.001);
        $this->assertCount(2, $preview['lines'], 'Zeitblock plus Auffüllung in der Vorschau');

        $invoice = $generator->fromTimeEntries($this->customer, null, self::PERIOD);

        $items = $invoice->items()->orderBy('position')->get();
        $this->assertCount(2, $items, 'Zeitblock plus Auffüllung');
        $topUp = $items->last();
        $this->assertSame(2, (int) $topUp->position, 'Auffüllung folgt den Zeitpositionen');
        $this->assertStringContainsString('Vereinbarte Einsatzzeit — Anna Beispiel, 11.03.2030', (string) $topUp->description);
        $this->assertStringContainsString('Soll 8,00 h, gebucht 6,50 h — Auffüllung 1,50 h', (string) $topUp->description);
        $this->assertEqualsWithDelta(1.5, (float) $topUp->quantity, 0.0001);
        $this->assertEqualsWithDelta(80.0, $topUp->unit_price->toFloat(), 0.0001, 'Kundensatz wie bei einem Zeitblock');
        $this->assertEqualsWithDelta(120.0, $topUp->netAmount()->toFloat(), 0.001);
        $this->assertSame('2030-03-11', $topUp->service_date?->toDateString());
        $this->assertEqualsWithDelta($preview['totals']['amount'], (float) $invoice->fresh()->subtotal?->toFloat(), 0.01, 'Vorschau zeigt, was entsteht');

        $shortfall = ProjectTimeShortfall::query()->firstOrFail();
        $this->assertSame(90, $shortfall->billed_minutes);
        $this->assertNotNull($shortfall->billed_at);
        $this->assertSame($topUp->id, $shortfall->invoice_item_id);
        $this->assertNull($shortfall->billing_transfer_position_id);

        // Nachbuchung auf den berechneten Tag: nicht abrechenbar, wie bei der Übergabe.
        $late = TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11', 'minutes' => 60, 'billable' => true, 'description' => 'Nachtrag']);
        $this->assertFalse((bool) $late->fresh()->billable);
        $this->assertStringContainsString('[Soll berechnet]', (string) $late->fresh()->description);
    }

    public function test_project_without_the_switch_adds_no_top_up_to_the_invoice(): void {
        $this->project->update(['bills_target_time' => false]);

        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);

        $this->assertSame(1, $invoice->items()->count());
        $this->assertNull(ProjectTimeShortfall::query()->firstOrFail()->billed_minutes);
    }

    public function test_a_finding_billed_by_the_invoice_never_reaches_a_transfer(): void {
        $transfers = app(BillingTransferService::class);
        $draft = $transfers->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant);

        app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);

        // Der ältere Übergabeentwurf baut keine Auffüllung mehr, auch nicht beim Versand.
        $this->assertNull(app(BillingPositionBuilder::class)->build($draft->refresh())->firstWhere('source_kind', BillingTransferPosition::KIND_TARGET));
        $transferred = $transfers->markTransferred($transfers->confirm($draft, $this->accountant), null, null, $this->accountant);
        $shortfall = ProjectTimeShortfall::query()->firstOrFail();
        $this->assertNotNull($shortfall->invoice_item_id);
        $this->assertNull($shortfall->billing_transfer_position_id);

        // Der Storno der Übergabe nimmt der Rechnung ihre Auffüllung nicht weg.
        $transfers->cancel($transferred, 'Test', $this->accountant);
        $this->assertSame(90, $shortfall->refresh()->billed_minutes);
        $this->assertNotNull($shortfall->invoice_item_id);

        // Eine neue Übergabe findet nichts: Zeit und Auffüllung sind berechnet.
        TimeEntry::query()->update(['exported' => true]);
        $this->expectException(BillingTransferException::class);
        $transfers->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant);
    }

    public function test_a_finding_reserved_by_a_confirmed_transfer_stays_out_of_the_invoice(): void {
        $transfers = app(BillingTransferService::class);
        $transfers->confirm($transfers->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant), $this->accountant);

        $generator = app(InvoiceGenerator::class);
        $this->assertSame(0, $generator->previewTimeEntries($this->customer, null, self::PERIOD)['target']['count']);
        $invoice = $generator->fromTimeEntries($this->customer, null, self::PERIOD);

        $this->assertSame(0, $invoice->items()->where('description', 'like', '%Vereinbarte Einsatzzeit%')->count());
        $this->assertNull(ProjectTimeShortfall::query()->firstOrFail()->invoice_item_id);
    }

    public function test_cancellation_and_removing_the_position_release_the_top_up(): void {
        $generator = app(InvoiceGenerator::class);
        $invoice = $generator->fromTimeEntries($this->customer, null, self::PERIOD);
        $topUp = $invoice->items()->reorder('position', 'desc')->firstOrFail();

        // Position entfernen gibt frei, ein neuer Lauf nimmt die Auffüllung wieder auf.
        $this->delete(route('invoices.items.destroy', [$invoice, $topUp]))->assertRedirect();
        $shortfall = ProjectTimeShortfall::query()->firstOrFail();
        $this->assertNull($shortfall->billed_minutes);
        $this->assertNull($shortfall->invoice_item_id);

        $rebill = $generator->fromTimeEntries($this->customer, null, self::PERIOD);
        $this->assertSame(1, $rebill->items()->count(), 'nur die Auffüllung, die Zeit ist verbraucht');
        app(InvoiceIssueService::class)->issue($rebill->refresh());

        // Vollstorno: der Stornobeleg kehrt die Auffüllung um, der Befund ist wieder offen.
        $cancellation = $generator->cancellationFor($rebill->refresh(), 'Test', $this->accountant->id);
        $this->assertSame(Invoice::TYPE_CANCELLATION, $cancellation->type);
        $this->assertEqualsWithDelta(-1.5, (float) $cancellation->items()->firstOrFail()->quantity, 0.0001);
        $shortfall->refresh();
        $this->assertNull($shortfall->billed_minutes);
        $this->assertNull($shortfall->billed_at);
        $this->assertNull($shortfall->invoice_item_id);
        $this->assertSame(1, app(InvoiceGenerator::class)->previewTimeEntries($this->customer, null, self::PERIOD)['target']['count']);
    }
}
