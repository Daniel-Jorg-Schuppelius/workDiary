<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportBuilderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Models\{AuditLog, Customer, User};
use App\Services\Support\SupportReportBuilder;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SupportReportBuilderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_bundle_contains_top_level_sections(): void {
        $bundle = app(SupportReportBuilder::class)->build();

        $this->assertArrayHasKey('generated_at', $bundle);
        $this->assertArrayHasKey('installation', $bundle);
        $this->assertArrayHasKey('release', $bundle);
        $this->assertArrayHasKey('health', $bundle);
        $this->assertArrayHasKey('plugin_errors', $bundle);
        $this->assertArrayHasKey('operations', $bundle);
        $this->assertArrayHasKey('diagnostics', $bundle);
        $this->assertArrayHasKey('composer', $bundle);
        $this->assertArrayHasKey('npm', $bundle);
        $this->assertArrayHasKey('migrations', $bundle);
        $this->assertArrayHasKey('config_keys', $bundle);
        $this->assertArrayHasKey('env_keys', $bundle);
        $this->assertArrayHasKey('table_row_counts', $bundle);
        $this->assertArrayHasKey('failed_jobs', $bundle);
        $this->assertArrayHasKey('log_tail', $bundle);
        $this->assertArrayHasKey('audit_event_counts', $bundle);
        $this->assertArrayHasKey('options', $bundle);
    }

    public function test_bundle_contains_versions_via_release_block(): void {
        $bundle = app(SupportReportBuilder::class)->build();

        $this->assertArrayHasKey('runtime', $bundle['release']);
        $this->assertArrayHasKey('php', $bundle['release']['runtime']);
        $this->assertArrayHasKey('laravel', $bundle['release']['runtime']);
        $this->assertArrayHasKey('database_version', $bundle['release']['runtime']);
        $this->assertArrayHasKey('application', $bundle['release']);
        $this->assertArrayHasKey('version', $bundle['release']['application']);
        $this->assertArrayHasKey('build', $bundle['release']['application']);
    }

    public function test_bundle_contains_health_summary_from_system_health(): void {
        $bundle = app(SupportReportBuilder::class)->build();

        $this->assertArrayHasKey('available', $bundle['health']);
        $this->assertTrue($bundle['health']['available']);
        $this->assertArrayHasKey('checks', $bundle['health']);
        $this->assertNotEmpty($bundle['health']['checks']);

        $names = array_column($bundle['health']['checks'], 'name');
        // system:health prüft u. a. Datenbank, Migrationen, Storage, Queue, APP_KEY.
        $this->assertContains('Datenbank', $names);
        $this->assertContains('APP_KEY', $names);
        foreach ($bundle['health']['checks'] as $check) {
            $this->assertArrayHasKey('ok', $check);
            $this->assertIsBool($check['ok']);
        }
    }

    public function test_bundle_contains_record_counts(): void {
        $bundle = app(SupportReportBuilder::class)->build();

        $this->assertIsArray($bundle['table_row_counts']);
        $this->assertArrayHasKey('users', $bundle['table_row_counts']);
        $this->assertIsInt($bundle['table_row_counts']['users']);

        // Operations-Block liefert reine Counts/Metadaten.
        $this->assertArrayHasKey('queue', $bundle['operations']);
        $this->assertArrayHasKey('backup', $bundle['operations']);
    }

    public function test_plugin_error_block_contains_only_counts_no_messages(): void {
        \App\Models\PluginError::query()->create([
            'plugin_id' => 'demo-plugin',
            'organization_id' => null,
            'phase' => 'runtime',
            'exception_class' => 'RuntimeException',
            'message' => 'PII-Leak-Customer-Müller-Secret',
            'occurred_at' => now(),
        ]);

        $bundle = app(SupportReportBuilder::class)->build();
        $serialized = JsonHelper::encode($bundle['plugin_errors']);

        $this->assertSame(1, $bundle['plugin_errors']['total']);
        $this->assertSame('demo-plugin', $bundle['plugin_errors']['by_plugin_phase'][0]['plugin_id']);
        $this->assertSame('runtime', $bundle['plugin_errors']['by_plugin_phase'][0]['phase']);
        $this->assertSame(1, $bundle['plugin_errors']['by_plugin_phase'][0]['count']);
        // Die Fehlermeldung darf NIE im Bericht stehen.
        $this->assertStringNotContainsString('PII-Leak-Customer-Müller-Secret', $serialized);
        $this->assertStringNotContainsString('PII-Leak-Customer-Müller-Secret', JsonHelper::encode($bundle));
    }

    public function test_bundle_never_contains_customer_pii(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $admin->id,
            'name' => 'PII-Customer-Inc-XYZ',
        ]);

        $bundle = app(SupportReportBuilder::class)->build();
        $serialized = JsonHelper::encode($bundle);
        $this->assertStringNotContainsString($customer->name, $serialized);
        $this->assertStringNotContainsString($admin->email, $serialized);
    }

    public function test_bundle_never_contains_app_key_secret(): void {
        // Gezielter Negativtest (Feature 041): der konfigurierte APP_KEY darf
        // nirgends im Bericht auftauchen — weder als Wert noch im Config-/Diag-Block.
        $appKey = (string) config('app.key');
        $this->assertNotSame('', $appKey, 'APP_KEY muss in der Testumgebung gesetzt sein.');

        $bundle = app(SupportReportBuilder::class)->build();
        $serialized = JsonHelper::encode($bundle);

        $this->assertStringNotContainsString($appKey, $serialized);
        // Auch die Base64-Form (ohne base64:-Präfix) darf nicht auftauchen.
        if (str_starts_with($appKey, 'base64:')) {
            $this->assertStringNotContainsString(substr($appKey, 7), $serialized);
        }
    }

    public function test_bundle_never_contains_secret_env_values(): void {
        // Lege gezielt einen sensiblen ENV-Wert vor, der nie im Bundle stehen darf.
        $secret = 'super-secret-token-' . bin2hex(random_bytes(4));
        putenv('LICENSE_KEY=' . $secret);

        $bundle = app(SupportReportBuilder::class)->build();
        $serialized = JsonHelper::encode($bundle);
        $this->assertStringNotContainsString($secret, $serialized);

        // ENV-Schlüsselname darf gelistet werden, aber als <redacted>.
        $envKeys = $bundle['env_keys'];
        if (in_array('LICENSE_KEY', $envKeys, true) || in_array('LICENSE_KEY=<redacted>', $envKeys, true)) {
            $this->assertContains('LICENSE_KEY=<redacted>', $envKeys, 'LICENSE_KEY muss als redacted markiert sein.');
        }
    }

    public function test_bundle_audit_event_counts_include_recent_events(): void {
        AuditLog::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => null,
            'event' => 'support.test',
            'auditable_type' => \App\Models\Organization::class,
            'auditable_id' => $this->organization->id,
            'changes' => [],
        ]);

        $bundle = app(SupportReportBuilder::class)->build();

        $this->assertArrayHasKey('support.test', $bundle['audit_event_counts']);
        $this->assertGreaterThanOrEqual(1, $bundle['audit_event_counts']['support.test']);
    }

    public function test_bundle_migrations_section_has_expected_shape(): void {
        // RefreshDatabase entleert die migrations-Tabelle innerhalb der Test-Transaktion,
        // daher prüfen wir Struktur statt Inhalt: korrekt typisiertes Array, Felder name/batch.
        $bundle = app(SupportReportBuilder::class)->build();

        $this->assertIsArray($bundle['migrations']);
        foreach ($bundle['migrations'] as $entry) {
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('batch', $entry);
            $this->assertIsString($entry['name']);
            $this->assertIsInt($entry['batch']);
        }
    }
}
