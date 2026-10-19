<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditLogListComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Audit;

use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Verlaufsliste der Akten: generisches „Geändert“ neben einem Fachereignis
 * entfällt, lange Listen bleiben hinter „Weitere Einträge anzeigen“.
 */
class AuditLogListComponentTest extends TestCase {
    private function log(string $event, string $at, int $userId = 7): AuditLog {
        $user = new User(['name' => 'Daniel Test']);
        $log = new AuditLog;
        $log->forceFill(['event' => $event, 'user_id' => $userId, 'created_at' => Carbon::parse($at)]);
        $log->setRelation('user', $user);

        return $log;
    }

    private function render(Collection $logs, ?int $limit = null): string {
        return Blade::render(
            $limit === null ? '<x-audit-log-list :logs="$logs" />' : '<x-audit-log-list :logs="$logs" :limit="$limit" />',
            ['logs' => $logs, 'limit' => $limit],
        );
    }

    public function test_generic_update_next_to_a_specific_event_is_hidden(): void {
        $html = $this->render(collect([
            $this->log('updated', '2026-10-19 17:45:10'),
            $this->log('portal.visibility.updated', '2026-10-19 17:45:09'),
            $this->log('updated', '2026-10-19 17:20:00'),
        ]));

        $this->assertSame(1, substr_count($html, __('audit-events.portal.visibility.updated')));
        // Nur das alleinstehende „Geändert“ von 17:20 bleibt (Anzeige in Org-Zeitzone, daher kein Uhrzeit-Vergleich).
        $this->assertSame(1, substr_count($html, __('audit-events.updated')));
        $this->assertSame(2, substr_count($html, '<li '));
    }

    public function test_generic_update_of_another_actor_stays(): void {
        $html = $this->render(collect([
            $this->log('updated', '2026-10-19 17:45:10', userId: 8),
            $this->log('portal.visibility.updated', '2026-10-19 17:45:09', userId: 7),
        ]));

        $this->assertSame(1, substr_count($html, __('audit-events.updated')));
    }

    public function test_long_lists_fold_behind_a_summary(): void {
        $logs = collect(range(0, 11))->map(fn (int $i): AuditLog => $this->log('supplier.created', '2026-10-01 10:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00'));

        $html = $this->render($logs);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString(__('Weitere Einträge anzeigen (:count)', ['count' => 4]), $html);
        $this->assertSame(12, substr_count($html, '<li '));

        $short = $this->render($logs->take(8));
        $this->assertStringNotContainsString('<details', $short);
        $this->assertStringNotContainsString('Weitere Einträge anzeigen', $short);
    }
}
