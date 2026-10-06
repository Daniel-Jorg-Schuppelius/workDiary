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

use App\Enums\Integration\IntegrationInboxStatus;
use App\Models\Integration\IntegrationInboxItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Artisan, DB};
use Tests\TestCase;

/**
 * MVP-103: Retention-Cleanup der Integrations-Inbox. Abgeschlossene Einträge
 * werden nach der Frist entfernt; offene Einträge bleiben IMMER erhalten.
 */
final class PurgeResolvedInboxItemsTest extends TestCase {
    use RefreshDatabase;

    private int $seq = 0;

    private function item(IntegrationInboxStatus $status, ?Carbon $resolvedAt): IntegrationInboxItem {
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
        $oldLinked = $this->item(IntegrationInboxStatus::ResolvedLinked, Carbon::now()->subDays(120));
        $oldDismissed = $this->item(IntegrationInboxStatus::Dismissed, Carbon::now()->subDays(100));
        $recentResolved = $this->item(IntegrationInboxStatus::ResolvedCreated, Carbon::now()->subDays(10));
        $openOld = $this->item(IntegrationInboxStatus::Open, null);

        $exit = Artisan::call('integration:purge-inbox'); // Default 90 Tage
        $this->assertSame(0, $exit);

        $this->assertModelMissing($oldLinked);
        $this->assertModelMissing($oldDismissed);
        $this->assertModelExists($recentResolved);
        $this->assertModelExists($openOld); // offen wird nie gepurgt
    }

    public function test_days_option_overrides_retention(): void {
        $resolved = $this->item(IntegrationInboxStatus::ResolvedLinked, Carbon::now()->subDays(20));

        // Mit --days=10 fällt der 20 Tage alte Eintrag in die Purge-Fenster.
        Artisan::call('integration:purge-inbox', ['--days' => '10']);
        $this->assertModelMissing($resolved);
    }

    /**
     * Altbestand (Entscheidung 2026-10-05): geschlossene Fälle ohne Erledigt-Datum
     * übersprang der Aufräumlauf für immer. Die Migration datiert sie auf den
     * Tag der Migration — die Frist beginnt damit neu, nichts verschwindet sofort.
     */
    public function test_backfill_starts_the_retention_of_undated_closed_items_with_the_migration(): void {
        $this->freezeSecond();
        $closedLongAgo = Carbon::now()->subDays(120);
        $undated = $this->item(IntegrationInboxStatus::Dismissed, null);
        $recent = $this->item(IntegrationInboxStatus::ResolvedLinked, null);
        $withoutTimestamps = $this->item(IntegrationInboxStatus::Dismissed, null);
        $dated = $this->item(IntegrationInboxStatus::Dismissed, Carbon::now()->subDays(5));
        $open = $this->item(IntegrationInboxStatus::Open, null);
        DB::table('integration_inbox_items')->where('id', $undated->id)->update(['updated_at' => $closedLongAgo]);
        DB::table('integration_inbox_items')->where('id', $withoutTimestamps->id)->update(['updated_at' => null, 'created_at' => null]);
        DB::table('integration_inbox_items')->where('id', $open->id)->update(['updated_at' => $closedLongAgo]);
        $recentChange = $recent->fresh()->updated_at;

        Artisan::call('integration:purge-inbox');
        $this->assertModelExists($undated); // der Fehler: ohne Datum nie aufgeräumt

        $migration = require database_path('migrations/2027_03_10_100200_backfill_resolved_at_on_closed_inbox_items.php');
        $migration->up();
        $migration->up(); // idempotent

        $migrated = Carbon::now();
        $this->assertTrue($migrated->equalTo($undated->fresh()->resolved_at));
        $this->assertTrue($closedLongAgo->equalTo($undated->fresh()->updated_at));
        $this->assertTrue($migrated->equalTo($recent->fresh()->resolved_at));
        $this->assertTrue($recentChange->equalTo($recent->fresh()->updated_at));
        $this->assertTrue($migrated->equalTo($withoutTimestamps->fresh()->resolved_at));
        $this->assertTrue($migrated->copy()->subDays(5)->equalTo($dated->fresh()->resolved_at));
        $this->assertNull($open->fresh()->resolved_at);

        // Direkt nach der Migration verschwindet nichts — auch der seit 120 Tagen verworfene Fall nicht.
        Artisan::call('integration:purge-inbox');
        $this->assertModelExists($undated);

        $this->travel(91)->days();
        Artisan::call('integration:purge-inbox');
        $this->assertModelMissing($undated);
        $this->assertModelMissing($recent);
        $this->assertModelMissing($withoutTimestamps);
        $this->assertModelExists($open);
    }

    public function test_zero_days_keeps_everything(): void {
        $resolved = $this->item(IntegrationInboxStatus::ResolvedLinked, Carbon::now()->subDays(500));

        Artisan::call('integration:purge-inbox', ['--days' => '0']);
        $this->assertModelExists($resolved); // Aufbewahrung unbegrenzt
    }
}
