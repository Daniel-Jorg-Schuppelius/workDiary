<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTargetBillingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferTarget};
use App\Models\Customer\Customer;
use App\Models\Finance\BillingTransferPosition;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Services\Finance\{BillingPositionBuilder, BillingTransferService};
use App\Services\Finance\BillingTransferException;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abrechnung nach Soll (Feature 194, MVP-1470): die Übergabe ergänzt je
 * Befund eine Position „Vereinbarte Einsatzzeit“, der Versand markiert den
 * Befund als berechnet, eine Nachbuchung auf den Tag ist nicht abrechenbar.
 */
class ProjectTargetBillingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private User $anna;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2030-03-20');
        $this->accountant = $this->orgAdmin();
        $this->anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Anna Beispiel']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schulträger GmbH', 'billing_mode' => BillingMode::Workdiary, 'hourly_rate' => '80.00']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Schule Nord', 'bills_target_time' => true, 'billable' => true]);
        $this->project->members()->attach([$this->anna->id]);
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => 1, 'target_minutes' => 480]);
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11', 'minutes' => 390, 'billable' => true]);
        ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11', 'target_minutes' => 480, 'booked_minutes' => 390, 'shortfall_minutes' => 90]);
    }

    public function test_transfer_adds_a_top_up_position_and_marks_the_finding_billed(): void {
        $service = app(BillingTransferService::class);
        $transfer = $service->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, ['from' => '2030-03-01', 'to' => '2030-03-31'], null, $this->accountant);

        $this->assertSame(1, $transfer->items()->where('source_type', MorphMap::alias(ProjectTimeShortfall::class))->count());
        $positions = app(BillingPositionBuilder::class)->build($transfer);
        $topUp = $positions->firstWhere('source_kind', BillingTransferPosition::KIND_TARGET);
        $this->assertNotNull($topUp, 'Auffüllung als eigene Position');
        $this->assertSame(2, $positions->count(), 'Zeitblock plus Auffüllung');
        $this->assertSame(2, (int) $topUp->position, 'Nummerierung schließt an die Zeitpositionen an');
        $this->assertEqualsWithDelta(1.5, (float) $topUp->quantity, 0.0001);
        $this->assertEqualsWithDelta(80.0, (float) $topUp->unit_price, 0.0001, 'Kundensatz wie bei einem Zeitblock');
        $this->assertEqualsWithDelta(120.0, (float) $topUp->amount, 0.01);
        $this->assertStringContainsString('Vereinbarte Einsatzzeit', (string) $topUp->name);
        $this->assertStringContainsString('Anna Beispiel', (string) $topUp->name);

        $confirmed = $service->confirm($transfer, $this->accountant);
        $service->markTransferred($confirmed, null, null, $this->accountant);

        $shortfall = ProjectTimeShortfall::query()->firstOrFail();
        $this->assertSame(90, $shortfall->billed_minutes);
        $this->assertNotNull($shortfall->billed_at);
        $this->assertNotNull($shortfall->billing_transfer_position_id);

        // Eine zweite Übergabe findet keine Auffüllung mehr; die Nachbuchung auf den Tag ist nicht abrechenbar.
        $late = TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->anna->id, 'date' => '2030-03-11', 'minutes' => 60, 'billable' => true, 'description' => 'Nachtrag']);
        $this->assertFalse((bool) $late->fresh()->billable);
        $this->assertStringContainsString('[Soll berechnet]', (string) $late->fresh()->description);
        // Zeit verbraucht, Auffüllung berechnet, Nachbuchung nicht abrechenbar: nichts mehr zu übergeben.
        $this->expectException(BillingTransferException::class);
        $service->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, ['from' => '2030-03-01', 'to' => '2030-03-31'], null, $this->accountant);
    }

    public function test_project_without_the_switch_adds_no_top_up(): void {
        $this->project->update(['bills_target_time' => false]);

        $transfer = app(BillingTransferService::class)->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, ['from' => '2030-03-01', 'to' => '2030-03-31'], null, $this->accountant);

        $this->assertSame(0, $transfer->items()->where('source_type', MorphMap::alias(ProjectTimeShortfall::class))->count());
        $this->assertNull(app(BillingPositionBuilder::class)->build($transfer)->firstWhere('source_kind', BillingTransferPosition::KIND_TARGET));
    }

    public function test_billing_settings_form_saves_the_switch(): void {
        $this->project->update(['bills_target_time' => false]);

        $this->actingAs($this->accountant)->patch(route('projects.billing-settings.update', $this->project), ['bills_target_time' => '1'])->assertRedirect();
        $this->assertTrue((bool) $this->project->fresh()->bills_target_time);

        $this->actingAs($this->accountant)->patch(route('projects.billing-settings.update', $this->project), [])->assertRedirect();
        $this->assertFalse((bool) $this->project->fresh()->bills_target_time);
    }
}
