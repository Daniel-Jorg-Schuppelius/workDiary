<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdminListPaginationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\B2b\B2bOrderStatus;
use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase};
use App\Enums\Export\ExportRunState;
use App\Enums\User\Permission;
use App\Models\B2b\B2bOrder;
use App\Models\Backup\{BackupGeneration, BackupTargetConnection};
use App\Models\Classification\ClassificationRequirement;
use App\Models\Domain\DomainProviderConnection;
use App\Models\Finance\CostCenterRule;
use App\Models\Integration\{ExportRun, WebhookEndpoint};
use App\Models\Invoicing\InvoiceMailTemplate;
use App\Models\Platform\{User, UserBadge, UserTerminalPin};
use App\Models\Surcharge\SurchargeRule;
use App\Models\Time\WageTypeMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k4-14 (Paket a): die Verwaltungslisten
 * blättern, und was über der Liste zählt oder sortiert, rechnet weiter über
 * die ganze Menge.
 */
class AdminListPaginationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
    }

    public function test_classification_requirements_count_all_rules_on_every_page(): void {
        foreach (range(1, 27) as $i) {
            ClassificationRequirement::factory()->create([
                'organization_id' => $this->organization->id,
                'entry_type_code' => sprintf('typ-%02d', $i),
                'required_domain' => ClassificationDomain::Result->value,
                'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.classification-requirements.index'))->assertOk();
        $this->assertPage($first, 'requirements', 27, 25);
        $first->assertSee('27 Pflichtregeln angezeigt')->assertSee('typ-01')->assertDontSee('typ-26');

        $second = $this->get(route('admin.classification-requirements.index', ['page' => 2]))->assertOk();
        $this->assertPage($second, 'requirements', 27, 2);
        $second->assertSee('27 Pflichtregeln angezeigt')->assertSee('typ-27')->assertDontSee('typ-01');
    }

    public function test_cost_center_rules_page_by_priority(): void {
        $this->admin->givePermissionTo([Permission::CostCenterRuleViewAny->value, Permission::CostCenterRuleManage->value]);
        foreach (range(1, 26) as $i) {
            CostCenterRule::query()->create(['organization_id' => $this->organization->id, 'cost_center' => 'KST-' . $i, 'priority' => $i]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.cost-center-rules.index'))->assertOk();
        $this->assertPage($first, 'rules', 26, 25);
        $this->assertSame(26, $first->viewData('rules')->items()[0]->priority);

        $second = $this->get(route('admin.cost-center-rules.index', ['page' => 2]))->assertOk();
        $this->assertPage($second, 'rules', 26, 1);
        $this->assertSame(1, $second->viewData('rules')->items()[0]->priority);
    }

    public function test_surcharge_rules_page(): void {
        SurchargeRule::factory()->count(26)->create(['organization_id' => $this->organization->id]);

        $this->assertPage($this->actingAs($this->admin)->get(route('admin.surcharge-rules.index'))->assertOk(), 'rules', 26, 25);
        $this->assertPage($this->get(route('admin.surcharge-rules.index', ['page' => 2]))->assertOk(), 'rules', 26, 1);
    }

    public function test_wage_type_mappings_page_below_the_delivery_card(): void {
        $this->admin->givePermissionTo([Permission::WageTypeMappingViewAny->value, Permission::WageTypeMappingManage->value]);
        foreach (range(1, 26) as $i) {
            WageTypeMapping::query()->create([
                'organization_id' => $this->organization->id,
                'profile' => 'datev',
                'wage_type' => sprintf('art_%02d', $i),
                'external_code' => (string) (1000 + $i),
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.wage-type-mappings.index'))->assertOk();
        $this->assertPage($first, 'mappings', 26, 25);
        $first->assertSee(__('wage_types.title.delivery'));

        $second = $this->get(route('admin.wage-type-mappings.index', ['page' => 2]))->assertOk();
        $this->assertPage($second, 'mappings', 26, 1);
        $second->assertSee('art_26')->assertSee(__('wage_types.title.delivery'));
    }

    public function test_webhook_endpoints_page_with_their_last_deliveries(): void {
        $endpoints = WebhookEndpoint::factory()->count(26)->create(['organization_id' => $this->organization->id]);

        $first = $this->actingAs($this->admin)->get(route('admin.webhooks.index'))->assertOk();
        $this->assertPage($first, 'endpoints', 26, 25);
        $this->assertTrue($first->viewData('endpoints')->items()[0]->relationLoaded('deliveries'));

        $second = $this->get(route('admin.webhooks.index', ['page' => 2]))->assertOk();
        $this->assertPage($second, 'endpoints', 26, 1);
        $this->assertSame($endpoints->min('id'), $second->viewData('endpoints')->items()[0]->id);
    }

    public function test_domain_provider_connections_page(): void {
        $this->admin->givePermissionTo([Permission::DomainProviderView->value, Permission::DomainProviderManage->value]);
        DomainProviderConnection::factory()->count(26)->create(['organization_id' => $this->organization->id]);

        $this->assertPage($this->actingAs($this->admin)->get(route('admin.domain-provider.index'))->assertOk(), 'connections', 26, 25);
        $this->assertPage($this->get(route('admin.domain-provider.index', ['page' => 2]))->assertOk(), 'connections', 26, 1);
    }

    /** Bisher zeigte die Seite still nur die 15 jüngsten Läufe; der Zähler nennt jetzt alle. */
    public function test_export_runs_page_and_the_heading_counts_all_runs(): void {
        foreach (range(1, 17) as $i) {
            ExportRun::create([
                'organization_id' => $this->organization->id,
                'entity' => 'customers',
                'format' => 'csv',
                'state' => ExportRunState::Ready,
                'output_filename' => 'lauf-' . $i . '.csv',
                'storage_path' => 'exports/data/' . $this->organization->id . '/lauf-' . $i . '.csv',
                'rows_total' => $i,
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.data.index'))->assertOk();
        $this->assertPage($first, 'runs', 17, 15);
        $first->assertSee('(17)');

        $second = $this->get(route('admin.data.index', ['page' => 2]))->assertOk();
        $this->assertPage($second, 'runs', 17, 2);
        $second->assertSee('(17)');
        $this->assertSame(1, $second->viewData('runs')->items()[1]->rows_total);
    }

    public function test_invoice_mail_templates_page_and_sort_on_the_server(): void {
        foreach (range(1, 26) as $i) {
            InvoiceMailTemplate::create([
                'organization_id' => $this->organization->id,
                'name' => sprintf('Vorlage %02d', $i),
                'document_kind' => $i === 26 ? 'quote' : 'invoice',
                'is_default' => $i === 7,
                'subject' => 'Betreff ' . (100 - $i),
                'body_html' => '<p>Text</p>',
                'body_text' => 'Text',
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.invoice-mail-templates.index'))->assertOk();
        $this->assertPage($first, 'templates', 26, 25);
        // Standard: Belegart, darin die Standardvorlage zuerst.
        $this->assertSame('Vorlage 07', $first->viewData('templates')->items()[0]->name);

        $second = $this->get(route('admin.invoice-mail-templates.index', ['page' => 2]))->assertOk();
        $this->assertSame(['Vorlage 26'], array_map(static fn (InvoiceMailTemplate $t): string => $t->name, $second->viewData('templates')->items()));

        $byName = $this->get(route('admin.invoice-mail-templates.index', ['sort' => 'name', 'dir' => 'desc']))->assertOk();
        $this->assertSame('Vorlage 26', $byName->viewData('templates')->items()[0]->name);

        $bySubject = $this->get(route('admin.invoice-mail-templates.index', ['sort' => 'subject', 'dir' => 'asc', 'q' => 'Vorlage']))->assertOk();
        $this->assertSame('Vorlage 26', $bySubject->viewData('templates')->items()[0]->name);
        $this->assertStringContainsString('q=Vorlage', (string) $bySubject->viewData('templates')->nextPageUrl());

        $this->get(route('admin.invoice-mail-templates.index', ['sort' => 'scope']))->assertOk();
    }

    /** Bisher zeigte die Seite still nur die 60 jüngsten Generationen. */
    public function test_backup_generations_page(): void {
        $platformAdmin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $connection = BackupTargetConnection::factory()->active()->create();
        foreach (range(1, 61) as $i) {
            BackupGeneration::factory()->verified()->create(['connection_id' => $connection->id, 'started_at' => now()->subHours($i)]);
        }

        $first = $this->actingAs($platformAdmin)->get(route('admin.backup-targets.index'))->assertOk();
        $this->assertPage($first, 'generations', 61, 25);

        $third = $this->get(route('admin.backup-targets.index', ['page' => 3]))->assertOk();
        $this->assertPage($third, 'generations', 61, 11);
        $third->assertSee($connection->name);
    }

    /** Bisher zeigte die Seite still nur die 50 jüngsten Bestellungen. */
    public function test_b2b_orders_page_while_accesses_stay_complete(): void {
        foreach (range(1, 51) as $i) {
            B2bOrder::query()->create([
                'organization_id' => $this->organization->id,
                'external_order_id' => sprintf('PO-%03d', $i),
                'buyer_key' => 'buyer-' . $i,
                'buyer' => ['name' => 'Besteller ' . $i],
                'currency' => 'EUR',
                'total_net' => '10.00',
                'lines' => [],
                'source' => 'upload',
                'status' => B2bOrderStatus::Open,
                'ordered_at' => now(),
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('b2b-catalog.index'))->assertOk();
        $this->assertPage($first, 'orders', 51, 25);
        $first->assertSee('PO-051')->assertDontSee('PO-001');

        $third = $this->get(route('b2b-catalog.index', ['page' => 3]))->assertOk();
        $this->assertPage($third, 'orders', 51, 1);
        $third->assertSee('PO-001');
    }

    public function test_terminal_badges_page_while_pins_stay_complete(): void {
        foreach (range(1, 26) as $i) {
            $employee = $this->orgUser(['name' => sprintf('Person %02d', $i)]);
            UserBadge::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $employee->id,
                'badge_hash' => UserBadge::hashBadge('AUSWEIS-' . $i),
                'label' => sprintf('Chip %02d', $i),
            ]);
            UserTerminalPin::query()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $employee->id,
                'pin_hash' => 'x',
            ]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.terminals.index'))->assertOk();
        $this->assertPage($first, 'badges', 26, 25);
        $this->assertCount(26, $first->viewData('pins'));
        $first->assertSee('Chip 26')->assertDontSee('Chip 01');

        $second = $this->get(route('admin.terminals.index', ['page' => 2]))->assertOk();
        $this->assertPage($second, 'badges', 26, 1);
        $this->assertCount(26, $second->viewData('pins'));
        $second->assertSee('Chip 01');
    }

    /** Seiten aus genau einer Tabelle füllen die Höhe, auch wenn der Code die Zeilen vorgibt. */
    public function test_fixed_row_tables_fill_the_viewport(): void {
        $main = 'class="wd-surface min-h-0 flex flex-col lg:overflow-clip"';

        $this->actingAs($this->admin)->get(route('admin.custom-fields.index'))->assertOk()
            ->assertSee($main, false)->assertSee(__('fields.custom.intro'))->assertSee(__('entity-types.Customer'));
        $this->get(route('admin.data-ownership.index'))->assertOk()
            ->assertSee($main, false)->assertSee(route('admin.data-ownership.update'));
    }

    private function assertPage(TestResponse $response, string $key, int $total, int $onPage): void {
        $paginator = $response->viewData($key);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame($total, $paginator->total());
        $this->assertCount($onPage, $paginator->items());
    }
}
