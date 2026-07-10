<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpiryScannerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Models\{AttendanceTerminal, ChatWebhook, OperationsTask, TodoistConnection, User};
use App\Services\Operations\Expiry\ExpiryScanner;
use App\Services\Operations\OperationsAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpiryScannerTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function scan(): void {
        app(ExpiryScanner::class)->scan(app(OperationsAlertService::class));
    }

    public function test_expiring_personal_access_token_creates_task_and_rotation_resolves(): void {
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $this->admin->id,
            'name' => 'ci-token',
            'token' => hash('sha256', 'x'),
            'abilities' => '[]',
            'expires_at' => now()->addDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->scan();

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => "credential_expiring:pat:{$tokenId}",
            'type' => 'credential_expiring',
            'status' => 'open',
        ]);

        // Rotation: Ablauf weit in der Zukunft → Auto-Resolve.
        DB::table('personal_access_tokens')->where('id', $tokenId)->update(['expires_at' => now()->addYear()]);
        $this->scan();

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => "credential_expiring:pat:{$tokenId}",
            'status' => 'resolved',
        ]);
    }

    public function test_todoist_connection_error_creates_connection_task(): void {
        $connection = TodoistConnection::query()->create([
            'organization_id' => $this->admin->organization_id,
            'access_token' => 'secret',
            'status' => 'paused',
            'last_error' => 'HTTP 401 beim Sync',
        ]);

        $this->scan();

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => "connection_failing:todoist:{$connection->id}",
            'type' => 'connection_failing',
            'status' => 'open',
        ]);
    }

    public function test_disabled_chat_webhook_and_stale_terminal_are_reported(): void {
        $webhook = ChatWebhook::query()->create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Teams Kanal',
            'kind' => 'teams',
            'webhook_url' => 'https://example.test/hook',
            'active' => false,
            'consecutive_failures' => 5,
            'disabled_at' => now(),
        ]);
        $terminal = AttendanceTerminal::query()->create([
            'organization_id' => $this->admin->organization_id,
            'name' => 'Halle 1',
            'token_hash' => 'abc',
            'active' => true,
            'last_seen_at' => now()->subDays(2),
        ]);

        $this->scan();

        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "connection_failing:chat_webhook:{$webhook->id}"]);
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "connection_failing:terminal:{$terminal->id}"]);

        // Terminal meldet sich wieder → Auto-Resolve nur für das Terminal.
        $terminal->update(['last_seen_at' => now()]);
        $this->scan();

        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "connection_failing:terminal:{$terminal->id}", 'status' => 'resolved']);
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => "connection_failing:chat_webhook:{$webhook->id}", 'status' => 'open']);
    }

    public function test_php_eol_task_appears_with_config_lead(): void {
        $minor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        config(['eol.php.' . $minor => now()->addDays(30)->format('Y-m-d')]);

        $this->scan();

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => "component_eol:php:{$minor}",
            'severity' => 'warning',
        ]);
    }

    public function test_manually_done_tasks_are_not_reopened_by_auto_resolve(): void {
        $connection = TodoistConnection::query()->create([
            'organization_id' => $this->admin->organization_id,
            'access_token' => 'secret',
            'status' => 'paused',
            'last_error' => 'HTTP 401',
        ]);
        $this->scan();

        OperationsTask::query()
            ->where('dedupe_key', "connection_failing:todoist:{$connection->id}")
            ->update(['status' => 'done']);

        // Ursache bleibt bestehen → done-Aufgabe wird recycelt und neu geöffnet
        // (bewusst: neuer Vorfall nach manueller Erledigung ohne Behebung).
        $this->scan();

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => "connection_failing:todoist:{$connection->id}",
            'status' => 'open',
        ]);
    }
}
