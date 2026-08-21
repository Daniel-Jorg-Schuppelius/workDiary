<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyPeriodTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Warranty;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\Warranty\{WarrantyBasis, WarrantySide, WarrantyStatus};
use App\Models\{Customer, Organization, Project, Supplier, User};
use App\Models\Notification\NotificationRule;
use App\Models\Warranty\WarrantyPeriod;
use App\Services\Warranty\WarrantyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gewährleistungsfristen (Feature 115, MVP-604).
 *
 * Der Kern ist der Vergleich beider Seiten: Eine Sub-Frist, die VOR der
 * eigenen endet, ist der teure Fall — danach haftet man allein für einen
 * Mangel, den ein anderer verursacht hat.
 */
class WarrantyPeriodTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Project $project;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->org->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function period(WarrantySide $side, string $start, ?string $end = null, WarrantyBasis $basis = WarrantyBasis::Vob4Years, array $attributes = []): WarrantyPeriod {
        return app(WarrantyService::class)->create($side, $basis, $start, $end, $attributes['override_reason'] ?? null, $this->admin, [
            'organization_id' => $this->org->id,
            'project_id' => $this->project->id,
            'customer_id' => $side === WarrantySide::Owed ? $this->customer->id : null,
            'supplier_id' => $side === WarrantySide::Claimable ? $this->supplier->id : null,
            'responsible_user_id' => $this->admin->id,
        ] + array_diff_key($attributes, ['override_reason' => null]));
    }

    public function test_end_date_follows_the_legal_basis(): void {
        $bgb = $this->period(WarrantySide::Owed, '2026-01-15', null, WarrantyBasis::Bgb5Years);
        $vob = $this->period(WarrantySide::Owed, '2026-01-15', null, WarrantyBasis::Vob4Years);

        $this->assertSame('2031-01-15', $bgb->ends_on->toDateString());
        $this->assertSame('2030-01-15', $vob->ends_on->toDateString());
        $this->assertFalse($bgb->isOverridden());
    }

    public function test_custom_basis_requires_an_end_date(): void {
        $this->expectException(\RuntimeException::class);
        $this->period(WarrantySide::Owed, '2026-01-15', null, WarrantyBasis::Custom);
    }

    /** Ein abweichendes Ende ohne Begründung wäre später nicht nachvollziehbar. */
    public function test_deviating_end_date_requires_a_reason(): void {
        $this->expectException(\RuntimeException::class);
        $this->period(WarrantySide::Owed, '2026-01-15', '2029-01-15', WarrantyBasis::Vob4Years);
    }

    public function test_deviating_end_date_is_accepted_with_a_reason(): void {
        $period = $this->period(WarrantySide::Owed, '2026-01-15', '2029-01-15', WarrantyBasis::Vob4Years, [
            'override_reason' => 'Vertraglich auf 3 Jahre verkürzt.',
        ]);

        $this->assertSame('2029-01-15', $period->ends_on->toDateString());
        $this->assertTrue($period->isOverridden());
    }

    public function test_acceptance_date_is_the_start(): void {
        $protocol = \App\Models\Protocol::factory()->create([
            'organization_id' => $this->org->id,
            'type' => \App\Enums\Protocol\ProtocolType::Acceptance->value,
            'occurred_at' => '2026-03-10 09:00:00',
        ]);

        $period = app(WarrantyService::class)->fromAcceptance($protocol, WarrantySide::Owed, WarrantyBasis::Vob4Years, actor: $this->admin);

        $this->assertSame('2026-03-10', $period->starts_on->toDateString());
        $this->assertSame('2030-03-10', $period->ends_on->toDateString());
    }

    // ── Der teure Fall ──────────────────────────────────────────────────

    public function test_subcontractor_period_ending_before_our_own_is_flagged(): void {
        $this->period(WarrantySide::Owed, now()->subYears(4)->toDateString(), now()->addYear()->toDateString(), WarrantyBasis::Custom);
        $sub = $this->period(WarrantySide::Claimable, now()->subYears(4)->toDateString(), now()->addDays(30)->toDateString(), WarrantyBasis::Custom);

        $flagged = app(WarrantyService::class)->subcontractorsEndingFirst((int) $this->org->id);

        $this->assertSame([$sub->id], $flagged->pluck('id')->all());
    }

    /** Endet die Sub-Frist SPÄTER, gibt es nichts zu warnen. */
    public function test_subcontractor_period_ending_later_is_not_flagged(): void {
        $this->period(WarrantySide::Owed, now()->subYears(4)->toDateString(), now()->addDays(30)->toDateString(), WarrantyBasis::Custom);
        $this->period(WarrantySide::Claimable, now()->subYears(4)->toDateString(), now()->addYear()->toDateString(), WarrantyBasis::Custom);

        $this->assertCount(0, app(WarrantyService::class)->subcontractorsEndingFirst((int) $this->org->id));
    }

    /** Eine Sub-Frist, die erst in Jahren endet, ist Rauschen — keine Handlung. */
    public function test_a_distant_subcontractor_period_is_not_flagged_yet(): void {
        $this->period(WarrantySide::Owed, now()->subYear()->toDateString(), now()->addYears(4)->toDateString(), WarrantyBasis::Custom);
        $this->period(WarrantySide::Claimable, now()->subYear()->toDateString(), now()->addYears(3)->toDateString(), WarrantyBasis::Custom);

        $this->assertCount(0, app(WarrantyService::class)->subcontractorsEndingFirst((int) $this->org->id));
    }

    /** Ohne eigene Frist im Projekt gibt es keinen Vergleich. */
    public function test_without_an_own_period_nothing_is_flagged(): void {
        $this->period(WarrantySide::Claimable, now()->subYears(4)->toDateString(), now()->addDays(30)->toDateString(), WarrantyBasis::Custom);

        $this->assertCount(0, app(WarrantyService::class)->subcontractorsEndingFirst((int) $this->org->id));
    }

    public function test_scanner_reminds_before_the_period_ends(): void {
        $this->rule(NotificationEvent::WarrantyExpiring);
        $this->period(WarrantySide::Owed, now()->subYears(4)->toDateString(), now()->addDays(60)->toDateString(), WarrantyBasis::Custom);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::WarrantyExpiring->value,
        ]);
    }

    public function test_scanner_warns_when_the_sub_period_ends_first(): void {
        $this->rule(NotificationEvent::WarrantySubcontractorEndsFirst);
        $this->period(WarrantySide::Owed, now()->subYears(4)->toDateString(), now()->addYear()->toDateString(), WarrantyBasis::Custom);
        $this->period(WarrantySide::Claimable, now()->subYears(4)->toDateString(), now()->addDays(30)->toDateString(), WarrantyBasis::Custom);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::WarrantySubcontractorEndsFirst->value,
        ]);
    }

    public function test_closing_a_period_ends_the_reminders(): void {
        $period = $this->period(WarrantySide::Owed, now()->subYears(4)->toDateString(), now()->addDays(60)->toDateString(), WarrantyBasis::Custom);

        app(WarrantyService::class)->close($period, $this->admin);

        $this->assertSame(WarrantyStatus::Closed, $period->refresh()->status);
        $this->assertFalse($period->isRunning());
    }

    public function test_index_shows_both_sides_and_the_critical_block(): void {
        $this->period(WarrantySide::Owed, now()->subYears(4)->toDateString(), now()->addYear()->toDateString(), WarrantyBasis::Custom);
        $this->period(WarrantySide::Claimable, now()->subYears(4)->toDateString(), now()->addDays(30)->toDateString(), WarrantyBasis::Custom);

        $response = $this->actingAs($this->admin)->get(route('warranties.index'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('openOwed'));
        $this->assertSame(1, $response->viewData('openClaimable'));
        $this->assertCount(1, $response->viewData('critical'));
    }

    public function test_endpoint_creates_from_sqid_inputs(): void {
        $this->actingAs($this->admin)->post(route('warranties.store'), [
            'side' => WarrantySide::Claimable->value,
            'basis' => WarrantyBasis::Vob4Years->value,
            'starts_on' => '2026-05-01',
            'project_id' => $this->project->sqid,
            'supplier_id' => $this->supplier->sqid,
            'trade' => 'Elektro',
        ])->assertRedirect(route('warranties.index'));

        $period = WarrantyPeriod::query()->sole();
        $this->assertSame('2030-05-01', $period->ends_on->toDateString());
        $this->assertSame((int) $this->supplier->id, (int) $period->supplier_id);
    }

    private function rule(NotificationEvent $event): void {
        \Illuminate\Support\Facades\Notification::fake();
        NotificationRule::factory()->forEvent($event)->create([
            'organization_id' => $this->org->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
            'recipient_user_ids' => [$this->admin->id],
        ]);
    }
}
