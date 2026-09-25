<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EarlyWarningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Dashboard\Widgets\EarlyWarningsWidget;
use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Models\Asset\{Asset, AssetDefect};
use App\Models\Classification\Classification;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Reporting\ReportTarget;
use App\Models\Time\TimeEntry;
use App\Services\Reporting\EarlyWarningService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-889: Frühwarnungen mit Handlungsempfehlung. */
class EarlyWarningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00'));
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_recurring_asset_defects_warn_and_notify_once(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hebebühne 2']);
        foreach (['2026-06-10', '2026-03-01', '2026-01-10'] as $at) {
            AssetDefect::factory()->create(['organization_id' => $this->organization->id, 'asset_id' => $asset->id, 'reported_by_user_id' => $this->admin->id, 'reported_at' => $at]);
        }

        $warnings = app(EarlyWarningService::class)->collect($this->organization);
        $this->assertSame(['asset_defects'], array_map(static fn ($w): string => $w->kind, $warnings));
        $this->assertSame('Hebebühne 2', $warnings[0]->params['name']);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $notifications = $this->admin->notifications()->get()->filter(static fn ($n): bool => ($n->data['event'] ?? null) === 'report.warning');
        $this->assertCount(1, $notifications);
        $this->assertStringContainsString('Hebebühne 2', (string) $notifications->first()?->data['title']);
    }

    public function test_customer_rework_warns_only_against_a_configured_target(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nacharbeit GmbH']);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'is_default' => false]);
        $reason = Classification::query()->create(['organization_id' => $this->organization->id, 'domain' => 'rework_reason', 'code' => 'fehler', 'label' => 'Fehler', 'active' => true]);
        foreach ([['2026-06-01', null], ['2026-06-02', $reason->id]] as [$date, $rework]) {
            TimeEntry::query()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'user_id' => $this->admin->id, 'date' => $date, 'minutes' => 60, 'billable' => $rework === null, 'internal_rate' => 50, 'rework_reason_classification_id' => $rework]);
        }
        $service = app(EarlyWarningService::class);

        $this->assertSame([], $service->collect($this->organization));

        ReportTarget::query()->create(['organization_id' => $this->organization->id, 'metric' => ReportTargetMetric::ReworkShare->value, 'scope' => ReportTargetScope::Org->value, 'target_value' => 10]);
        $warnings = $service->collect($this->organization);

        $this->assertSame(['customer_rework'], array_map(static fn ($w): string => $w->kind, $warnings));
        $this->assertSame('Nacharbeit GmbH', $warnings[0]->params['name']);
        $this->assertSame(50.0, $warnings[0]->params['actual']);
    }

    public function test_widget_lists_warnings_with_recommendation(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kompressor']);
        foreach (['2026-06-10', '2026-05-01', '2026-04-01'] as $at) {
            AssetDefect::factory()->create(['organization_id' => $this->organization->id, 'asset_id' => $asset->id, 'reported_by_user_id' => $this->admin->id, 'reported_at' => $at]);
        }
        $this->actingAs($this->admin);

        $html = (string) app(EarlyWarningsWidget::class)->render($this->admin);

        $this->assertStringContainsString('Kompressor', $html);
        $this->assertStringContainsString(__('reporting.warning.asset_defects.recommendation'), $html);
    }
}
