<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExtensionModuleGateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Enums\Notification\NotificationEvent;
use App\Models\Approval\Approval;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Models\Investments\InvestmentBudgetRequest;
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Platform\{LicenseFlagOverride, Organization, User};
use App\Plugins\Etsy\Services\EtsyCustomerOrdersPanel;
use App\Services\Approval\ApprovalInbox;
use App\Services\Claims\EarlyWarnings\ClaimPatternWarningSource;
use App\Services\Cockpit\CockpitPanels;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Domain\Cockpit\DomainCockpitPanel;
use App\Services\Domain\DeadlineScans\DomainExpiryScan;
use App\Services\Hr\EarlyWarnings\CapacityWarningSource;
use App\Services\Licensing\ModuleExtensions;
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Rental\DeadlineScans\RentalReturnScan;
use App\Services\Rental\WorkQueue\RentalRequestWorkItemSource;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Services\Reporting\EarlyWarningService;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\WorkQueue\WorkQueue;
use App\Support\MorphMap;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Modul-Gate der Erweiterungspunkte (MVP-1587): Ist ein Fachmodul für eine
 * Organisation aus, liefern seine Beiträge dort nichts — kein Scan-Treffer,
 * keine Frühwarnung, kein Eingangseintrag, kein Panel, keine Vorratsquelle.
 */
class ExtensionModuleGateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function switchOff(string $module, ?Organization $organization = null): void {
        LicenseFlagOverride::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'flag' => $module,
            'disabled_at' => now(),
            'disabled_by_user_id' => $this->admin->id,
        ]);
        $this->app->make(ModuleExtensions::class)->flush();
    }

    public function test_deadline_scan_skips_organizations_with_the_module_off(): void {
        $this->travelTo('2026-10-27 09:00:00');
        $other = Organization::factory()->create();
        $expiring = [];
        foreach ([$this->organization, $other] as $organization) {
            $connection = DomainProviderConnection::factory()->create(['organization_id' => $organization->id]);
            $domain = 'beispiel-' . $organization->id . '.example';
            $expiring[(int) $organization->id] = DomainProjection::factory()->create([
                'organization_id' => $organization->id, 'connection_id' => $connection->id, 'external_domain' => $domain,
                'domain_hash' => DomainProjection::hashFor($domain), 'status' => 'ACTIVE', 'renewal_mode' => 'AUTORENEW', 'synced_at' => now(),
                'expiration_at' => '2026-11-05', 'failure_at' => '2026-12-20',
            ]);
        }
        $this->switchOff('module.domain', $other);

        app(DomainExpiryScan::class)->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30));

        $logged = NotificationDispatchLog::query()->where('event', NotificationEvent::DomainExpiring->value)->pluck('subject_id')->map('intval')->all();
        $this->assertSame([(int) $expiring[(int) $this->organization->id]->id], $logged);
        $this->assertSame(0, NotificationDispatchLog::query()->where('organization_id', $other->id)->count());
    }

    /** Scans, die je Organisation einen Fachdienst rufen (Verleih: Statuswechsel auf „überfällig“), lassen abgeschaltete Organisationen aus. */
    public function test_per_organization_scans_skip_organizations_with_the_module_off(): void {
        $other = Organization::factory()->create();
        User::factory()->create(['organization_id' => $other->id]);
        $this->switchOff('module.rental', $other);

        $scan = app(RentalReturnScan::class);
        $handled = [];
        (new \ReflectionMethod($scan, 'sumPerOrganization'))->invoke(
            $scan,
            User::query()->withoutGlobalScopes()->whereIn('organization_id', [$this->organization->id, $other->id]),
            function (Organization $organization) use (&$handled): int {
                $handled[] = (int) $organization->id;

                return 1;
            },
        );

        $this->assertSame([(int) $this->organization->id], $handled);
    }

    public function test_early_warnings_of_a_switched_off_module_are_not_collected(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->app->bind(ClaimPatternWarningSource::class, fn (): EarlyWarningSource => new class($customer) implements EarlyWarningSource {
            public function __construct(private readonly Customer $subject) {}

            public function key(): string {
                return 'claim-patterns';
            }

            public function warnings(Organization $organization): array {
                return [new EarlyWarning('claim-patterns', $this->subject, 'Muster', 'Detail', 'Empfehlung')];
            }
        });
        $kinds = fn (): array => array_map(static fn (EarlyWarning $w): string => $w->kind, app(EarlyWarningService::class)->collect($this->organization));

        $this->assertContains('claim-patterns', $kinds());

        $this->switchOff('module.claims');

        $this->assertNotContains('claim-patterns', $kinds());
    }

    public function test_cockpit_panel_of_a_switched_off_module_is_not_rendered(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->app->bind(DomainCockpitPanel::class, fn (): CockpitPanel => new class implements CockpitPanel {
            public function order(): int {
                return 1;
            }

            public function supports(Model $subject): bool {
                return true;
            }

            public function render(Model $subject, User $viewer): ?View {
                return view('cockpit._list', ['id' => 'cockpit-gate-probe', 'title' => 'Gate-Probe', 'icon' => 'language', 'count' => 0, 'rows' => []]);
            }
        });
        $html = fn (): string => implode('', array_map('strval', app(CockpitPanels::class)->render($customer, $this->admin)));

        $this->assertStringContainsString('cockpit-gate-probe', $html());

        $this->switchOff('module.domain');

        $this->assertStringNotContainsString('cockpit-gate-probe', $html());
    }

    public function test_approval_inbox_ignores_subjects_of_a_switched_off_module(): void {
        $approval = new Approval(['organization_id' => $this->organization->id, 'approvable_type' => MorphMap::alias(InvestmentBudgetRequest::class), 'approvable_id' => 1, 'step' => 1]);

        $this->assertNotNull(app(ApprovalInbox::class)->subjectFor($approval));

        $this->switchOff('module.investments');
        $this->app->forgetInstance(ApprovalInbox::class);

        $this->assertNull(app(ApprovalInbox::class)->subjectFor($approval));
        $this->assertCount(0, app(ApprovalInbox::class)->openStepsFor($this->admin));
    }

    public function test_work_queue_drops_sources_of_a_switched_off_module(): void {
        $this->assertArrayHasKey('rental_request', app(WorkQueue::class)->sourcesFor($this->admin));

        $this->switchOff('module.rental');

        $this->assertArrayNotHasKey('rental_request', app(WorkQueue::class)->sourcesFor($this->admin));
    }

    public function test_core_and_retention_contributions_stay_and_plugins_follow_their_switch(): void {
        $gate = $this->app->make(ModuleExtensions::class);
        $this->switchOff('module.rental');

        // Kernmodul: immer aktiv, auch ohne Organisation.
        $this->assertTrue($gate->allows(CapacityWarningSource::class, $this->organization));
        $this->assertTrue($gate->allows(CapacityWarningSource::class, null));
        // Fachmodul ohne Organisation: fail-closed.
        $this->assertFalse($gate->allows(RentalRequestWorkItemSource::class, null));
        $this->assertFalse($gate->allows(RentalRequestWorkItemSource::class, $this->organization));
        // Plugin-Beitrag: nur mit eingeschaltetem Plugin.
        $this->assertFalse($gate->allows(EtsyCustomerOrdersPanel::class, $this->organization));
        // Aufbewahrung läuft bewusst am Gate vorbei.
        $this->assertNotSame([], $this->app->make(\App\Modules\ModuleRegistry::class)->extensions(RetentionPolicyProvider::class));
    }
}
