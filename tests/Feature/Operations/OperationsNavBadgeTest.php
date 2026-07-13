<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsNavBadgeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\{OperationsTask, User};
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Nav-Badge des Admin-Aufgabencenters (B3/MVP-344): der Zähler aktiver
 * Betriebsaufgaben wird pro Org gecacht (kein Count-Query je Request)
 * und bei jeder Schreiboperation an einer Aufgabe invalidiert.
 */
class OperationsNavBadgeTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function makeTask(string $dedupeKey): OperationsTask {
        $task = app(OperationsAlertService::class)->report(new OperationsSignal(
            type: OperationsTaskType::BackupOverdue,
            dedupeKey: $dedupeKey,
            severity: OperationsTaskSeverity::Warning,
            titleKey: 'operations.task.backup_overdue',
            params: ['hours' => 30, 'threshold' => 26],
            organizationId: (int) $this->admin->organization_id,
        ));
        assert($task !== null);

        return $task;
    }

    private function cacheKey(): string {
        return OperationsTask::navBadgeCacheKey((int) $this->admin->organization_id);
    }

    public function test_badge_count_is_cached_on_first_request(): void {
        $this->makeTask('a');
        $this->makeTask('b');
        Cache::forget($this->cacheKey());

        $this->actingAs($this->admin)->get(route('admin.operations.index'))->assertOk();

        $this->assertTrue(Cache::has($this->cacheKey()));
        $this->assertSame(2, (int) Cache::get($this->cacheKey()));
    }

    public function test_badge_reads_from_cache_instead_of_querying(): void {
        $this->makeTask('a');
        // Abweichender Wert im Cache: erscheint er im HTML, liest der
        // Badge nachweislich aus dem Cache statt frisch zu zählen.
        Cache::put($this->cacheKey(), 42, OperationsTask::NAV_BADGE_TTL);

        $this->actingAs($this->admin)->get(route('admin.operations.index'))
            ->assertOk()
            ->assertSee('>42</span>', false);
    }

    public function test_task_writes_invalidate_the_cached_count(): void {
        $task = $this->makeTask('a');
        Cache::put($this->cacheKey(), 1, OperationsTask::NAV_BADGE_TTL);

        // Neue Aufgabe → Cache weg.
        $this->makeTask('b');
        $this->assertFalse(Cache::has($this->cacheKey()));

        // Statuswechsel (saved) → Cache erneut weg.
        Cache::put($this->cacheKey(), 2, OperationsTask::NAV_BADGE_TTL);
        $this->actingAs($this->admin)
            ->post(route('admin.operations.done', $task))
            ->assertRedirect();
        $this->assertFalse(Cache::has($this->cacheKey()));

        // Nächster Request cached den korrigierten Wert (b aktiv, a erledigt).
        $this->actingAs($this->admin)->get(route('admin.operations.index'))->assertOk();
        $this->assertSame(1, (int) Cache::get($this->cacheKey()));
    }
}
