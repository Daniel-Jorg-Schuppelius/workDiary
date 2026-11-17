<?php
/*
 * Created on   : Mon Nov 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTargetFlatRateTransferTest.php
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
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Finance\BillingTransferService;
use App\Services\Project\TimeTargets\ProjectTimeShortfallDetector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1716: Wie der Rechnungslauf (MVP-1708) schließen Übergabe und
 * Lexoffice-Zeitbeleg den Soll-Befund eines Projekts, das die Arbeit pauschal
 * abrechnet — mit Verweis auf Übergabeposition bzw. Zeitbeleg, ohne
 * „Soll berechnet“. Der Storno der Übergabe öffnet ihn wieder. Berechnet ist
 * ein Befund nur noch mit Auffüllposition.
 */
final class ProjectTargetFlatRateTransferTest extends TestCase {
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
            'country' => 'DE',
            'billing_mode' => BillingMode::Workdiary,
        ]);
        $this->project = $this->targetProject('Heizungswartung');
    }

    public function test_transfer_settles_the_flat_rate_finding_and_its_cancellation_reopens_it(): void {
        $this->flatRule($this->project);
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);
        $service = app(BillingTransferService::class);

        $transfer = $this->transferred();

        $position = $transfer->positions()->sole();
        $this->assertSame(BillingTransferPosition::KIND_TIME, $position->source_kind, 'Nur die Pauschale, keine Auffüllposition.');
        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->status);
        $this->assertNotNull($shortfall->resolved_at);
        $this->assertSame($position->id, $shortfall->billing_transfer_position_id, 'Grund: die Position, die die Zeiten des Tages abrechnet.');
        $this->assertNull($shortfall->billed_minutes, 'Aufgefüllt wurde nichts.');
        $this->assertNull($shortfall->billed_at);
        $this->assertTrue($shortfall->isSettledByFlatRate());
        $this->assertSame(0, ProjectTimeShortfall::query()->unresolved()->count());

        // Anders als nach einer Auffüllung bleibt eine Nachbuchung abrechenbar.
        $late = $this->entry($this->project, '2030-04-01 15:00', '2030-04-01 16:00');
        $this->assertTrue((bool) $late->fresh()?->billable);

        // Der Zeitreiter nennt die Übergabe.
        $this->get(route('projects.show', ['project' => $this->project, 'tab' => 'time', 'show' => 'shortfalls']))->assertOk()
            ->assertSee(__('invoicing.target_settlement.flat_rate_transfer', ['id' => $transfer->id]));

        // Der nächtliche Abgleich öffnet ihn nicht wieder.
        $day = CarbonImmutable::parse('2030-04-01');
        $this->assertSame(0, app(ProjectTimeShortfallDetector::class)->checkRange($this->project, $day, $day, 15)['reopened']);
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->refresh()->status);

        // Storno: wieder offen, ohne Verweis.
        $service->cancel($transfer->refresh(), 'Beleg im Zielsystem verworfen', $this->accountant);
        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Open, $shortfall->status);
        $this->assertNull($shortfall->resolved_at);
        $this->assertNull($shortfall->billing_transfer_position_id);
        $this->assertNull($shortfall->billed_minutes);
        $this->assertFalse($shortfall->isSettledByFlatRate());
    }

    /** Gegenprobe: Nach Stunden bleibt es bei der Auffüllposition; der Befund ist berechnet, nicht pauschal erledigt. */
    public function test_hourly_finding_is_billed_by_its_top_up_position(): void {
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);

        $transfer = $this->transferred();

        $topUp = $transfer->positions()->where('source_kind', BillingTransferPosition::KIND_TARGET)->sole();
        $shortfall->refresh();
        $this->assertSame(120, $shortfall->billed_minutes);
        $this->assertSame($topUp->id, $shortfall->billing_transfer_position_id);
        $this->assertSame(ShortfallStatus::Open, $shortfall->status, 'Die Auffüllung schließt den Befund nicht.');
        $this->assertFalse($shortfall->isSettledByFlatRate());

        app(BillingTransferService::class)->cancel($transfer->refresh(), null, $this->accountant);
        $this->assertNull($shortfall->refresh()->billed_minutes, 'Der Storno gibt die Auffüllung frei.');
        $this->assertSame(ShortfallStatus::Open, $shortfall->status);
    }

    /**
     * Ohne Arbeitszeit des Pauschal-Projekts in der Übergabe bleibt sein Befund
     * offen — und gilt nicht als berechnet (vorher setzte die Übergabe
     * `billed_minutes` ohne Auffüllposition).
     */
    public function test_flat_finding_without_time_of_its_project_stays_open_and_unbilled(): void {
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $idle = $this->targetProject('Lüftungswartung');
        $this->flatRule($idle);
        $idleShortfall = $this->shortfall($idle);

        $this->transferred();

        $idleShortfall->refresh();
        $this->assertSame(ShortfallStatus::Open, $idleShortfall->status);
        $this->assertNull($idleShortfall->billed_minutes);
        $this->assertNull($idleShortfall->billing_transfer_position_id);
    }

    public function test_lexoffice_time_voucher_settles_the_flat_rate_finding(): void {
        $this->flatRule($this->project);
        $entry = $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);
        $this->connectLexoffice();

        $result = app(LexofficePlugin::class)->exportCustomerTime($this->customer, CarbonImmutable::parse('2030-04-01')->startOfDay(), CarbonImmutable::parse('2030-04-30')->endOfDay());

        $voucher = ExternalReference::query()->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)->sole();
        $this->assertSame('voucher-time-1', $result['external_id']);
        $this->assertTrue((bool) $entry->fresh()?->exported);
        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->status);
        $this->assertSame($voucher->id, $shortfall->external_reference_id);
        $this->assertNull($shortfall->billed_minutes);
        $this->assertTrue($shortfall->isSettledByFlatRate());
        $this->get(route('projects.show', ['project' => $this->project, 'tab' => 'time', 'show' => 'shortfalls']))->assertOk()
            ->assertSee(__('invoicing.target_settlement.flat_rate_voucher'));

        $day = CarbonImmutable::parse('2030-04-01');
        app(ProjectTimeShortfallDetector::class)->checkRange($this->project, $day, $day, 15);
        $this->assertSame(ShortfallStatus::Resolved, $shortfall->refresh()->status, 'Der nächtliche Abgleich öffnet ihn nicht wieder.');
    }

    /** Gegenprobe: Ein Zeitbeleg nach Stunden füllt nicht auf und schließt nichts. */
    public function test_lexoffice_time_voucher_leaves_an_hourly_finding_open(): void {
        $this->entry($this->project, '2030-04-01 08:00', '2030-04-01 14:00');
        $shortfall = $this->shortfall($this->project);
        $this->connectLexoffice();

        app(LexofficePlugin::class)->exportCustomerTime($this->customer, CarbonImmutable::parse('2030-04-01')->startOfDay(), CarbonImmutable::parse('2030-04-30')->endOfDay());

        $shortfall->refresh();
        $this->assertSame(ShortfallStatus::Open, $shortfall->status);
        $this->assertNull($shortfall->external_reference_id);
        $this->assertNull($shortfall->billed_minutes);
    }

    private function transferred(): BillingTransfer {
        $service = app(BillingTransferService::class);
        $draft = $service->createDraft($this->customer, TransferChannel::Time, TransferTarget::File, self::PERIOD, null, $this->accountant);
        $confirmed = $service->confirm($draft, $this->accountant);

        return $service->markTransferred($confirmed, null, null, $this->accountant);
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
            'https://api.lexware.io/v1/vouchers*' => FakePluginHttp::response([
                'id' => 'voucher-time-1',
                'resourceUri' => 'https://api.lexware.io/v1/vouchers/voucher-time-1',
            ], 201),
        ]);
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
