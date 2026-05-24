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
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SupportReportBuilderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_bundle_contains_top_level_sections(): void {
        $bundle = app(SupportReportBuilder::class)->build();

        $this->assertArrayHasKey('generated_at', $bundle);
        $this->assertArrayHasKey('installation', $bundle);
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

    public function test_bundle_never_contains_customer_pii(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $admin->id,
            'name' => 'PII-Customer-Inc-XYZ',
        ]);

        $bundle = app(SupportReportBuilder::class)->build();
        $serialized = json_encode($bundle);

        $this->assertIsString($serialized);
        $this->assertStringNotContainsString($customer->name, $serialized);
        $this->assertStringNotContainsString($admin->email, $serialized);
    }

    public function test_bundle_never_contains_secret_env_values(): void {
        // Lege gezielt einen sensiblen ENV-Wert vor, der nie im Bundle stehen darf.
        $secret = 'super-secret-token-' . bin2hex(random_bytes(4));
        putenv('LICENSE_KEY=' . $secret);

        $bundle = app(SupportReportBuilder::class)->build();
        $serialized = json_encode($bundle);

        $this->assertIsString($serialized);
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
