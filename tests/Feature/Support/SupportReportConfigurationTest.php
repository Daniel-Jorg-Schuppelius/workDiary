<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportConfigurationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Models\User;
use App\Services\Support\SupportReportBuilder;
use App\Settings\{SettingScope, SettingsRegistry};
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportReportConfigurationTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        User::factory()->admin()->create();
    }

    public function test_report_contains_configuration_with_sources_and_redacted_secrets(): void {
        // Sensibles Registry-Setting + System-Override anlegen.
        $definitions = (array) config('settings-registry');
        $definitions['testsecret.api_key'] = ['type' => 'string', 'scopes' => ['system'], 'sensitive' => true];
        config(['settings-registry' => $definitions]);
        $this->app->forgetInstance(SettingsRegistry::class);

        Setting::set('testsecret.api_key', 'streng-geheim-42', SettingScope::System);
        Setting::set('pagination.customers', 60, SettingScope::System);

        $report = app(SupportReportBuilder::class)->build();

        $this->assertIsArray($report['configuration']);
        $configuration = collect((array) $report['configuration']);
        $secret = $configuration->firstWhere('key', 'testsecret.api_key');
        $this->assertSame('<redacted>', $secret['value']);
        $this->assertSame('system', $secret['source']);

        $pagination = $configuration->firstWhere('key', 'pagination.customers');
        $this->assertSame(60, $pagination['value']);
        $this->assertSame('system', $pagination['source']);

        $this->assertStringNotContainsString('streng-geheim-42', (string) json_encode($report));
    }

    public function test_report_contains_scheduler_and_updates_snapshot(): void {
        app(\App\Scheduling\SchedulerOverrideService::class)->pause('toggl.import');

        $report = app(SupportReportBuilder::class)->build();

        $this->assertIsArray($report['scheduler']);
        $scheduler = collect((array) $report['scheduler']);
        $toggl = $scheduler->firstWhere('job', 'toggl.import');
        $this->assertFalse($toggl['enabled']);
        $this->assertSame('0 * * * *', $toggl['cron']);

        $this->assertSame('manual', $report['updates']['mode']);
        $this->assertIsArray($report['updates']['pending']);
    }
}
