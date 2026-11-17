<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RepairTransferShortfallsCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1721: `finance:repair-transfer-shortfalls` setzt Pauschal-Befunde
 * zurück, die eine Übergabe vor MVP-1716 ohne Auffüllposition als berechnet
 * markiert hat, schließt sie wie heute über die Pauschale, wenn die Übergabe
 * Arbeitszeit ihres Projekts enthält, und macht die als „[Soll berechnet]“
 * markierten Nachbuchungen wieder abrechenbar. Nicht eindeutige Fälle meldet
 * er nur.
 */
final class RepairTransferShortfallsCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string COMMAND = 'finance:repair-transfer-shortfalls';

    private const array PERIOD = ['from' => '2030-04-01', 'to' => '2030-04-30'];

    private User $accountant;

    private User $anna;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2030-04-20 09:00');
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

    public function test_dry_run_lists_and_the_run_resets_the_finding_and_its_late_entry(): void {
        $busy = $this->targetProject('Heizungswartung');
        $this->entry($busy, '2030-04-01 08:00', '2030-04-01 14:00');
        $idle = $this->targetProject('Lüftungswartung');
        $this->flatRule($idle);
        $shortfall = $this->shortfall($idle);
        $this->transferred();
        $this->markedAsBilledBeforeMvp1716($shortfall);
        $this->travel(5)->minutes();
        $late = $this->entry($idle, '2030-04-01 15:00', '2030-04-01 16:00', 'Nachgetragen');
        $marker = (string) __('[Soll berechnet]');
        $this->assertFalse((bool) $late->refresh()->billable, 'Vorbedingung: die Nachbuchung ist als „Soll berechnet“ markiert.');
        $this->assertStringContainsString($marker, (string) $late->description);

        // Befund und Nachbuchung stehen in einer Zeile; expectsOutputToContain prüfte davon nur einen.
        $dryRun = $this->repairOutput(['--dry-run' => true]);
        $this->assertStringContainsString((string) $shortfall->sqid, $dryRun);
        $this->assertStringContainsString((string) $late->sqid, $dryRun);
        $this->assertSame(120, $shortfall->refresh()->billed_minutes, 'Der Trockenlauf ändert nichts.');
        $this->assertFalse((bool) $late->refresh()->billable);

        $this->repair()->assertSuccessful();

        $shortfall->refresh();
        $this->assertNull($shortfall->billed_minutes);
        $this->assertNull($shortfall->billed_at);
        $this->assertSame(ShortfallStatus::Open, $shortfall->status, 'Ohne Arbeitszeit des Projekts in der Übergabe bleibt er offen.');
        $late->refresh();
        $this->assertTrue((bool) $late->billable);
        $this->assertSame('Nachgetragen', $late->description);
        // MVP-1722: Die Übergabe hält ihn nicht mehr; die nächste Abrechnung kann ihn schließen.
        $this->assertContains($shortfall->id, array_map('intval', app(TargetTopUps::class)->openQuery($this->customer, self::PERIOD)->pluck('id')->all()));

        // Idempotent: ein zweiter Lauf findet nichts mehr.
        $this->assertStringNotContainsString((string) $shortfall->sqid, $this->repairOutput());
        $this->assertNull($shortfall->refresh()->billed_minutes);
    }

    /** Enthält die Übergabe Arbeitszeit des Pauschal-Projekts, schließt die Reparatur den Befund wie MVP-1716. */
    public function test_finding_with_time_of_its_project_is_settled_by_the_transfer(): void {
        $project = $this->targetProject('Kesselwartung');
        $this->flatRule($project);
        $this->entry($project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($project);
        $transfer = $this->transferred();
        $timePosition = $transfer->positions()->where('source_kind', BillingTransferPosition::KIND_TIME)->sole();
        // Vor MVP-1716: offen, berechnet, ohne Verweis (refresh: das Übertragen hat ihn eben geschlossen).
        $shortfall->refresh()->forceFill(['status' => ShortfallStatus::Open, 'resolved_at' => null, 'billing_transfer_position_id' => null])->saveQuietly();
        $this->markedAsBilledBeforeMvp1716($shortfall);

        $this->repair()->assertSuccessful();

        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->status);
        $this->assertSame($timePosition->id, $shortfall->billing_transfer_position_id);
        $this->assertNull($shortfall->billed_minutes);
        $this->assertTrue($shortfall->isSettledByFlatRate());
    }

    /** Gegenprobe: Eine Auffüllung mit Position bleibt berechnet, ihre Nachbuchung nicht abrechenbar. */
    public function test_finding_billed_by_its_top_up_position_is_left_alone(): void {
        $project = $this->targetProject('Heizungswartung');
        $this->entry($project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($project);
        $this->transferred();
        $this->assertNotNull($shortfall->refresh()->billing_transfer_position_id);
        $this->travel(5)->minutes();
        $late = $this->entry($project, '2030-04-01 15:00', '2030-04-01 16:00', 'Nachgetragen');

        $this->repair()->assertSuccessful();

        $this->assertSame(120, $shortfall->refresh()->billed_minutes);
        $this->assertFalse((bool) $late->refresh()->billable);
    }

    /** Nicht eindeutig: Die Übergabe hat eine Auffüllposition, nur der Verweis fehlt — gemeldet, nicht geändert. */
    public function test_unclear_finding_is_reported_only(): void {
        $project = $this->targetProject('Heizungswartung');
        $this->entry($project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($project);
        $transfer = $this->transferred();
        $shortfall->forceFill(['billing_transfer_position_id' => null])->saveQuietly();

        $output = $this->repairOutput();
        $this->assertStringContainsString((string) $shortfall->sqid, $output);
        $this->assertStringContainsString("Übergabe #{$transfer->id} hat eine Auffüllposition", $output);

        $this->assertSame(120, $shortfall->refresh()->billed_minutes);
    }

    /** @param  array<string, mixed>  $options */
    private function repair(array $options = []): PendingCommand {
        $command = $this->artisan(self::COMMAND, $options);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    /** @param  array<string, mixed>  $options */
    private function repairOutput(array $options = []): string {
        $this->assertSame(0, Artisan::call(self::COMMAND, $options));

        return Artisan::output();
    }

    private function markedAsBilledBeforeMvp1716(ProjectTimeShortfall $shortfall): void {
        $shortfall->forceFill(['billed_minutes' => (int) $shortfall->shortfall_minutes, 'billed_at' => now()])->saveQuietly();
    }

    private function transferred(): BillingTransfer {
        $service = app(BillingTransferService::class);
        $draft = $service->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant);

        return $service->markTransferred($service->confirm($draft, $this->accountant), null, null, $this->accountant);
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

    private function entry(Project $project, string $start, string $end, string $description = 'Anlage geprüft'): TimeEntry {
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
            'description' => $description,
        ]);
    }
}
