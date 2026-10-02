<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpReadToolsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Mcp;

use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Quote;
use App\Services\Customer\Mcp\CustomersTool;
use App\Services\Dashboard\Mcp\KeyFiguresTool;
use App\Services\Diary\Mcp\{OrdersTool, ScheduleTool};
use App\Services\Invoicing\Mcp\{InvoicesTool, OpenItemsTool};
use App\Services\Mcp\WorkDiaryMcpServer;
use App\Services\Project\Mcp\ProjectsTool;
use App\Services\Sales\Mcp\QuotesTool;
use App\Services\Search\Mcp\SearchTool;
use App\Services\Time\Mcp\UnbilledTimeTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** MVP-1063: MCP-Server lesend — Scope, Organisation, Modul-Gate und Policy wie in der Oberfläche. */
class McpReadToolsTest extends TestCase {
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->organization = Organization::factory()->enterprise()->create(['settings' => ['mcp' => ['enabled' => '1']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** Wie SetOrganizationContext im Request: Fabriken verstellen sonst den Spatie-Team-Kontext. */
    private function mcpAs(User $user, array $abilities = ['mcp:read']): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->organization_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        Sanctum::actingAs($user, $abilities);
    }

    /** @return array<string, mixed> */
    private function rpc(string $token, string $method, array $params = []): array {
        return $this->withToken($token)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])
            ->assertOk()
            ->json();
    }

    public function test_http_endpoint_needs_an_mcp_scope_and_lists_the_tools(): void {
        $plain = $this->admin->createToken('rest', ['customers:read'])->plainTextToken;
        $this->withToken($plain)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
        $this->app['auth']->forgetGuards();

        $token = $this->admin->createToken('assistent', ['mcp:read'])->plainTextToken;
        $names = array_column($this->rpc($token, 'tools/list')['result']['tools'], 'name');

        foreach (['customers', 'projects', 'orders', 'schedule', 'quotes', 'invoices', 'open_items', 'unbilled_time', 'key_figures', 'search'] as $tool) {
            $this->assertContains($tool, $names);
        }
    }

    public function test_wildcard_tokens_and_disabled_organizations_get_no_tools(): void {
        $wildcard = $this->admin->createToken('alt')->plainTextToken;
        $this->withToken($wildcard)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden()->assertJsonPath('error', 'insufficient_scope');

        $this->organization->update(['settings' => ['mcp' => ['enabled' => '0']]]);
        $this->app['auth']->forgetGuards();
        $token = $this->admin->createToken('assistent', ['mcp:read'])->plainTextToken;
        $this->assertSame([], $this->rpc($token, 'tools/list')['result']['tools']);
    }

    public function test_tools_stay_inside_the_organization(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bäckerei Eigen']);
        $foreign = Customer::factory()->create(['organization_id' => Organization::factory()->create()->id, 'name' => 'Bäckerei Fremd']);
        $this->mcpAs($this->admin);

        WorkDiaryMcpServer::tool(CustomersTool::class, ['query' => 'Bäckerei'])
            ->assertOk()->assertSee('Bäckerei Eigen')->assertDontSee('Bäckerei Fremd');
        WorkDiaryMcpServer::tool(CustomersTool::class, ['id' => $foreign->sqid])->assertHasErrors();
    }

    public function test_orders_follow_the_worklist_visibility(): void {
        $worker = User::factory()->create(['organization_id' => $this->organization->id]);
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $worker->id, 'title' => 'Eigener Einsatz', 'start_at' => now()->addDay()]);
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'title' => 'Fremder Einsatz', 'start_at' => now()->addDay()]);
        $this->mcpAs($worker);

        WorkDiaryMcpServer::tool(OrdersTool::class, [])->assertOk()->assertSee('Eigener Einsatz')->assertDontSee('Fremder Einsatz');
        WorkDiaryMcpServer::tool(ScheduleTool::class, [])->assertOk()->assertSee('Eigener Einsatz')->assertDontSee('Fremder Einsatz');
    }

    public function test_open_items_report_the_open_amount(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'number' => 'RE-2026-0042',
            'issued_on' => now()->subDays(40),
            'due_on' => now()->subDays(10),
        ]);
        $this->mcpAs($this->admin);

        WorkDiaryMcpServer::tool(OpenItemsTool::class, ['overdue_only' => true])->assertOk()->assertSee('RE-2026-0042')->assertSee('days_overdue');
    }

    public function test_module_gate_and_policy_hide_tools(): void {
        $free = Organization::factory()->free()->create(['settings' => ['mcp' => ['enabled' => '1']]]);
        $user = User::factory()->admin()->create(['organization_id' => $free->id]);
        Quote::factory()->create(['organization_id' => $free->id, 'customer_id' => Customer::factory()->create(['organization_id' => $free->id])->id]);
        $this->mcpAs($user);
        WorkDiaryMcpServer::tool(QuotesTool::class, [])->assertHasErrors();

        $plainUser = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->mcpAs($plainUser);
        WorkDiaryMcpServer::tool(QuotesTool::class, [])->assertHasErrors();
        WorkDiaryMcpServer::tool(SearchTool::class, ['query' => 'Muster'])->assertOk();
    }

    public function test_every_read_tool_answers_for_an_admin(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Quote::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $this->mcpAs($this->admin);

        foreach ([
            CustomersTool::class => ['id' => $customer->sqid],
            ProjectsTool::class => [],
            OrdersTool::class => ['open_only' => true],
            ScheduleTool::class => ['from' => now()->toDateString()],
            QuotesTool::class => ['id' => Quote::query()->firstOrFail()->sqid],
            InvoicesTool::class => ['id' => Invoice::query()->firstOrFail()->sqid],
            OpenItemsTool::class => [],
            UnbilledTimeTool::class => [],
            KeyFiguresTool::class => [],
            SearchTool::class => ['query' => 'Muster'],
        ] as $tool => $arguments) {
            WorkDiaryMcpServer::tool($tool, $arguments)->assertOk()->assertHasNoErrors();
        }
    }
}
