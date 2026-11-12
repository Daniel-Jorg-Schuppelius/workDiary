<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomFieldCoverageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fields;

use App\Models\Article\Article;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Fields\CustomFieldValue;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Sales\Lead;
use App\Models\Supplier\Supplier;
use App\Services\Article\Search\ArticleSearchDomain;
use App\Services\Asset\Search\AssetSearchDomain;
use App\Services\Customer\Search\CustomerSearchDomain;
use App\Services\Fields\CustomFieldService;
use App\Services\Project\Search\ProjectSearchDomain;
use App\Services\Sales\Search\LeadSearchDomain;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Services\Supplier\Search\SupplierSearchDomain;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1615: eigene Felder in Export, Suche und Liste aller Träger. */
class CustomFieldCoverageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    private function lead(): Lead {
        return Lead::query()->create(['organization_id' => $this->organization->id, 'source' => 'web', 'company' => 'Beispiel Lead GmbH', 'created_by' => $this->admin->id]);
    }

    /** @return list<array<string, string>> */
    private function rows(): array {
        return [
            ['label' => 'Kostenstelle', 'type' => 'text', 'listed' => '1'],
            ['label' => 'Segment', 'type' => 'choice', 'options' => 'Privat, Gewerbe'],
            ['label' => 'Stichtag', 'type' => 'date'],
        ];
    }

    public function test_customer_and_supplier_exports_carry_custom_columns(): void {
        $service = app(CustomFieldService::class);
        foreach ([Customer::class, Supplier::class] as $class) {
            $service->saveDefinition($this->organization, MorphMap::alias($class), $this->rows());
        }
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Exportkunde'])
            ->syncCustomFields(['kostenstelle' => 'KST-K1', 'segment' => 'Gewerbe']);
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Exportlieferant'])
            ->syncCustomFields(['kostenstelle' => 'KST-L1', 'segment' => 'Privat']);

        $customers = $this->get(route('customers.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Kostenstelle', $customers);
        $this->assertStringContainsString('KST-K1', $customers);
        $this->assertStringContainsString('Gewerbe', $customers);

        $suppliers = $this->get(route('suppliers.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Segment', $suppliers);
        $this->assertStringContainsString('KST-L1', $suppliers);
        $this->assertStringContainsString('Privat', $suppliers);
    }

    public function test_supplier_and_lead_lists_show_listed_fields(): void {
        $service = app(CustomFieldService::class);
        $service->saveDefinition($this->organization, MorphMap::alias(Supplier::class), $this->rows());
        $service->saveDefinition($this->organization, MorphMap::alias(Lead::class), $this->rows());
        Supplier::factory()->create(['organization_id' => $this->organization->id])->syncCustomFields(['kostenstelle' => 'KST-LIEF', 'segment' => 'Privat']);
        $this->lead()->syncCustomFields(['kostenstelle' => 'KST-LEAD', 'segment' => 'Privat']);

        $this->get(route('suppliers.index'))->assertOk()->assertSee('Kostenstelle')->assertSee('KST-LIEF');
        $this->get(route('leads.index'))->assertOk()->assertSee('Kostenstelle')->assertSee('KST-LEAD');
    }

    public function test_every_search_domain_finds_records_by_custom_field_text(): void {
        $service = app(CustomFieldService::class);
        /** @var list<array{0: class-string<Model>, 1: class-string<SearchDomain>, 2: \Closure(): Model}> $cases */
        $cases = [
            [Customer::class, CustomerSearchDomain::class, fn (): Model => Customer::factory()->create(['organization_id' => $this->organization->id])],
            [Supplier::class, SupplierSearchDomain::class, fn (): Model => Supplier::factory()->create(['organization_id' => $this->organization->id])],
            [Lead::class, LeadSearchDomain::class, fn (): Model => $this->lead()],
            [Asset::class, AssetSearchDomain::class, fn (): Model => Asset::factory()->create(['organization_id' => $this->organization->id])],
            [Article::class, ArticleSearchDomain::class, fn (): Model => Article::factory()->create(['organization_id' => $this->organization->id])],
            [Project::class, ProjectSearchDomain::class, fn (): Model => Project::factory()->create(['organization_id' => $this->organization->id])],
        ];
        foreach ($cases as [$class, $domainClass, $make]) {
            $service->saveDefinition($this->organization, MorphMap::alias($class), $this->rows());
            $model = $make();
            $model->syncCustomFields(['kostenstelle' => 'Zentrallager Süd', 'segment' => 'Gewerbe', 'stichtag' => '2026-12-24']);

            $domain = app($domainClass);
            $hits = $domain->search($this->admin, (int) $this->organization->id, 'Zentrallager Süd', new SearchDomainFilters, 10);
            $this->assertContains($model->getKey(), array_column($hits, 'id'), $class . ' per Freitext');
            // Gesucht wird die Anzeige (Datum deutsch), nicht der gespeicherte Wert.
            $hits = $domain->search($this->admin, (int) $this->organization->id, '24.12.2026', new SearchDomainFilters, 10);
            $this->assertContains($model->getKey(), array_column($hits, 'id'), $class . ' per Datumsanzeige');
        }
    }

    public function test_search_text_follows_option_label_changes_and_deactivation(): void {
        $service = app(CustomFieldService::class);
        $service->saveDefinition($this->organization, 'customers', $this->rows());
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customer->syncCustomFields(['kostenstelle' => 'KST-1', 'segment' => 'Gewerbe']);
        $this->assertSame("KST-1\nGewerbe", CustomFieldValue::query()->sole()->search_text);

        $definition = $service->definition((int) $this->organization->id, 'customers');
        $this->assertNotNull($definition);
        $service->setActive($definition, false);
        $this->assertNull(CustomFieldValue::query()->sole()->search_text, 'Deaktivierte Felder sind nicht durchsuchbar.');
        $service->setActive($definition, true);
        $this->assertSame("KST-1\nGewerbe", CustomFieldValue::query()->sole()->search_text);
    }
}
