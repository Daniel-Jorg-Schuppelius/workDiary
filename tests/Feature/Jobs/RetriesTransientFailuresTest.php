<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetriesTransientFailuresTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Jobs;

use App\Jobs\Location\ProcessLocationBatch;
use App\Plugins\Calendly\Jobs\CalendlyIngestJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, J7: Wake-/Ingest-Jobs liefen ohne Retry-Konfiguration
 * und ohne failed()-Handler — ein transienter Fehler landete sofort und
 * unbemerkt in failed_jobs.
 */
class RetriesTransientFailuresTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_jobs_carry_retry_budget_and_backoff(): void {
        $job = new ProcessLocationBatch(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertSame([30, 120, 600], $job->backoff());
    }

    public function test_plugin_job_failure_lands_in_the_plugin_error_inbox(): void {
        $this->setUpOrganization();
        $job = new CalendlyIngestJob((int) $this->organization->id, '{}');

        $job->failed(new RuntimeException('Calendly antwortet nicht'));

        $this->assertDatabaseHas('plugin_errors', [
            'plugin_id' => 'calendly',
            'organization_id' => $this->organization->id,
        ]);
    }
}
