<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QueueAlertsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Models\User;
use App\Services\Diagnostics\DiagnosticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Cache, DB};
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, J6: failed_jobs und ein toter Worker-Heartbeat waren
 * nur auf der Diagnoseseite sichtbar. operations:scan meldet beides als
 * Betriebsaufgabe und löst sie bei Erholung wieder auf.
 */
class QueueAlertsTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        User::factory()->admin()->create(); // Empfänger + Betreiber-Org
        config(['queue.default' => 'database']);
        Cache::forget(DiagnosticsService::QUEUE_WORKER_HEARTBEAT_KEY);
    }

    public function test_failed_jobs_create_a_task_that_resolves_once_cleared(): void {
        DB::table('failed_jobs')->insert([
            'uuid' => 'f1', 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'boom', 'failed_at' => now(),
        ]);

        Artisan::call('operations:scan');
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'queue_failed_jobs', 'status' => 'open', 'severity' => 'warning']);

        DB::table('failed_jobs')->delete();
        Artisan::call('operations:scan');
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'queue_failed_jobs', 'status' => 'resolved']);
    }

    public function test_silent_worker_creates_a_critical_task_but_a_fresh_heartbeat_does_not(): void {
        Cache::put(DiagnosticsService::QUEUE_WORKER_HEARTBEAT_KEY, CarbonImmutable::now()->subMinutes(30)->toIso8601String());
        Artisan::call('operations:scan');
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'queue_worker_down', 'status' => 'open', 'severity' => 'critical']);

        Cache::put(DiagnosticsService::QUEUE_WORKER_HEARTBEAT_KEY, CarbonImmutable::now()->toIso8601String());
        Artisan::call('operations:scan');
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'queue_worker_down', 'status' => 'resolved']);
    }

    public function test_no_heartbeat_at_all_is_not_an_alarm(): void {
        Artisan::call('operations:scan');

        $this->assertDatabaseMissing('operations_tasks', ['dedupe_key' => 'queue_worker_down']);
    }
}
