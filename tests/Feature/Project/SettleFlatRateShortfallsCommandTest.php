<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettleFlatRateShortfallsCommandTest.php
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
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Models\Time\TimeEntry;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Finance\BillingTransferService;
use App\Services\Invoicing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1734: `projects:settle-flat-rate-shortfalls` schließt Soll-Befunde von
 * Pauschal-Projekten, die vor MVP-1708 offen blieben, obwohl ein Beleg die
 * Arbeitszeit des Projekts schon abgerechnet hatte — über denselben Weg wie
 * heute (Rechnungsposition, Übergabeposition, Zeitbeleg). Nicht eindeutige
 * Fälle meldet er nur; Stunden-Projekte bleiben offen.
 */
final class SettleFlatRateShortfallsCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string COMMAND = 'projects:settle-flat-rate-shortfalls';

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

    public function test_dry_run_lists_and_the_run_settles_through_the_invoice_idempotently(): void {
        $project = $this->targetProject('Heizungswartung', 'Pauschale');
        $this->entry($project, $this->anna, '2030-04-01 08:00', '2030-04-01 14:00');
        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, $project, self::PERIOD);
        // Stand vor MVP-1708: Die Rechnung rechnete die Pauschale ab, der Befund blieb offen.
        $shortfall = $this->shortfall($project, '2030-04-01');
        $item = $invoice->items()->whereHas('timeEntries')->sole();

        $dryRun = $this->settleOutput(['--dry-run' => true]);
        $this->assertStringContainsString((string) $shortfall->sqid, $dryRun);
        $this->assertStringContainsString("zu schließen über Rechnung #{$invoice->id}", $dryRun);
        $this->assertSame(ShortfallStatus::Open, $shortfall->refresh()->status, 'Der Trockenlauf ändert nichts.');

        $this->assertStringContainsString("geschlossen über Rechnung #{$invoice->id}", $this->settleOutput());

        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->status);
        $this->assertSame($item->id, $shortfall->invoice_item_id);
        $this->assertNull($shortfall->billed_minutes, 'Aufgefüllt wurde nichts.');
        $this->assertTrue($shortfall->isSettledByFlatRate());

        // Idempotent: Der zweite Lauf findet ihn nicht mehr.
        $this->assertStringNotContainsString((string) $shortfall->sqid, $this->settleOutput());
        $this->assertSame($item->id, $shortfall->refresh()->invoice_item_id);
    }

    public function test_finding_is_settled_through_the_transferred_transfer(): void {
        $project = $this->targetProject('Heizungswartung', 'Pauschale');
        $this->entry($project, $this->anna, '2030-04-01 08:00', '2030-04-01 14:00');
        $transfer = $this->transferred();
        $position = $transfer->positions()->where('source_kind', BillingTransferPosition::KIND_TIME)->sole();
        $shortfall = $this->shortfall($project, '2030-04-01');

        $this->assertStringContainsString("geschlossen über Übergabe #{$transfer->id}", $this->settleOutput());

        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->status);
        $this->assertSame($position->id, $shortfall->billing_transfer_position_id);
        $this->assertTrue($shortfall->isSettledByFlatRate());
    }

    public function test_finding_is_settled_through_the_lexoffice_time_voucher(): void {
        $project = $this->targetProject('Heizungswartung', 'Pauschale');
        $this->entry($project, $this->anna, '2030-04-01 08:00', '2030-04-01 14:00');
        $this->connectLexoffice();
        app(LexofficePlugin::class)->exportCustomerTime($this->customer, CarbonImmutable::parse('2030-04-01')->startOfDay(), CarbonImmutable::parse('2030-04-30')->endOfDay());
        $voucher = ExternalReference::query()->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)->sole();
        $shortfall = $this->shortfall($project, '2030-04-03');

        $this->assertStringContainsString("geschlossen über Lexoffice-Zeitbeleg #{$voucher->id}", $this->settleOutput());

        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->status);
        $this->assertSame($voucher->id, $shortfall->external_reference_id);
        $this->assertTrue($shortfall->isSettledByFlatRate());
    }

    /** Gegenprobe: Ein Stunden-Projekt bleibt offen — die nächste Abrechnung füllt es auf. */
    public function test_hourly_project_stays_open(): void {
        $project = $this->targetProject('Objektreinigung', 'Std.');
        $this->entry($project, $this->anna, '2030-04-01 08:00', '2030-04-01 14:00');
        app(InvoiceGenerator::class)->fromTimeEntries($this->customer, $project, self::PERIOD);
        $shortfall = $this->shortfall($project, '2030-04-01');

        $this->assertStringNotContainsString((string) $shortfall->sqid, $this->settleOutput());

        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Open, $shortfall->status);
        $this->assertNull($shortfall->invoice_item_id);
        $this->assertNull($shortfall->billed_minutes);
    }

    /** Außerhalb des Leistungszeitraums des Belegs gibt es keinen Träger: bleibt offen, nur gemeldet. */
    public function test_finding_outside_the_billed_period_stays_open(): void {
        $project = $this->targetProject('Heizungswartung', 'Pauschale');
        $this->entry($project, $this->anna, '2030-04-01 08:00', '2030-04-01 14:00');
        app(InvoiceGenerator::class)->fromTimeEntries($this->customer, $project, self::PERIOD);
        $shortfall = $this->shortfall($project, '2030-04-15');

        $this->assertStringContainsString('kein Beleg rechnet Arbeitszeit des Projekts im Zeitraum ab', $this->settleOutput());

        $this->assertSame(ShortfallStatus::Open, $shortfall->refresh()->status);
    }

    /** Zwei Belege umfassen den Tag, keiner mit Zeit der Person an dem Tag: nicht eindeutig, nur gemeldet. */
    public function test_two_carriers_without_the_person_on_the_day_are_reported_only(): void {
        $project = $this->targetProject('Heizungswartung', 'Pauschale');
        $this->entry($project, $this->accountant, '2030-04-01 08:00', '2030-04-01 10:00');
        $this->entry($project, $this->accountant, '2030-04-10 08:00', '2030-04-10 10:00');
        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, $project, ['from' => '2030-04-01', 'to' => '2030-04-10']);
        $this->entry($project, $this->accountant, '2030-04-05 08:00', '2030-04-05 10:00');
        $transfer = $this->transferred();
        $shortfall = $this->shortfall($project, '2030-04-07');

        $output = $this->settleOutput();

        $this->assertStringContainsString((string) $shortfall->sqid, $output);
        $this->assertStringContainsString('nicht eindeutig', $output);
        $this->assertStringContainsString("Rechnung #{$invoice->id}", $output);
        $this->assertStringContainsString("Übergabe #{$transfer->id}", $output);
        $this->assertSame(ShortfallStatus::Open, $shortfall->refresh()->status);
    }

    /** Hat einer der Belege Arbeitszeit der Person an dem Tag, gewinnt er — dieselbe Regel wie `settle()`. */
    public function test_the_carrier_with_the_person_on_the_day_wins(): void {
        $project = $this->targetProject('Heizungswartung', 'Pauschale');
        $this->entry($project, $this->anna, '2030-04-01 08:00', '2030-04-01 10:00');
        $this->entry($project, $this->accountant, '2030-04-10 08:00', '2030-04-10 10:00');
        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, $project, ['from' => '2030-04-01', 'to' => '2030-04-10']);
        $this->entry($project, $this->accountant, '2030-04-05 08:00', '2030-04-05 10:00');
        $this->transferred();
        $shortfall = $this->shortfall($project, '2030-04-01');

        $this->assertStringContainsString("geschlossen über Rechnung #{$invoice->id}", $this->settleOutput());
        $this->assertNotNull($shortfall->refresh()->invoice_item_id);
    }

    /** @param  array<string, mixed>  $options */
    private function settleOutput(array $options = []): string {
        $this->assertSame(0, Artisan::call(self::COMMAND, $options));

        return Artisan::output();
    }

    private function transferred(): BillingTransfer {
        $service = app(BillingTransferService::class);
        $draft = $service->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant);

        return $service->markTransferred($service->confirm($draft, $this->accountant), null, null, $this->accountant);
    }

    private function connectLexoffice(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->id,
            'external_id' => 'contact-1',
            'synced_at' => now(),
        ]);
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/vouchers*' => FakePluginHttp::response(['id' => 'voucher-time-1'], 201),
        ]);
    }

    private function targetProject(string $name, string $unit): Project {
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
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'unit_name' => $unit,
            'net_unit_price' => 250.0,
        ]);

        return $project;
    }

    private function shortfall(Project $project, string $date): ProjectTimeShortfall {
        return ProjectTimeShortfall::factory()->create([
            'project_id' => $project->id,
            'user_id' => $this->anna->id,
            'date' => $date,
            'target_minutes' => 480,
            'booked_minutes' => 360,
            'shortfall_minutes' => 120,
        ]);
    }

    private function entry(Project $project, User $user, string $start, string $end): TimeEntry {
        $from = CarbonImmutable::parse($start);
        $to = CarbonImmutable::parse($end);
        $order = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'title' => 'Wartung Kessel',
        ]);

        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $user->id,
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
