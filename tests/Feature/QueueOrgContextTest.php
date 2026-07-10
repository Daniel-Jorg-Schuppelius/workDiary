<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QueueOrgContextTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Tests\TestCase;

/**
 * Whitebox 2026-07-10 (J1/J2): Jeder Queue-Job startet mit sauberem
 * Mandantenkontext — ein von einem Vorgänger-Job im langlebigen Worker
 * gebundenes `currentOrganization` darf nicht in den nächsten Job
 * verschleppt werden (org-gescopte Queries filterten sonst still auf die
 * falsche Org). Der sync-Driver läuft innerhalb eines Requests und muss
 * den Request-Kontext behalten.
 */
class QueueOrgContextTest extends TestCase {
    use RefreshDatabase;

    public function test_worker_job_start_clears_stale_organization_binding(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);

        event(new JobProcessing('database', $this->fakeJob()));

        $this->assertFalse(app()->bound('currentOrganization'), 'Stale Binding muss vor jedem Worker-Job verworfen werden.');
    }

    public function test_sync_driver_keeps_request_context(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);

        event(new JobProcessing('sync', $this->fakeJob()));

        $this->assertTrue(app()->bound('currentOrganization'), 'Der sync-Driver läuft im Request und darf den Kontext nicht verwerfen.');
        $this->assertSame($org->id, app('currentOrganization')->id);
    }

    private function fakeJob(): Job {
        return $this->createStub(Job::class);
    }
}
