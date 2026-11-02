<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionPerf11Test.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Models\Tenders\{TenderFilterProfile, TenderNotice, TenderNoticeMatch};
use App\Plugins\RemoteSupport\Enums\RemotePendingSessionStatus;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Services\Retention\RetentionRegistry;
use App\Services\Tenders\TenderNoticeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Leistungsanalyse 2026-11-02, perf-11: wachsende Tabellen ohne Aufbewahrung. */
final class RetentionPerf11Test extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_imported_and_dismissed_remote_sessions_become_overdue_after_a_year(): void {
        $this->setUpOrganization();
        $policy = app(RetentionRegistry::class)->policy('remote_pending_sessions');
        $this->assertNotNull($policy, 'Plugin trägt die Regel bei');

        $make = fn (RemotePendingSessionStatus $status, int $daysAgo): RemotePendingSession => RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id, 'provider' => 'anydesk', 'remote_id' => 'r-' . $status->value . $daysAgo,
            'session_id' => 's-' . $status->value . $daysAgo, 'started_at' => now()->subDays($daysAgo)->subHour(), 'ended_at' => now()->subDays($daysAgo),
            'status' => $status,
        ]);
        $old = $make(RemotePendingSessionStatus::Imported, 400);
        $dismissed = $make(RemotePendingSessionStatus::Dismissed, 400);
        $fresh = $make(RemotePendingSessionStatus::Imported, 30);
        $open = $make(RemotePendingSessionStatus::Open, 400);

        $cutoff = now()->subYear();
        $ids = ($policy->overdueQuery)($this->organization, $cutoff)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame(collect([$old->id, $dismissed->id])->sort()->values()->all(), $ids);
        $this->assertNotContains($fresh->id, $ids);
        $this->assertNotContains($open->id, $ids, 'offene Sitzungen bleiben');
    }

    public function test_expired_tender_notices_without_matches_are_pruned(): void {
        $this->setUpOrganization();
        $notice = fn (string $id, ?string $deadline, string $published): TenderNotice => TenderNotice::query()->create([
            'notice_id' => $id, 'version' => 1, 'title' => 'Ausschreibung ' . $id, 'published_on' => $published, 'submission_deadline' => $deadline, 'payload' => [],
        ]);
        $expired = $notice('t-alt', now()->subDays(400)->toDateTimeString(), now()->subDays(420)->toDateString());
        $noDeadline = $notice('t-ohne', null, now()->subDays(400)->toDateString());
        $recent = $notice('t-neu', now()->subDays(10)->toDateTimeString(), now()->subDays(30)->toDateString());
        $matched = $notice('t-treffer', now()->subDays(400)->toDateTimeString(), now()->subDays(420)->toDateString());
        $profile = TenderFilterProfile::query()->create(['organization_id' => $this->organization->id, 'name' => 'Profil', 'active' => true, 'keywords' => ['bau']]);
        TenderNoticeMatch::query()->create(['organization_id' => $this->organization->id, 'tender_notice_id' => $matched->id, 'tender_filter_profile_id' => $profile->id]);

        $pruned = app(TenderNoticeImporter::class)->pruneExpired();

        $this->assertSame(2, $pruned);
        $this->assertDatabaseMissing('tender_notices', ['id' => $expired->id]);
        $this->assertDatabaseMissing('tender_notices', ['id' => $noDeadline->id]);
        $this->assertDatabaseHas('tender_notices', ['id' => $recent->id]);
        $this->assertDatabaseHas('tender_notices', ['id' => $matched->id]);
    }
}
