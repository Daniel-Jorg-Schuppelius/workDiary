<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportReportCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Models\{Customer, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SupportReportCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_command_writes_report_to_output_file(): void {
        $path = storage_path('app/testing/support-report-cli-' . bin2hex(random_bytes(3)) . '.json');
        @mkdir(dirname($path), 0777, true);

        $exit = Artisan::call('support:report', ['--output' => $path]);

        $this->assertSame(0, $exit);
        $this->assertFileExists($path);

        $payload = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('release', $payload);
        $this->assertArrayHasKey('health', $payload);
        $this->assertArrayHasKey('table_row_counts', $payload);

        @unlink($path);
    }

    public function test_command_output_excludes_customer_and_secrets(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $admin->id,
            'name' => 'CLI-Negativ-Kunde-QWERTY',
        ]);

        Artisan::call('support:report');
        $output = Artisan::output();

        $this->assertStringNotContainsString($customer->name, $output);
        $this->assertStringNotContainsString((string) config('app.key'), $output);
        $this->assertStringNotContainsString($admin->email, $output);
    }
}
