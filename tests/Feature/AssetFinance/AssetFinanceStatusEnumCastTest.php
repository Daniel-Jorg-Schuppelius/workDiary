<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetFinance;

use App\Enums\AssetFinance\{AssetFinanceDeadlineKind, AssetFinanceDeadlineStatus, AssetFinanceEndKind, AssetFinanceEndProcessStatus, AssetFinanceKind, AssetFinanceRateScheduleStatus, AssetFinanceStatus};
use App\Models\Asset\Asset;
use App\Models\AssetFinance\{AssetFinanceContract, AssetFinanceDeadline, AssetFinanceEndProcess};
use App\Models\Platform\User;
use App\Services\AssetFinance\AssetFinanceService;
use App\Services\AssetFinance\Demo\AssetFinanceDemoBlock;
use App\Services\Demo\Contracts\DemoSeedContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 6): Fristen, Ende-Prozesse und
 * Ratenzeilen der Leasingakte führen ihren Status als Enum. Ein Vergleich
 * gegen die frühere Zeichenkette ist danach still falsch — die Aktion fehlt
 * oder bleibt stehen, obwohl sie nicht mehr gilt.
 */
final class AssetFinanceStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private AssetFinanceContract $contract;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Radlader']);

        $service = app(AssetFinanceService::class);
        $this->contract = $service->create($this->organization, $this->admin, [
            'kind' => AssetFinanceKind::OperatingLease->value,
            'partner_name' => 'Muster-Leasing GmbH',
            'starts_on' => '2026-10-01',
            'ends_on' => '2027-09-01',
            'payment_rhythm' => 'monthly',
            'rate_amount' => '400.00',
        ], [$asset->id]);
        $service->activate($this->contract, $this->admin);
    }

    private function deadline(AssetFinanceDeadlineStatus $status, string $dueOn, int $warnDays = 30): AssetFinanceDeadline {
        return $this->contract->deadlines()->create([
            'organization_id' => $this->organization->id,
            'kind' => AssetFinanceDeadlineKind::Termination,
            'due_on' => $dueOn,
            'warn_days_before' => $warnDays,
            'status' => $status,
        ]);
    }

    private function endProcess(AssetFinanceEndProcessStatus $status): AssetFinanceEndProcess {
        return $this->contract->endProcesses()->create([
            'organization_id' => $this->organization->id,
            'kind' => AssetFinanceEndKind::Return,
            'status' => $status,
        ]);
    }

    private function page(string $url): string {
        return (string) $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    }

    /** Eigene Meldung statt `assertSee`: Dessen Fehlertext trägt die ganze Seite. */
    private function assertPageHas(string $needle, string $html): void {
        $this->assertTrue(str_contains($html, $needle), "Auf der Seite fehlt: {$needle}");
    }

    private function assertPageLacks(string $needle, string $html): void {
        $this->assertFalse(str_contains($html, $needle), "Auf der Seite steht unerwartet: {$needle}");
    }

    public function test_deadline_calendar_offers_completion_and_marks_the_warning_only_for_open_deadlines(): void {
        $due = $this->deadline(AssetFinanceDeadlineStatus::Open, '2026-10-15');
        $later = $this->deadline(AssetFinanceDeadlineStatus::Open, '2027-06-30');
        $done = $this->deadline(AssetFinanceDeadlineStatus::Done, '2026-10-10');

        // Ohne Filter: nur offene Fristen; rot ist allein die Frist in der Vorwarnzeit.
        $html = $this->page(route('asset-finance.deadlines.index'));
        $this->assertPageHas('action="' . route('asset-finance.deadlines.complete', $due) . '"', $html);
        $this->assertPageHas('action="' . route('asset-finance.deadlines.complete', $later) . '"', $html);
        $this->assertPageLacks('action="' . route('asset-finance.deadlines.complete', $done) . '"', $html);
        $this->assertSame(1, substr_count($html, 'text-error font-medium'));

        $html = $this->page(route('asset-finance.deadlines.index', ['status' => 'done']));
        $this->assertPageHas('<option value="done" selected>' . e(AssetFinanceDeadlineStatus::Done->label()) . '</option>', $html);
        $this->assertPageLacks('action="' . route('asset-finance.deadlines.complete', $done) . '"', $html);
        $this->assertPageLacks('action="' . route('asset-finance.deadlines.complete', $due) . '"', $html);
        $this->assertSame(0, substr_count($html, 'text-error font-medium'));
    }

    public function test_deadline_filter_lists_every_status(): void {
        $html = $this->page(route('asset-finance.deadlines.index'));

        foreach (AssetFinanceDeadlineStatus::cases() as $status) {
            $this->assertPageHas('<option value="' . $status->value . '" >' . e($status->label()) . '</option>', $html);
        }
    }

    public function test_contract_file_offers_the_actions_that_fit_the_stored_status(): void {
        $open = $this->deadline(AssetFinanceDeadlineStatus::Open, '2026-10-15');
        $missed = $this->deadline(AssetFinanceDeadlineStatus::Missed, '2026-09-30');
        $running = $this->endProcess(AssetFinanceEndProcessStatus::InProgress);
        $completed = $this->endProcess(AssetFinanceEndProcessStatus::Completed);
        $this->contract->rateSchedules()->orderBy('due_on')->firstOrFail()->update(['status' => AssetFinanceRateScheduleStatus::Paid]);

        $html = $this->page(route('asset-finance.show', $this->contract));

        $this->assertPageHas('action="' . route('asset-finance.deadlines.complete', $open) . '"', $html);
        $this->assertPageLacks('action="' . route('asset-finance.deadlines.complete', $missed) . '"', $html);
        // Die Vorwarnung gilt nur der offenen Frist, nicht der versäumten.
        $this->assertSame(1, substr_count($html, e(__('Vorwarnzeit läuft'))));
        $this->assertPageHas('action="' . route('asset-finance.ends.complete', $running) . '"', $html);
        $this->assertPageLacks('action="' . route('asset-finance.ends.complete', $completed) . '"', $html);
        foreach ([AssetFinanceDeadlineStatus::Missed, AssetFinanceEndProcessStatus::Completed, AssetFinanceRateScheduleStatus::Paid, AssetFinanceRateScheduleStatus::Planned] as $status) {
            $this->assertSame(1, preg_match('/badge-outline"[^>]*>\s*' . preg_quote(e($status->label()), '/') . '\s*</u', $html), "Kein Abzeichen „{$status->label()}“.");
        }
    }

    public function test_completing_a_deadline_marks_it_done(): void {
        $deadline = $this->deadline(AssetFinanceDeadlineStatus::Open, '2026-10-15');

        $this->actingAs($this->admin)->post(route('asset-finance.deadlines.complete', $deadline))->assertRedirect();

        $this->assertSame(AssetFinanceDeadlineStatus::Done, $deadline->fresh()->status);
        $this->assertFalse($deadline->fresh()->isDueForWarning());
    }

    /** Der Vergleich gegen `'completed'` griff nicht mehr: der zweite Abschluss lief in die Statusprüfung der Akte. */
    public function test_completing_an_end_process_twice_changes_nothing(): void {
        $this->actingAs($this->admin)->post(route('asset-finance.ends.store', $this->contract), ['kind' => AssetFinanceEndKind::Return->value])
            ->assertRedirect();
        $endProcess = $this->contract->endProcesses()->sole();
        $this->assertSame(AssetFinanceEndProcessStatus::InProgress, $endProcess->status);

        $service = app(AssetFinanceService::class);
        $service->completeEndProcess($endProcess, $this->admin);
        $decidedAt = $endProcess->fresh()->decided_at;
        $this->assertSame(AssetFinanceEndProcessStatus::Completed, $endProcess->fresh()->status);
        $this->assertSame(AssetFinanceStatus::Returned, $this->contract->fresh()->status);

        $this->travel(1)->hours();
        $service->completeEndProcess($endProcess->fresh(), $this->admin);
        $this->actingAs($this->admin)->post(route('asset-finance.ends.complete', $endProcess))->assertSessionHasNoErrors();

        $this->assertTrue($decidedAt->equalTo($endProcess->fresh()->decided_at));
        $this->assertSame(AssetFinanceStatus::Returned, $this->contract->fresh()->status);
    }

    /** Der Demo-Block fängt jeden Fehler ab und meldet dann nur „0 angelegt“. */
    public function test_demo_block_seeds_a_contract_with_planned_rates_and_an_open_deadline(): void {
        $context = new DemoSeedContext($this->organization, $this->admin, collect([$this->admin]), [], null, null, null);

        $this->assertSame(['asset_finance' => 1], (new AssetFinanceDemoBlock)->seed($context));

        $contract = AssetFinanceContract::query()->orderByDesc('id')->firstOrFail();
        $this->assertNotSame($this->contract->id, $contract->id);
        $this->assertSame(12, $contract->rateSchedules()->planned()->count());
        // Die Frist legt der Block ohne Status an — es gilt die Spaltenvorgabe.
        $this->assertSame(AssetFinanceDeadlineStatus::Open, $contract->deadlines()->sole()->status);
    }

    public function test_report_counts_missed_deadlines_and_referenced_rates(): void {
        $this->deadline(AssetFinanceDeadlineStatus::Missed, '2026-09-30');
        $this->deadline(AssetFinanceDeadlineStatus::Open, '2026-12-31');
        $this->contract->rateSchedules()->orderBy('due_on')->firstOrFail()->update(['status' => AssetFinanceRateScheduleStatus::Paid]);

        $this->actingAs($this->admin)->get(route('asset-finance.reports.index'))->assertOk()
            ->assertViewHas('missedDeadlines', 1)
            ->assertViewHas('openDeadlines', 1)
            ->assertViewHas('referencedTotal', 400.0);
    }
}
