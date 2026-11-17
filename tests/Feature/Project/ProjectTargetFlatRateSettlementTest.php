<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTargetFlatRateSettlementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\Finance\BillingMode;
use App\Enums\Project\{ProjectStatus, ShortfallStatus};
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use App\Services\Project\TimeTargets\ProjectTimeShortfallDetector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1708: Rechnet ein Projekt mit „Vereinbarte Stunden abrechnen“ pauschal
 * ab, entfällt die Auffüllung (MVP-1678). Der Rechnungslauf, der die Pauschale
 * abrechnet, schließt den Soll-Befund jetzt als erledigt mit Verweis auf die
 * Position, statt ihn offen stehen zu lassen; der Nachtlauf öffnet ihn nicht
 * wieder, Entfernen der Position und Storno schon.
 */
final class ProjectTargetFlatRateSettlementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array PERIOD = ['from' => '2030-04-01', 'to' => '2030-04-30'];

    private User $accountant;

    private User $anna;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2030-04-20');
        $this->accountant = $this->orgAdmin(['hourly_rate' => null]);
        $this->actingAs($this->accountant);
        $this->anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Beispiel']);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => '90.00',
            'currency' => 'EUR',
            'billing_mode' => BillingMode::Workdiary,
        ]);
        $this->project = $this->targetProject('Heizungswartung');
    }

    public function test_the_run_settles_the_finding_with_the_flat_line(): void {
        $this->flatRule($this->project);
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);

        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);

        $flat = $invoice->items()->sole();
        $this->assertSame(1.0, (float) $flat->quantity, 'Nur die Pauschale, keine Auffüllung.');
        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->status);
        $this->assertNotNull($shortfall->resolved_at);
        $this->assertSame($flat->id, $shortfall->invoice_item_id, 'Grund: die Position, die die Zeiten des Tages abrechnet.');
        $this->assertNull($shortfall->billed_minutes, 'Aufgefüllt wurde nichts.');
        $this->assertTrue($shortfall->isSettledByFlatRate());
        $this->assertSame(0, ProjectTimeShortfall::query()->unresolved()->count(), 'Weder Arbeitsvorrat noch Leitungssicht zählen ihn.');
        $this->assertSame(0, app(InvoiceGenerator::class)->previewTimeEntries($this->customer, null, self::PERIOD)['target']['count']);

        // Anders als nach einer Auffüllung bleibt eine Nachbuchung abrechenbar.
        $late = $this->entry($this->project, '2030-04-01 15:00', '2030-04-01 16:00');
        $this->assertTrue((bool) $late->fresh()?->billable);

        // Der Zeitreiter nennt den Grund mit der Rechnungsnummer.
        $this->get(route('projects.show', ['project' => $this->project, 'tab' => 'time', 'show' => 'shortfalls']))->assertOk()
            ->assertSee(__('invoicing.target_settlement.flat_rate', ['number' => $invoice->number]));
    }

    public function test_the_nightly_check_does_not_reopen_a_settled_finding(): void {
        $this->flatRule($this->project);
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);
        app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);

        $day = CarbonImmutable::parse('2030-04-01');
        $result = app(ProjectTimeShortfallDetector::class)->checkRange($this->project, $day, $day, 15);

        $this->assertSame(0, $result['reopened']);
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->refresh()->status);

        // Gegenprobe: ein gewöhnlich erledigter Befund mit derselben Lücke öffnet der Lauf wieder.
        $shortfall->update(['invoice_item_id' => null]);
        $again = app(ProjectTimeShortfallDetector::class)->checkRange($this->project, $day, $day, 15);
        $this->assertSame(1, $again['reopened']);
        $this->assertSame(ShortfallStatus::Open, $shortfall->refresh()->status);
    }

    public function test_removing_the_line_and_cancelling_reopen_the_finding(): void {
        $this->flatRule($this->project);
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);
        $generator = app(InvoiceGenerator::class);
        $invoice = $generator->fromTimeEntries($this->customer, null, self::PERIOD);

        $this->delete(route('invoices.items.destroy', [$invoice, $invoice->items()->sole()]))->assertRedirect();
        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Open, $shortfall->status);
        $this->assertNull($shortfall->resolved_at);
        $this->assertNull($shortfall->invoice_item_id);

        // Ein neuer Lauf rechnet die Pauschale wieder ab und schließt erneut; der Storno öffnet.
        $rebill = $generator->fromTimeEntries($this->customer, null, self::PERIOD);
        $this->assertTrue($shortfall->refresh()->isSettledByFlatRate());
        app(InvoiceIssueService::class)->issue($rebill->refresh());
        $cancellation = $generator->cancellationFor($rebill->refresh(), 'Doppelt berechnet', $this->accountant->id);

        $this->assertSame(Invoice::TYPE_CANCELLATION, $cancellation->type);
        $this->assertSame(ShortfallStatus::Open, $shortfall->refresh()->status);
        $this->assertNull($shortfall->invoice_item_id);
    }

    /** Gegenprobe: nach Stunden bleibt es bei der Auffüllung, der Befund ist berechnet und nicht pauschal erledigt. */
    public function test_hourly_project_keeps_the_top_up(): void {
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);

        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);

        $this->assertSame(2, $invoice->items()->count(), 'Zeitblock plus Auffüllung');
        $shortfall->refresh();
        $this->assertSame(120, $shortfall->billed_minutes);
        $this->assertSame(ShortfallStatus::Open, $shortfall->status);
        $this->assertFalse($shortfall->isSettledByFlatRate());
    }

    /** Ohne abgerechnete Zeit des Projekts in diesem Lauf bleibt sein Befund offen. */
    public function test_flat_project_without_time_in_this_run_stays_open(): void {
        $this->flatRule($this->project);
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $idle = $this->targetProject('Lüftungswartung');
        $this->flatRule($idle);
        $idleShortfall = $this->shortfall($idle);

        app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);

        $this->assertSame(ShortfallStatus::Open, $idleShortfall->refresh()->status);
        $this->assertNull($idleShortfall->refresh()->invoice_item_id);
    }

    private function targetProject(string $name): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => $name,
            'status' => ProjectStatus::Active->value,
            'hourly_rate' => null,
            'is_default' => false,
            'bills_target_time' => true,
            'billable' => true,
        ]);
        $project->members()->attach([$this->anna->id]);
        // Der 01.04.2030 ist ein Montag.
        ProjectTimeTarget::factory()->create(['project_id' => $project->id, 'weekday' => 1, 'target_minutes' => 480]);

        return $project;
    }

    private function flatRule(Project $project): void {
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'unit_name' => 'Pauschale',
            'net_unit_price' => 250.0,
        ]);
    }

    private function shortfall(Project $project): ProjectTimeShortfall {
        return ProjectTimeShortfall::factory()->create([
            'project_id' => $project->id,
            'user_id' => $this->anna->id,
            'date' => '2030-04-01',
            'target_minutes' => 480,
            'booked_minutes' => 360,
            'shortfall_minutes' => 120,
        ]);
    }

    private function entry(Project $project, string $start, string $end): TimeEntry {
        $from = CarbonImmutable::parse($start);
        $to = CarbonImmutable::parse($end);
        $order = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->anna->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'title' => 'Wartung Kessel',
        ]);

        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->anna->id,
            'date' => $from->toDateString(),
            'started_at' => $from,
            'ended_at' => $to,
            'minutes' => (int) $from->diffInMinutes($to),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'description' => 'Kessel geprüft',
        ]);
    }
}
