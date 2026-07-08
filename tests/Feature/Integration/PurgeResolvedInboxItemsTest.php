<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurgeResolvedInboxItemsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Integration;

use App\Models\IntegrationInboxItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * MVP-103: Retention-Cleanup der Integrations-Inbox. Abgeschlossene Einträge
 * werden nach der Frist entfernt; offene Einträge bleiben IMMER erhalten.
 */
final class PurgeResolvedInboxItemsTest extends TestCase {
    use RefreshDatabase;

    private int $seq = 0;

    private function item(string $status, ?Carbon $resolvedAt): IntegrationInboxItem {
        $this->seq++;
        $item = new IntegrationInboxItem();
        $item->forceFill([
            'plugin_id' => 'toggl',
            'source' => 'api',
            'target_type' => 'App\\Models\\Customer',
            'external_type' => 'client',
            'external_id' => 'ext-' . $this->seq,
            'dedupe_key' => 'key-' . $this->seq,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => $status,
            'remote_snapshot' => [],
            'resolved_at' => $resolvedAt,
        ])->save();

        return $item;
    }

    public function test_purges_only_old_resolved_items(): void {
        $oldLinked = $this->item(IntegrationInboxItem::STATUS_RESOLVED_LINKED, Carbon::now()->subDays(120));
        $oldDismissed = $this->item(IntegrationInboxItem::STATUS_DISMISSED, Carbon::now()->subDays(100));
        $recentResolved = $this->item(IntegrationInboxItem::STATUS_RESOLVED_CREATED, Carbon::now()->subDays(10));
        $openOld = $this->item(IntegrationInboxItem::STATUS_OPEN, null);

        $exit = Artisan::call('integration:purge-inbox'); // Default 90 Tage
        $this->assertSame(0, $exit);

        $this->assertModelMissing($oldLinked);
        $this->assertModelMissing($oldDismissed);
        $this->assertModelExists($recentResolved);
        $this->assertModelExists($openOld); // offen wird nie gepurgt
    }

    public function test_days_option_overrides_retention(): void {
        $resolved = $this->item(IntegrationInboxItem::STATUS_RESOLVED_LINKED, Carbon::now()->subDays(20));

        // Mit --days=10 fällt der 20 Tage alte Eintrag in die Purge-Fenster.
        Artisan::call('integration:purge-inbox', ['--days' => '10']);
        $this->assertModelMissing($resolved);
    }

    public function test_zero_days_keeps_everything(): void {
        $resolved = $this->item(IntegrationInboxItem::STATUS_RESOLVED_LINKED, Carbon::now()->subDays(500));

        Artisan::call('integration:purge-inbox', ['--days' => '0']);
        $this->assertModelExists($resolved); // Aufbewahrung unbegrenzt
    }
}
