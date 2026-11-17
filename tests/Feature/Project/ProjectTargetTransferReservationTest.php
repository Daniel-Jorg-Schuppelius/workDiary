<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTargetTransferReservationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferTarget};
use App\Enums\Project\{ProjectStatus, ShortfallStatus};
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Services\Finance\BillingTransferService;
use App\Services\Invoicing\TargetTopUps;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1722: Eine Übergabe hält einen Soll-Befund nur, wenn sie ihn auffüllt
 * (Auffüllposition) oder mit der Pauschale schließen kann (Arbeitszeit seines
 * Projekts). Vorher blieb ein Pauschal-Befund ohne Arbeitszeit seines Projekts
 * nach dem Übertragen offen, aber bis zum Storno reserviert — keine weitere
 * Übergabe und keine Rechnung konnte ihn schließen.
 */
final class ProjectTargetTransferReservationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array PERIOD = ['from' => '2030-04-01', 'to' => '2030-04-30'];

    private User $accountant;

    private User $anna;

    private Customer $customer;

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
            'country' => 'DE',
            'billing_mode' => BillingMode::Workdiary,
        ]);
    }

    public function test_flat_finding_without_time_of_its_project_is_free_after_the_transfer(): void {
        $busy = $this->targetProject('Heizungswartung');
        $this->entry($busy, '2030-04-01 08:00', '2030-04-01 14:00');
        $idle = $this->targetProject('Lüftungswartung');
        $this->flatRule($idle);
        $idleShortfall = $this->shortfall($idle);
        $service = app(BillingTransferService::class);

        $first = $this->transferred();

        $this->assertSame(ShortfallStatus::Open, $idleShortfall->refresh()->status);
        $this->assertContains($idleShortfall->id, $this->openIds(), 'Vorher: bis zum Storno der Übergabe reserviert.');

        // Die nächste Übergabe mit Arbeitszeit des Projekts nimmt ihn auf und schließt ihn mit der Pauschale.
        $this->entry($idle, '2030-04-02 08:00', '2030-04-02 12:00');
        $second = $this->transferred();
        $timePosition = $second->positions()->where('source_kind', BillingTransferPosition::KIND_TIME)->where('project_id', $idle->id)->sole();
        $idleShortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $idleShortfall->status);
        $this->assertSame($timePosition->id, $idleShortfall->billing_transfer_position_id);

        // Der Storno der ersten Übergabe lässt den Befund der zweiten.
        $service->cancel($first->refresh(), 'Beleg im Zielsystem verworfen', $this->accountant);
        $this->assertSame(ShortfallStatus::Resolved, $idleShortfall->refresh()->status);
        $this->assertSame($timePosition->id, $idleShortfall->billing_transfer_position_id);
    }

    /** Gegenprobe: Auffüllung und Pauschale mit Arbeitszeit bleiben gehalten, schon in der bestätigten Übergabe. */
    public function test_confirmed_transfer_holds_the_findings_it_bills_or_settles(): void {
        $hourly = $this->targetProject('Heizungswartung');
        $this->entry($hourly, '2030-04-01 08:00', '2030-04-01 14:00');
        $hourlyShortfall = $this->shortfall($hourly);
        $flat = $this->targetProject('Kesselwartung');
        $this->flatRule($flat);
        $this->entry($flat, '2030-04-01 08:00', '2030-04-01 14:00');
        $flatShortfall = $this->shortfall($flat);
        $idle = $this->targetProject('Lüftungswartung');
        $this->flatRule($idle);
        $idleShortfall = $this->shortfall($idle);
        $service = app(BillingTransferService::class);

        $confirmed = $service->confirm($this->draft(), $this->accountant);

        $this->assertSame(1, $confirmed->positions()->where('source_kind', BillingTransferPosition::KIND_TARGET)->where('primary_source_id', $hourlyShortfall->id)->count());
        $open = $this->openIds();
        $this->assertNotContains($hourlyShortfall->id, $open, 'Die Auffüllung gehört der bestätigten Übergabe.');
        $this->assertNotContains($flatShortfall->id, $open, 'Die Pauschale mit Arbeitszeit schließt ihn beim Übertragen.');
        $this->assertContains($idleShortfall->id, $open);

        // Eine zweite Übergabe mit dem freien Befund lässt sich bestätigen; die gehaltenen fehlen in ihr.
        $second = $service->confirm($this->draft(), $this->accountant);
        $this->assertSame([$idleShortfall->id], $second->items()->where('source_type', $idleShortfall->getMorphClass())->pluck('source_id')->map(static fn ($id): int => (int) $id)->all());

        $service->markTransferred($confirmed->refresh(), null, null, $this->accountant);
        $this->assertSame(120, $hourlyShortfall->refresh()->billed_minutes);
        $this->assertSame(ShortfallStatus::Resolved, $flatShortfall->refresh()->status);
        $this->assertNotContains($hourlyShortfall->id, $this->openIds());
    }

    /** @return list<int> */
    private function openIds(): array {
        return array_values(array_map('intval', app(TargetTopUps::class)->openQuery($this->customer, self::PERIOD)->pluck('id')->all()));
    }

    private function draft(): BillingTransfer {
        return app(BillingTransferService::class)->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant);
    }

    private function transferred(): BillingTransfer {
        $service = app(BillingTransferService::class);

        return $service->markTransferred($service->confirm($this->draft(), $this->accountant), null, null, $this->accountant);
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
            'title' => 'Wartung',
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
            'description' => 'Anlage geprüft',
        ]);
    }
}
