<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeWakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\User;
use App\Services\CloudIntake\{CloudIntakeRunner, IntakeWakeSignal};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Aufweck-Spur des Cloud-Dokumenteingangs (Feature 080).
 *
 * Regression zum Befund vom 2026-08-21: Die Provider-Webhooks setzten ein
 * Aufweck-Flag, das NIEMAND gelesen hat — `consume()` kam nur in Tests vor.
 * Diese Tests halten fest, dass das Flag jetzt eine Wirkung hat und dass es
 * genau einmal gilt.
 */
final class CloudIntakeWakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CloudDocumentConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app()->instance('currentOrganization', $this->organization);

        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->connection = CloudDocumentConnection::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $creator->id,
        ]);
    }

    /** Zählt die Läufe, ohne einen echten Anbieter zu berühren. */
    private function countingRunner(): CloudIntakeRunner {
        $runner = new class extends CloudIntakeRunner {
            /** @var list<int> */
            public array $ran = [];

            public function __construct() {}

            public function run(CloudDocumentConnection $connection, ?\App\Plugins\Contracts\DocumentIntakeSource $adapter = null): array {
                $this->ran[] = (int) $connection->id;

                return ['status' => 'ok', 'pages' => 0, 'imported' => 0, 'inbox' => 0, 'rejected' => 0, 'duplicates' => 0, 'skipped' => 0, 'tombstones' => 0];
            }
        };
        app()->instance(CloudIntakeRunner::class, $runner);

        return $runner;
    }

    public function test_without_a_signal_nothing_runs(): void {
        $runner = $this->countingRunner();

        $this->artisan('cloud-intake:wake')->assertExitCode(0);

        $this->assertSame([], $runner->ran);
    }

    public function test_a_signalled_connection_runs_once(): void {
        $runner = $this->countingRunner();
        app(IntakeWakeSignal::class)->signal((int) $this->connection->id);

        $this->artisan('cloud-intake:wake')->assertExitCode(0);

        $this->assertSame([(int) $this->connection->id], $runner->ran);

        // Das Flag gilt einmal — sonst liefe dieselbe Verbindung im Minutentakt.
        $this->artisan('cloud-intake:wake')->assertExitCode(0);
        $this->assertCount(1, $runner->ran);
    }

    public function test_only_the_signalled_connection_runs(): void {
        $other = CloudDocumentConnection::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->connection->created_by_user_id,
        ]);
        $runner = $this->countingRunner();

        app(IntakeWakeSignal::class)->signal((int) $other->id);
        $this->artisan('cloud-intake:wake')->assertExitCode(0);

        $this->assertSame([(int) $other->id], $runner->ran);
    }
}
