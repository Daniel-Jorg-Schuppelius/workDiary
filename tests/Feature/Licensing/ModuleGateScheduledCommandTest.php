<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleGateScheduledCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Licensing;

use App\Enums\Finance\{AccountType, ProfitDetermination, RecurringInterval, RecurringTemplateKind, RecurringTemplateStatus};
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\Accounting\{AccountingRecurringRun, AccountingRecurringTemplate};
use App\Models\Customer\Customer;
use App\Models\Location\LocationPoint;
use App\Models\Platform\{LicenseFlagOverride, Organization, User};
use App\Models\Project\Project;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Time\TimeEntry;
use App\Notifications\Finance\OpenTimesDigestNotification;
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService, RecurringAccountingService};
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-1524 (Konzept „Modul-Konfiguration“ §5): geplante Befehle eines
 * Fachmoduls wirken nur für Organisationen, in denen das Modul aktiv ist;
 * Lösch- und Aufbewahrungsläufe laufen weiter.
 */
class ModuleGateScheduledCommandTest extends TestCase {
    use RefreshDatabase;

    private function disable(Organization $org, string $flag): void {
        LicenseFlagOverride::query()->create(['organization_id' => $org->id, 'flag' => $flag, 'disabled_at' => now()]);
    }

    /** Lokale Buchhaltung mit einer fälligen Belegerwartung (Vorlage wie AccountingRecurringTest). */
    private function localAccountingWithDueTemplate(Organization $org, CarbonImmutable $startsOn): void {
        OrganizationContext::run($org, function () use ($org, $startsOn): void {
            $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
            app(AccountingProfileService::class)->configure($org, [
                'profit_determination' => ProfitDetermination::DoubleEntry,
                'base_currency' => CurrencyCode::Euro,
                'fiscal_year_start_month' => 1,
                'starts_on' => $startsOn,
                'note' => null,
            ]);
            app(FiscalYearService::class)->create($org, $startsOn);
            app(AccountingProfileService::class)->activateLocal($org, $admin);
            app(ChartOfAccountsService::class)->create($org, ['number' => '6310', 'name' => 'Miete', 'type' => AccountType::Expense]);

            $template = AccountingRecurringTemplate::query()->create([
                'organization_id' => $org->id,
                'kind' => RecurringTemplateKind::DocumentExpectation,
                'name' => 'Telefonrechnung',
                'interval' => RecurringInterval::Monthly,
                'due_day' => 5,
                'starts_on' => $startsOn->toDateString(),
                'status' => RecurringTemplateStatus::Active,
                'version' => 1,
                'expected_amount' => '120.00',
                'currency' => CurrencyCode::Euro,
                'responsible_user_id' => $admin->id,
            ]);
            $template->update(['next_due_on' => app(RecurringAccountingService::class)->firstDue($template)->toDateString()]);
        });
    }

    public function test_recurring_accounting_runs_only_where_the_finance_module_is_active(): void {
        $startsOn = CarbonImmutable::parse('2026-01-01');
        $off = Organization::factory()->create(['name' => 'Musterbau Nord']);
        $on = Organization::factory()->create(['name' => 'Musterbau Süd']);
        $this->localAccountingWithDueTemplate($off, $startsOn);
        $this->localAccountingWithDueTemplate($on, $startsOn);
        $this->disable($off, 'module.finance');

        $this->artisan('accounting:run-recurring', ['--date' => $startsOn->addDays(10)->toDateString()])->assertSuccessful();

        $runs = AccountingRecurringRun::query()->withoutGlobalScopes()->get();
        $this->assertCount(1, $runs);
        $this->assertSame((int) $on->id, (int) $runs->sole()->organization_id);
    }

    /** Stunden, die seit 60 Tagen offen liegen, und ein Admin als Empfänger des Digests. */
    private function staleOpenTimes(Organization $org): User {
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create(['organization_id' => $org->id, 'customer_id' => $customer->id]);
        TimeEntry::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'billable' => true,
            'exported' => false,
            'date' => now()->subDays(60)->toDateString(),
        ]);

        return $admin;
    }

    public function test_the_open_times_digest_notifies_only_organizations_with_the_module(): void {
        Notification::fake();
        $off = Organization::factory()->create();
        $on = Organization::factory()->create();
        $silent = $this->staleOpenTimes($off);
        $notified = $this->staleOpenTimes($on);
        $this->disable($off, 'module.finance');

        $this->artisan('finance:open-times-digest')->assertSuccessful();

        Notification::assertSentTo($notified, OpenTimesDigestNotification::class);
        Notification::assertNotSentTo($silent, OpenTimesDigestNotification::class);
    }

    public function test_an_explicitly_named_organization_without_the_module_is_skipped_with_a_warning(): void {
        Notification::fake();
        $off = Organization::factory()->create(['name' => 'Beispiel GmbH']);
        $this->staleOpenTimes($off);
        $this->disable($off, 'module.finance');

        $this->artisan('finance:open-times-digest', ['--organization' => (string) $off->id])
            ->expectsOutputToContain('Modul nicht aktiv — übersprungen')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_record_scan_skips_records_of_organizations_without_the_module(): void {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $off = Organization::factory()->create();
        $on = Organization::factory()->create();
        $this->disable($off, 'module.helpdesk');
        $overdue = [
            'status' => ServiceTicketStatus::InProgress->value,
            'reaction_due_at' => '2026-06-01 11:00:00',
            'resolution_due_at' => '2026-06-01 11:30:00',
            'acknowledged_at' => '2026-06-01 10:00:00',
            'resolved_at' => null,
        ];
        $skipped = ServiceTicket::factory()->create(['organization_id' => $off->id] + $overdue);
        $flagged = ServiceTicket::factory()->create(['organization_id' => $on->id] + $overdue);

        $this->artisan('tickets:scan-sla-breaches')->assertSuccessful();

        $this->assertFalse((bool) $skipped->refresh()->resolution_breached);
        $this->assertTrue((bool) $flagged->refresh()->resolution_breached);
        Carbon::setTestNow();
    }

    public function test_a_reference_import_without_any_active_organization_fetches_nothing(): void {
        $fake = FakePluginHttp::fake(['https://api.statistiken.bundesbank.de/*' => FakePluginHttp::response('')]);
        $org = Organization::factory()->create();
        $this->disable($org, 'module.contracts');

        $this->artisan('contracts:price-index-sync')
            ->expectsOutputToContain('Kein Abruf')
            ->assertSuccessful();

        $fake->assertNothingSent();
    }

    public function test_deletion_duties_run_while_the_module_is_off(): void {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $this->disable($org, 'module.standorterfassung');
        LocationPoint::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'recorded_at' => '2020-01-01 08:00:00',
            'processed_at' => '2020-01-01 09:00:00',
            'lat' => 52.52,
            'lng' => 13.40,
            'accuracy_m' => 10,
            'source' => LocationPoint::SOURCE_OWNTRACKS,
        ]);

        $this->artisan('location:purge-points', ['--days' => 30])->assertSuccessful();

        $this->assertSame(0, LocationPoint::query()->withoutGlobalScopes()->count());
    }
}
