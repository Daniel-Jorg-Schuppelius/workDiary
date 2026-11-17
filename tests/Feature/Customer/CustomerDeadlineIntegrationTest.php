<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineIntegrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\Customer\CustomerDeadlineStatus;
use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\Notification\NotificationEvent;
use App\Enums\User\UserRole;
use App\Models\Customer\{Customer, CustomerDeadline, CustomerDeadlineProfile};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Platform\{Organization, User};
use App\Notifications\GenericEventNotification;
use App\Services\Calendar\CalendarEventService;
use App\Services\Customer\Deadlines\{CustomerDeadlineProfileService, CustomerDeadlineRuleSets};
use App\Services\Customer\Deadlines\RuleSets\TaxAdvisoryDeadlineRuleSet;
use App\Services\Customer\DeadlineScans\CustomerDeadlineScan;
use App\Services\Customer\EarlyWarnings\OverdueCustomerDeadlineWarningSource;
use App\Services\Customer\Offboarding\CustomerDeadlineOffboardingHandover;
use App\Services\Delegation\WorkDelegationService;
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Meldewege und Übersicht der Kundenfristen (Feature 197, MVP-1633) samt
 * Vertretung (Anschluss MVP-1648): Übersicht und Export, Dialoge, Cockpit,
 * Arbeitsvorrat, Kalender, gebündelter Scan, Frühwarnung, Übergabe — und
 * Abnahme 7 (fremde Organisationen und Personen ohne Recht sehen nichts).
 */
final class CustomerDeadlineIntegrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $anna;

    private User $ben;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->setUpOrganization(['settings' => ['holidays' => ['provider' => 'Germany\\Berlin']]]);
        $this->admin = $this->orgAdmin();
        $this->anna = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Anna Beispiel']);
        $this->ben = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Ben Muster']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bäckerei Probe']);
        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [TaxAdvisoryDeadlineRuleSet::KEY]);
        $ruleSet = app(CustomerDeadlineRuleSets::class)->get($this->organization, TaxAdvisoryDeadlineRuleSet::KEY);
        $this->assertNotNull($ruleSet);
        app(CustomerDeadlineProfileService::class)->save($this->customer, $ruleSet, ['vat_interval' => 'monthly', 'wage_tax_interval' => 'monthly', 'tax_office_state' => 'Germany\\Berlin'], CarbonImmutable::parse('2026-03-02'), $this->anna, $this->admin);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function deadline(string $kind, string $period): CustomerDeadline {
        return CustomerDeadline::query()->where('customer_id', $this->customer->id)->where('kind', $kind)->where('period_key', $period)->sole();
    }

    /** @return list<WorkItem> */
    private function queue(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $queue = app(WorkQueue::class);
        $queue->forget($viewer, $this->organization);

        return $queue->itemsFor($viewer, $this->organization);
    }

    public function test_the_overview_lists_filters_and_exports(): void {
        $this->actingAs($this->anna)
            ->get(route('customer-deadlines.index'))
            ->assertOk()
            ->assertSee(__('customer_deadline.title'))
            ->assertSee('Bäckerei Probe')
            ->assertSee(__('customer_deadline.kind.wage_tax'));

        $this->actingAs($this->anna)
            ->get(route('customer-deadlines.index', ['responsible' => 'mine', 'kind' => 'wage_tax', 'month' => '2026-03']))
            ->assertOk()
            ->assertSee('10.03.2026');

        $csv = $this->actingAs($this->anna)->get(route('customer-deadlines.index', ['export' => 'csv', 'status' => 'all']));
        $csv->assertOk();
        $this->assertStringContainsString('Bäckerei Probe', (string) $csv->getContent());
        $this->actingAs($this->anna)->get(route('customer-deadlines.index', ['export' => 'xlsx']))->assertOk();
    }

    /** Abnahme 7: ohne Recht und aus fremder Organisation keine Fristen. */
    public function test_people_without_permission_and_other_organisations_see_nothing(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $deadline = $this->deadline('vat_advance', '2026-03');

        $this->actingAs($worker)->get(route('customer-deadlines.index'))->assertForbidden();
        $this->actingAs($worker)->get(route('customer-deadlines.edit', $deadline) . '?dialog=1')->assertForbidden();
        $this->assertSame([], array_values(array_filter($this->queue($worker), static fn (WorkItem $i): bool => $i->source === 'customer_deadline')));

        $foreign = Organization::factory()->create();
        $stranger = $this->userWithRole(UserRole::Teamleitung->value, $foreign);
        app()->instance('currentOrganization', $foreign);
        $this->actingAs($stranger)->get(route('customer-deadlines.edit', $deadline) . '?dialog=1')->assertNotFound();
        $this->actingAs($stranger)->get(route('customer-deadlines.index'))->assertOk()->assertDontSee('Bäckerei Probe');
    }

    public function test_the_dialog_steps_through_complete_extend_and_reopen(): void {
        $deadline = $this->deadline('vat_advance', '2026-02');
        $this->actingAs($this->anna)->get(route('customer-deadlines.edit', $deadline) . '?dialog=1')->assertOk()->assertSee(__('customer_deadline.dialog.history'));

        $this->actingAs($this->anna)->post(route('customer-deadlines.update', $deadline), ['action' => 'extend', 'due_on' => '2026-03-31'])->assertSessionHasErrors('text');
        $this->actingAs($this->anna)->post(route('customer-deadlines.update', $deadline), ['action' => 'extend', 'due_on' => '2026-03-31', 'text' => 'Antrag bewilligt'])->assertSessionHasNoErrors();
        $this->assertSame(CustomerDeadlineStatus::Extended, $deadline->refresh()->status);

        $this->actingAs($this->anna)->post(route('customer-deadlines.update', $deadline), ['action' => 'complete'])->assertSessionHasNoErrors();
        $this->assertSame(CustomerDeadlineStatus::Completed, $deadline->refresh()->status);
        $this->assertSame((int) $this->anna->id, (int) $deadline->completed_by);
    }

    /** Die zuständige Person bearbeitet ihre Fristen mit dem Leserecht; andere brauchen das Pflegerecht. */
    public function test_the_responsible_person_may_act_with_the_view_permission_only(): void {
        $viewer = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Carla Klein']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $viewer->givePermissionTo('customer.deadline.view');
        $deadline = $this->deadline('vat_advance', '2026-02');

        $this->actingAs($viewer)->post(route('customer-deadlines.update', $deadline), ['action' => 'complete'])->assertForbidden();
        $deadline->update(['responsible_user_id' => $viewer->id]);
        $this->actingAs($viewer)->post(route('customer-deadlines.update', $deadline), ['action' => 'complete'])->assertSessionHasNoErrors();
        $this->assertSame(CustomerDeadlineStatus::Completed, $deadline->refresh()->status);
        $this->actingAs($viewer)->get(route('customers.deadline-profile.edit', $this->customer) . '?dialog=1')->assertForbidden();
    }

    public function test_profile_and_service_dialogs_save_versions_and_services(): void {
        $this->actingAs($this->anna)->get(route('customers.deadline-profile.edit', $this->customer) . '?dialog=1')
            ->assertOk()
            ->assertSee(__('customer_deadline.trait.vat_extension'))
            ->assertSee(__('customer_deadline.profile.like_last_year'));

        $this->actingAs($this->anna)->post(route('customers.deadline-profile.store', $this->customer), [
            'rule_set' => TaxAdvisoryDeadlineRuleSet::KEY,
            'valid_from' => '2026-04-01',
            'responsible_user_id' => $this->ben->sqid,
            'traits' => ['vat_interval' => 'quarterly', 'wage_tax_interval' => 'nonsense'],
        ])->assertSessionHasErrors('traits.wage_tax_interval');

        $this->actingAs($this->anna)->post(route('customers.deadline-profile.store', $this->customer), [
            'rule_set' => TaxAdvisoryDeadlineRuleSet::KEY,
            'valid_from' => '2026-04-01',
            'responsible_user_id' => $this->ben->sqid,
            'traits' => ['vat_interval' => 'quarterly', 'vat_extension' => '1', 'wage_tax_interval' => 'monthly', 'tax_office_state' => 'Germany\\Berlin'],
        ])->assertSessionHasNoErrors();

        $versions = CustomerDeadlineProfile::query()->where('customer_id', $this->customer->id)->orderBy('valid_from')->get();
        $this->assertCount(2, $versions);
        $old = $versions->first();
        $new = $versions->last();
        $this->assertNotNull($old);
        $this->assertNotNull($new);
        $this->assertSame('2026-03-31', $old->valid_until?->toDateString());
        $this->assertSame((int) $this->ben->id, (int) $new->responsible_user_id);
        $this->assertTrue($new->traitValues()->get('vat_extension'));
        // Ab April vierteljährlich: Q2 entsteht, der offene Monat April entfällt.
        $this->assertSame('2026-08-10', $this->deadline('vat_advance', '2026-Q2')->due_on->toDateString());
        $this->assertSame('2026-04-10', $this->deadline('vat_advance', '2026-03')->due_on->toDateString(), 'März gehört zur alten Fassung');
        $this->assertNull(CustomerDeadline::query()->where('customer_id', $this->customer->id)->where('kind', 'vat_advance')->where('period_key', '2026-04')->first());

        $this->actingAs($this->anna)->get(route('customers.services.index', $this->customer) . '?dialog=1')->assertOk();
        $type = \App\Models\Classification\EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'lohn', 'label' => 'Lohn']);
        $this->actingAs($this->anna)->post(route('customers.services.store', $this->customer), [
            'entry_type_id' => $type->sqid, 'period_kind' => 'month', 'is_mandatory' => '1', 'valid_from' => '2026-01-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-03-25', $this->deadline('contribution_statement', '2026-03')->due_on->toDateString());

        $service = \App\Models\Customer\CustomerService::query()->where('customer_id', $this->customer->id)->sole();
        $this->actingAs($this->anna)->post(route('customers.services.end', [$this->customer, $service]), ['end_on' => '2026-06-30'])->assertSessionHasNoErrors();
        $this->assertSame('2026-06-30', $service->refresh()->valid_until?->toDateString());
        $this->assertNull(CustomerDeadline::query()->where('customer_id', $this->customer->id)->where('kind', 'contribution_due')->where('period_key', '2026-07')->first());
    }

    public function test_settings_switch_rule_sets_and_warning_days(): void {
        $this->actingAs($this->anna)->get(route('customer-deadlines.settings.edit') . '?dialog=1')->assertOk()->assertSee(__('customer_deadline.rule_set.home_care'));
        $this->actingAs($this->anna)->post(route('customer-deadlines.settings.update'), [
            'rule_sets' => [TaxAdvisoryDeadlineRuleSet::KEY, 'home_care'],
            'warn_days' => 10,
            'warn_days_by_rule_set' => ['tax_advisory' => 3, 'home_care' => ''],
        ])->assertSessionHasNoErrors();

        $ruleSets = app(CustomerDeadlineRuleSets::class);
        $organization = $this->organization->refresh();
        $this->assertSame(['home_care', 'tax_advisory'], $ruleSets->enabledKeys($organization));
        $this->assertSame(3, $ruleSets->warnDays($organization, 'tax_advisory'));
        $this->assertSame(10, $ruleSets->warnDays($organization, 'home_care'));
    }

    public function test_the_customer_cockpit_shows_profile_and_next_deadlines(): void {
        $this->actingAs($this->anna)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee('id="customer-deadlines"', false)
            ->assertSee(__('customer_deadline.kind.vat_advance'))
            ->assertSee(__('customer_deadline.trait.tax_office_state'));
    }

    /** Arbeitsvorrat „bis Wochenende und überfällig“ der zuständigen Person; die Vertretung sieht ihn gekennzeichnet. */
    public function test_the_work_queue_shows_due_deadlines_also_to_the_deputy(): void {
        // 02.03.2026 (Montag): Februar fällig 10.03. — nicht bis Sonntag 08.03.; Lohnsteuer Februar ebenso.
        $this->deadline('vat_advance', '2026-02')->update(['due_on' => '2026-03-06']);
        $titles = array_map(static fn (WorkItem $i): string => $i->title, array_filter($this->queue($this->anna), static fn (WorkItem $i): bool => $i->source === 'customer_deadline'));
        $this->assertCount(1, $titles);
        $this->assertSame([], array_values(array_filter($this->queue($this->ben), static fn (WorkItem $i): bool => $i->source === 'customer_deadline')));

        app(WorkDelegationService::class)->create($this->anna, $this->ben, CarbonImmutable::parse('2026-03-02'), CarbonImmutable::parse('2026-03-20'), WorkDelegationKind::Vacation, null, null, $this->anna);
        $delegated = array_values(array_filter($this->queue($this->ben), static fn (WorkItem $i): bool => $i->source === 'customer_deadline'));
        $this->assertCount(1, $delegated);
        $this->assertSame('Anna Beispiel', $delegated[0]->onBehalfOf);
    }

    public function test_the_calendar_shows_own_deadlines(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $events = app(CalendarEventService::class)->events(CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'), $this->anna);
        $ids = array_column($events, 'id');
        $this->assertContains('customer-deadline-' . $this->deadline('vat_advance', '2026-02')->id, $ids);
        $this->assertSame([], array_values(array_filter(array_column(app(CalendarEventService::class)->events(CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'), $this->ben), 'id'), static fn (string $id): bool => str_starts_with($id, 'customer-deadline-'))));
    }

    /** Ein gebündelter Hinweis je Person und Stufe, nur einmal, auch an die Vertretung. */
    public function test_the_scan_bundles_per_person_once_and_forwards_to_the_deputy(): void {
        Notification::fake();
        app(WorkDelegationService::class)->create($this->anna, $this->ben, CarbonImmutable::parse('2026-03-02'), null, WorkDelegationKind::Vacation, null, null, $this->anna);
        $scan = app(CustomerDeadlineScan::class);
        $options = new DeadlineScanOptions(7, 30);

        // USt Februar und Lohnsteuer Februar (sonst 10.03., außerhalb der Vorwarnzeit von 7 Tagen) rücken vor.
        $this->deadline('vat_advance', '2026-02')->update(['due_on' => '2026-03-05']);
        $this->deadline('wage_tax', '2026-02')->update(['due_on' => '2026-03-06']);
        $sent = $scan->run(app(NotificationDispatcher::class), $options);

        $this->assertGreaterThan(0, $sent);
        $this->assertSame(1, NotificationDispatchLog::query()->where('event', NotificationEvent::CustomerDeadlineDueSoon->value)->count());
        Notification::assertSentTo($this->anna, GenericEventNotification::class);
        Notification::assertSentTo($this->ben, GenericEventNotification::class, static fn (GenericEventNotification $n): bool => ($n->payload['on_behalf_of'] ?? null) === 'Anna Beispiel');
        $this->assertNotNull($this->deadline('wage_tax', '2026-02')->warned_at);

        $this->assertSame(0, $scan->run(app(NotificationDispatcher::class), $options), 'nur einmal je Frist');
    }

    public function test_overdue_deadlines_raise_an_early_warning(): void {
        $this->assertSame([], app(OverdueCustomerDeadlineWarningSource::class)->warnings($this->organization));
        Carbon::setTestNow('2026-03-20 09:00:00');
        $warnings = app(OverdueCustomerDeadlineWarningSource::class)->warnings($this->organization);
        $this->assertCount(1, $warnings);
        $this->assertSame('Bäckerei Probe', $warnings[0]->params['name']);
        $this->assertSame(2, $warnings[0]->params['count']);
    }

    /** Endgültige Übergabe: laufende Fassungen und offene Fristen gehen an die Nachfolge, Erledigtes bleibt. */
    public function test_the_handover_moves_responsibility(): void {
        $done = $this->deadline('vat_advance', '2026-02');
        $done->update(['status' => CustomerDeadlineStatus::Completed]);
        $handover = app(CustomerDeadlineOffboardingHandover::class);

        $this->assertSame(1, $handover->count($this->anna));
        $this->assertSame(1, $handover->handOver($this->anna, $this->ben, $this->admin));
        $this->assertSame(0, $handover->count($this->anna));
        $this->assertSame((int) $this->ben->id, (int) $this->deadline('vat_advance', '2026-03')->responsible_user_id);
        $this->assertSame((int) $this->ben->id, (int) CustomerDeadlineProfile::query()->where('customer_id', $this->customer->id)->sole()->responsible_user_id);
        $this->assertSame((int) $this->anna->id, (int) $done->refresh()->responsible_user_id);
    }
}
