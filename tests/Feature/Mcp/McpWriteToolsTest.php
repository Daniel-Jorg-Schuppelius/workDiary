<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpWriteToolsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Mcp;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Sales\Quote;
use App\Models\Time\TimeEntry;
use App\Services\Customer\Mcp\CreateCustomerTool;
use App\Services\Dispatch\Mcp\RescheduleOrderTool;
use App\Services\Invoicing\Mcp\{CreateInvoiceDraftTool, DunningProposalTool};
use App\Services\Invoicing\QuoteService;
use App\Services\Mcp\WorkDiaryMcpServer;
use App\Services\Sales\Mcp\CreateQuoteDraftTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1064: MCP schreibt nur Entwürfe — Scope `mcp:write`, Audit je Schreibzugriff. */
class McpWriteToolsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'MCP Schreiben', 'settings' => ['mcp' => ['enabled' => '1']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'ACME', 'currency' => 'EUR', 'hourly_rate' => '90.00', 'created_by' => $this->admin->id]);
    }

    private function mcpAs(User $user, array $abilities = ['mcp:write']): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->organization_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        Sanctum::actingAs($user, $abilities);
    }

    private function audited(object $subject): bool {
        return AuditLog::query()->where('event', 'mcp.write')->where('auditable_id', $subject->id)->exists();
    }

    public function test_write_tools_need_the_write_scope(): void {
        $this->mcpAs($this->admin, ['mcp:read']);
        WorkDiaryMcpServer::tool(CreateCustomerTool::class, ['name' => 'Neu'])->assertHasErrors();
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_quote_draft_with_positions(): void {
        $this->mcpAs($this->admin);

        WorkDiaryMcpServer::tool(CreateQuoteDraftTool::class, [
            'customer' => $this->customer->sqid,
            'items' => [
                ['description' => 'Wand streichen', 'quantity' => '42,5', 'unit' => 'm2', 'unit_price' => '12.80'],
                ['description' => 'Abdecken', 'quantity' => 1, 'unit_price' => 85],
            ],
        ])->assertOk()->assertHasNoErrors();

        $quote = Quote::query()->with('items')->firstOrFail();
        $this->assertSame('draft', $quote->status);
        $this->assertCount(2, $quote->items);
        $this->assertSame('629.00', $quote->subtotal?->getAmount());
        $this->assertTrue($this->audited($quote));

        WorkDiaryMcpServer::tool(CreateQuoteDraftTool::class, ['customer' => $this->customer->sqid, 'items' => [['description' => 'x', 'quantity' => 'viel', 'unit_price' => 1]]])->assertHasErrors();
        $this->assertSame(1, Quote::query()->count());
    }

    public function test_invoice_drafts_from_open_time_and_from_an_accepted_quote(): void {
        $project = Project::create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Web', 'status' => ProjectStatus::Active->value, 'created_by' => $this->admin->id]);
        TimeEntry::create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'user_id' => $this->admin->id, 'date' => now()->subDay()->toDateString(), 'minutes' => 120, 'kind' => TimeEntryKind::Work->value, 'billable' => true, 'hourly_rate' => '90.00', 'description' => 'Server gewartet']);
        $this->mcpAs($this->admin);

        WorkDiaryMcpServer::tool(CreateInvoiceDraftTool::class, ['source' => 'time', 'customer' => $this->customer->sqid])->assertOk()->assertHasNoErrors();
        $fromTime = Invoice::query()->firstOrFail();
        $this->assertSame(Invoice::STATUS_DRAFT, $fromTime->status);
        $this->assertSame('180.00', $fromTime->subtotal?->getAmount());
        $this->assertTrue($this->audited($fromTime));

        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $this->customer->id], [['description' => 'Pauschale', 'quantity' => '1', 'unit_price' => '500']], $this->admin);
        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->fresh(), $this->admin);
        $quotes->accept($quote->fresh());
        $this->mcpAs($this->admin);

        WorkDiaryMcpServer::tool(CreateInvoiceDraftTool::class, ['source' => 'quote', 'quote' => $quote->sqid])->assertOk()->assertHasNoErrors();
        $this->assertSame(2, Invoice::query()->where('status', Invoice::STATUS_DRAFT)->count());
    }

    public function test_dunning_proposal_lists_ripe_invoices(): void {
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'status' => Invoice::STATUS_ISSUED, 'number' => 'RE-2026-0099', 'issued_on' => now()->subDays(60), 'due_on' => now()->subDays(30)]);
        $this->mcpAs($this->admin, ['mcp:read']);

        WorkDiaryMcpServer::tool(DunningProposalTool::class, [])->assertOk()->assertSee('RE-2026-0099')->assertSee('next_level');
    }

    public function test_reschedule_moves_the_order_and_blocks_on_overlap(): void {
        $worker = User::factory()->create(['organization_id' => $this->organization->id]);
        $day = now()->addDays(3)->startOfDay();
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'assigned_user_id' => $worker->id, 'start_at' => $day->copy()->setTime(8, 0), 'end_at' => $day->copy()->setTime(12, 0)]);
        $order = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'assigned_user_id' => $worker->id, 'start_at' => $day->copy()->setTime(13, 0), 'end_at' => $day->copy()->setTime(15, 0)]);
        $this->mcpAs($this->admin);

        WorkDiaryMcpServer::tool(RescheduleOrderTool::class, ['id' => $order->sqid, 'start_at' => $day->copy()->setTime(10, 0)->toIso8601String(), 'end_at' => $day->copy()->setTime(11, 0)->toIso8601String()])->assertHasErrors();
        $this->assertSame(13, (int) $order->fresh()->start_at->format('G'));

        WorkDiaryMcpServer::tool(RescheduleOrderTool::class, ['id' => $order->sqid, 'start_at' => $day->copy()->addDay()->setTime(9, 0)->toIso8601String(), 'end_at' => $day->copy()->addDay()->setTime(11, 0)->toIso8601String()])->assertOk()->assertHasNoErrors();
        $this->assertTrue($order->fresh()->start_at->equalTo($day->copy()->addDay()->setTime(9, 0)));
        $this->assertTrue($this->audited($order));
    }

    public function test_customer_with_address_and_no_duplicate_vat_id(): void {
        $this->mcpAs($this->admin);

        WorkDiaryMcpServer::tool(CreateCustomerTool::class, ['name' => 'Bäckerei Korn', 'company' => 'Bäckerei Korn GmbH', 'vat_id' => 'DE123456789', 'address_street' => 'Hauptstraße 1', 'address_zip' => '97070', 'address_city' => 'Würzburg', 'country' => 'DE'])->assertOk()->assertHasNoErrors();
        $customer = Customer::query()->where('name', 'Bäckerei Korn')->firstOrFail();
        $this->assertSame('Würzburg', $customer->primaryAddress()?->city);
        $this->assertTrue($this->audited($customer));

        WorkDiaryMcpServer::tool(CreateCustomerTool::class, ['name' => 'Korn doppelt', 'vat_id' => 'DE123456789'])->assertOk()->assertSee('"created":false');
        $this->assertSame(0, Customer::query()->where('name', 'Korn doppelt')->count());
    }
}
