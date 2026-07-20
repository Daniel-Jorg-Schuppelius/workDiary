<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchBoardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Diary\{DispatchStatus, Mode, Status};
use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketStatus};
use App\Models\{Customer, DiaryEntry, Organization, ServiceTicket, User};
use App\Services\Dispatch\{DispatchBoardService, DispatchStatusResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DispatchBoardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function dispatcher(): User {
        // teamleitung trägt dispatch.viewAny (PermissionsSeeder).
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function worker(): User {
        return User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function entry(array $overrides = []): DiaryEntry {
        return DiaryEntry::factory()->create(array_replace([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker()->id,
            'title' => 'Auftrag ' . fake()->word(),
            'mode' => Mode::Fixed->value,
            'status' => Status::Open->value,
            'start_at' => Carbon::parse('2026-07-01 09:00'),
            'end_at' => Carbon::parse('2026-07-01 12:00'),
        ], $overrides));
    }

    // ─── Berechtigung ───────────────────────────────────────────────────────

    public function test_worker_without_dispatch_permission_cannot_open_board(): void {
        $this->actingAs($this->worker())
            ->get(route('dispatch.board'))
            ->assertForbidden();
    }

    public function test_dispatcher_can_open_board_and_map(): void {
        $this->actingAs($this->dispatcher())->get(route('dispatch.board'))->assertOk();
        $this->actingAs($this->dispatcher())->get(route('dispatch.map'))->assertOk();
    }

    // ─── Gruppierung nach Status / Mitarbeiter ──────────────────────────────

    public function test_board_groups_entries_by_dispatch_status_in_range(): void {
        $assigned = $this->worker();
        $this->entry(['assigned_user_id' => $assigned->id]); // → Planned
        $this->entry(['status' => Status::InProgress->value]); // → EnRoute

        $service = app(DispatchBoardService::class);
        $from = \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay();
        $to = \Carbon\CarbonImmutable::parse('2026-07-01')->endOfDay();

        $items = $service->items($service->entries($from, $to));
        $grouped = $service->groupByDispatchStatus($items);

        $this->assertCount(1, $grouped[DispatchStatus::Planned->value]);
        $this->assertCount(1, $grouped[DispatchStatus::EnRoute->value]);
    }

    public function test_board_groups_by_employee(): void {
        $a = $this->worker();
        $b = $this->worker();
        $this->entry(['assigned_user_id' => $a->id]);
        $this->entry(['assigned_user_id' => $b->id]);

        $service = app(DispatchBoardService::class);
        $from = \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay();
        $to = \Carbon\CarbonImmutable::parse('2026-07-01')->endOfDay();

        $items = $service->items($service->entries($from, $to));
        $byEmployee = $service->groupByEmployee($items);

        $this->assertArrayHasKey((int) $a->id, $byEmployee);
        $this->assertArrayHasKey((int) $b->id, $byEmployee);
    }

    // ─── Filter: Zeitraum + Mitarbeiter ─────────────────────────────────────

    public function test_range_filter_excludes_entries_outside_period(): void {
        $this->entry(['start_at' => Carbon::parse('2026-07-01 09:00'), 'end_at' => Carbon::parse('2026-07-01 12:00')]);
        $this->entry(['start_at' => Carbon::parse('2026-08-01 09:00'), 'end_at' => Carbon::parse('2026-08-01 12:00')]);

        $service = app(DispatchBoardService::class);
        $from = \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay();
        $to = \Carbon\CarbonImmutable::parse('2026-07-01')->endOfDay();

        $this->assertSame(1, $service->entries($from, $to)->count());
    }

    public function test_employee_filter_restricts_entries(): void {
        $a = $this->worker();
        $this->entry(['assigned_user_id' => $a->id]);
        $this->entry(['assigned_user_id' => $this->worker()->id]);

        $service = app(DispatchBoardService::class);
        $from = \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay();
        $to = \Carbon\CarbonImmutable::parse('2026-07-01')->endOfDay();

        $this->assertSame(1, $service->entries($from, $to, (int) $a->id)->count());
    }

    // ─── SLA-Risiko (Feature 010 wiederverwendet) ───────────────────────────

    public function test_sla_breached_ticket_marks_customer_entry_as_risk(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $entry = $this->entry(['customer_id' => $customer->id]);

        ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'status' => ServiceTicketStatus::Reported->value,
            'priority' => ServiceTicketPriority::High->value,
            'reported_at' => Carbon::now()->subDays(2),
            'resolution_due_at' => Carbon::now()->subDay(), // überfällig → breached
            'resolved_at' => null,
        ]);

        $service = app(DispatchBoardService::class);
        $from = \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay();
        $to = \Carbon\CarbonImmutable::parse('2026-07-01')->endOfDay();
        $items = $service->items($service->entries($from, $to));

        $match = collect($items)->firstWhere(fn($i) => (int) $i['entry']->id === (int) $entry->id);
        $this->assertSame('breached', $match['sla']->value);
    }

    public function test_map_only_risk_filter_keeps_only_sla_risk_markers(): void {
        $riskCustomer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'address_lat' => 52.52,
            'address_lng' => 13.40,
        ]);
        $okCustomer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'address_lat' => 48.13,
            'address_lng' => 11.58,
        ]);
        $this->entry(['customer_id' => $riskCustomer->id]);
        $this->entry(['customer_id' => $okCustomer->id]);

        ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $riskCustomer->id,
            'status' => ServiceTicketStatus::Reported->value,
            'reported_at' => Carbon::now()->subDays(2),
            'resolution_due_at' => Carbon::now()->subDay(),
            'resolved_at' => null,
        ]);

        $response = $this->actingAs($this->dispatcher())
            ->get(route('dispatch.map', ['from' => '2026-07-01', 'to' => '2026-07-01', 'risk' => 1]))
            ->assertOk();

        $markers = $response->viewData('markers');
        $this->assertCount(1, $markers);
        $this->assertSame('#dc2626', $markers[0]['color']);
    }

    // ─── Terminmodus-Layer + Popup-Link + Prio-Filter (Vollaudit M13/M14) ───

    public function test_map_markers_separate_mode_layers_and_link_to_entry(): void {
        $fixed = $this->entry(['address_lat' => 52.52, 'address_lng' => 13.40]);
        // Window-Einträge matcht der modus-bewusste Zeitraumfilter über die Fenstergrenzen.
        $window = $this->entry([
            'mode' => Mode::Window->value,
            'window_start_date' => '2026-06-29',
            'window_end_date' => '2026-07-03',
            'address_lat' => 50.11,
            'address_lng' => 8.68,
        ]);
        $backlog = $this->entry(['mode' => Mode::Backlog->value, 'address_lat' => 48.13, 'address_lng' => 11.58]);

        $response = $this->actingAs($this->dispatcher())
            ->get(route('dispatch.map', ['from' => '2026-07-01', 'to' => '2026-07-01']))
            ->assertOk();

        $markers = collect($response->viewData('markers'));
        $byTitle = fn(DiaryEntry $e) => $markers->firstWhere('label', $e->title);

        $this->assertSame('fixed', $byTitle($fixed)['layer']);
        $this->assertSame('flexible', $byTitle($window)['layer']);
        $this->assertSame('backlog', $byTitle($backlog)['layer']);
        $this->assertStringContainsString(route('diary.show', $fixed), $byTitle($fixed)['popup']);

        $layerKeys = array_column($response->viewData('layers'), 'key');
        $this->assertSame(['risk', 'fixed', 'flexible', 'backlog'], $layerKeys);
    }

    public function test_priority_filter_restricts_board_and_map(): void {
        $urgent = $this->entry(['priority' => 'urgent', 'address_lat' => 52.52, 'address_lng' => 13.40]);
        $this->entry(['priority' => 'normal', 'address_lat' => 48.13, 'address_lng' => 11.58]);

        $map = $this->actingAs($this->dispatcher())
            ->get(route('dispatch.map', ['from' => '2026-07-01', 'to' => '2026-07-01', 'priority' => 'urgent']))
            ->assertOk();
        $this->assertCount(1, $map->viewData('markers'));
        $this->assertSame($urgent->title, $map->viewData('markers')[0]['label']);

        $board = $this->actingAs($this->dispatcher())
            ->get(route('dispatch.board', ['from' => '2026-07-01', 'to' => '2026-07-01', 'priority' => 'urgent']))
            ->assertOk();
        $this->assertSame(1, $board->viewData('total'));
    }

    // ─── Cross-Org-Isolation ────────────────────────────────────────────────

    public function test_board_does_not_expose_foreign_org_entries(): void {
        // Eintrag in der eigenen Org.
        $this->entry();

        // Fremde Org mit eigenem Auftrag.
        $foreignOrg = Organization::factory()->create();
        $foreignUser = User::factory()->create(['organization_id' => $foreignOrg->id]);
        DiaryEntry::factory()->create([
            'organization_id' => $foreignOrg->id,
            'user_id' => $foreignUser->id,
            'title' => 'Fremdauftrag',
            'mode' => Mode::Fixed->value,
            'status' => Status::Open->value,
            'start_at' => Carbon::parse('2026-07-01 09:00'),
            'end_at' => Carbon::parse('2026-07-01 12:00'),
        ]);

        $service = app(DispatchBoardService::class);
        $from = \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay();
        $to = \Carbon\CarbonImmutable::parse('2026-07-01')->endOfDay();

        $entries = $service->entries($from, $to);
        $this->assertSame(1, $entries->count());
        $this->assertNotContains('Fremdauftrag', $entries->pluck('title')->all());
    }

    // ─── Resolver-Konsistenz ────────────────────────────────────────────────

    public function test_items_use_dispatch_status_resolver(): void {
        $entry = $this->entry();
        app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::Confirmed);

        $service = app(DispatchBoardService::class);
        $from = \Carbon\CarbonImmutable::parse('2026-07-01')->startOfDay();
        $to = \Carbon\CarbonImmutable::parse('2026-07-01')->endOfDay();
        $items = $service->items($service->entries($from, $to));

        $match = collect($items)->firstWhere(fn($i) => (int) $i['entry']->id === (int) $entry->id);
        $this->assertSame(DispatchStatus::Confirmed, $match['dispatch']);
    }
}
