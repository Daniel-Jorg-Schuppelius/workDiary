<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchDomainsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Models\Article\Article;
use App\Models\Contract\Contract;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Inventory\Warehouse;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\{Organization, User};
use App\Models\Procurement\PurchaseOrder;
use App\Models\Project\{Project, Task};
use App\Models\Sales\Lead;
use App\Models\Supplier\Supplier;
use App\Services\Search\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Suchbereiche als Erweiterungspunkt (Feature 180, MVP-1364–1367). */
class SearchDomainsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, list<string>>
     */
    private function titles(User $user, string $term, array $filters = []): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->organization_id);
        $this->actingAs($user);
        $groups = app(GlobalSearchService::class)->groups($user, $term, $filters, 10, withActivities: false);

        return collect($groups)->mapWithKeys(fn (array $g) => [$g['key'] => array_column($g['items'], 'title')])->all();
    }

    private function supplier(string $name, ?Organization $organization = null): Supplier {
        return Supplier::factory()->create(['organization_id' => ($organization ?? $this->organization)->id, 'name' => $name, 'company' => $name]);
    }

    private function incomingInvoice(string $number, string $seller): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', $number),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Extracted,
            'invoice_number' => $number, 'seller_name' => $seller, 'currency' => 'EUR', 'amount_gross' => '119.00', 'summary' => [],
        ]);
    }

    public function test_new_domains_find_their_records_by_term(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kunde Nord']);
        $supplier = $this->supplier('Zephyr Großhandel');
        Lead::query()->create([
            'organization_id' => $this->organization->id, 'company' => 'Zephyr Interessent', 'contact_first_name' => 'Max', 'contact_last_name' => 'Muster',
            'source' => LeadSource::Web->value, 'status' => LeadStatus::New->value, 'created_by' => $this->admin->id,
        ]);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'R-ZEPHYR-1']);
        $this->incomingInvoice('ER-ZEPHYR-7', 'Lieferant Süd');
        Contract::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Zephyr Wartung']);
        Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zephyr Ventil']);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'title' => 'Zephyr montieren']);
        PurchaseOrder::query()->create([
            'organization_id' => $this->organization->id, 'number' => 'BE-ZEPHYR', 'supplier_id' => $supplier->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => PurchaseOrderStatus::Draft->value, 'currency' => 'EUR',
        ]);

        $titles = $this->titles($this->admin, 'zephyr');

        $this->assertSame(['Zephyr Großhandel'], $titles['suppliers'] ?? null);
        $this->assertSame(['Zephyr Interessent'], $titles['leads'] ?? null);
        $this->assertSame(['R-ZEPHYR-1'], $titles['invoices'] ?? null);
        $this->assertSame(['ER-ZEPHYR-7'], $titles['incoming_invoices'] ?? null);
        $this->assertSame(['Zephyr Wartung'], $titles['contracts'] ?? null);
        $this->assertSame(['Zephyr Ventil'], $titles['articles'] ?? null);
        $this->assertSame(['Zephyr montieren'], $titles['tasks'] ?? null);
        $this->assertSame(['BE-ZEPHYR'], $titles['purchase_orders'] ?? null);
    }

    public function test_records_of_other_organizations_stay_hidden(): void {
        $foreign = Organization::factory()->create();
        $this->supplier('Zephyr Fremd', $foreign);
        Article::factory()->create(['organization_id' => $foreign->id, 'name' => 'Zephyr Fremdartikel']);

        $this->assertSame([], $this->titles($this->admin, 'zephyr'));
    }

    public function test_domains_follow_the_read_permission(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'R-ZEPHYR-2']);
        $this->supplier('Zephyr Großhandel');
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $titles = $this->titles($worker, 'zephyr');

        $this->assertArrayNotHasKey('invoices', $titles);
        $this->assertArrayNotHasKey('invoices', app(GlobalSearchService::class)->domains($worker));
        $this->assertArrayHasKey('invoices', app(GlobalSearchService::class)->domains($this->admin));
    }

    public function test_customer_filter_narrows_and_unknown_filters_yield_nothing(): void {
        $north = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $south = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $north->id, 'number' => 'R-ZEPHYR-N']);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $south->id, 'number' => 'R-ZEPHYR-S']);
        $this->supplier('Zephyr Großhandel');

        $titles = $this->titles($this->admin, 'zephyr', ['customer' => $north->id]);

        $this->assertSame(['R-ZEPHYR-N'], $titles['invoices'] ?? null);
        // Lieferanten kennen keinen Kunden — gefiltert heißt hier „keine Treffer“.
        $this->assertArrayNotHasKey('suppliers', $titles);
    }

    public function test_person_filter_matches_the_assignee_of_tasks(): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'title' => 'Zephyr mine', 'assigned_to' => $this->admin->id]);
        Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'title' => 'Zephyr theirs', 'assigned_to' => $colleague->id]);
        Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zephyr Ventil']);

        $titles = $this->titles($this->admin, 'zephyr', ['person' => $colleague->id]);

        $this->assertSame(['Zephyr theirs'], $titles['tasks'] ?? null);
        $this->assertArrayNotHasKey('articles', $titles);
    }

    public function test_date_range_uses_the_invoice_date(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'R-ZEPHYR-MAR', 'issued_on' => '2026-03-31']);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'R-ZEPHYR-APR', 'issued_on' => '2026-04-01']);

        $titles = $this->titles($this->admin, 'zephyr', ['from' => '2026-03-01', 'to' => '2026-03-31']);

        $this->assertSame(['R-ZEPHYR-MAR'], $titles['invoices'] ?? null);
    }

    public function test_search_page_focuses_a_new_domain(): void {
        $this->supplier('Zephyr Großhandel');
        Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Zephyr Ventil']);

        $this->actingAs($this->admin)
            ->get(route('search.index', ['q' => 'zephyr', 'domain' => 'suppliers']))
            ->assertOk()
            ->assertSee('Zephyr Großhandel')
            ->assertDontSee('Zephyr Ventil');
    }
}
