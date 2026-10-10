<?php
/*
 * Created on   : Wed Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Diary\Status as DiaryStatus;
use App\Enums\User\Permission;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class OperationsReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_route_renders(): void {
        $this->getWithRange('reports.operations')->assertOk();
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.operations'))->assertRedirect(route('login'));
    }

    public function test_csv_export_returns_download_with_metadata(): void {
        $response = $this->getWithRange('reports.operations', ['export' => 'csv']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('operations_2030-04-01_2030-04-30.csv', (string) $response->headers->get('Content-Disposition'));
        $body = $response->getContent() ?: '';
        $this->assertStringContainsString('#report:operations', $body);
        $this->assertStringContainsString('Service-Aufträge', $body);
    }

    public function test_backlog_links_only_with_the_drilldown_permission(): void {
        $serviceType = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('slug', EntryType::SLUG_SERVICE)
            ->firstOrFail();
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Acme GmbH']);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'assigned_user_id' => $this->user->id,
            'entry_type_id' => $serviceType->id,
            'customer_id' => $customer->id,
            'status' => DiaryStatus::Planned,
            'scheduled_for' => '2030-04-10',
        ]);

        $series = $this->getWithRange('reports.operations')->assertOk()->viewData('backlogSeries');
        $this->assertCount(1, $series);
        $this->assertArrayNotHasKey('url', $series[0]);

        $this->user->givePermissionTo(Permission::ReportView->value);
        $series = $this->getWithRange('reports.operations')->assertOk()->viewData('backlogSeries');
        $this->assertArrayHasKey('url', $series[0]);
    }

    private function getWithRange(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeMonth(2030, 4))
            ->get(route($routeName, $parameters));
    }
}
