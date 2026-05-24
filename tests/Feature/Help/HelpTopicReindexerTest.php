<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopicReindexerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Models\HelpTopic;
use App\Services\Help\{HelpTopicLoader, HelpTopicReindexer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HelpTopicReindexerTest extends TestCase {
    use RefreshDatabase;

    private string $tmpRoot;

    protected function setUp(): void {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir() . '/help-reindex-' . uniqid('', true);
        File::makeDirectory($this->tmpRoot . '/de', 0755, true);
    }

    protected function tearDown(): void {
        if (File::isDirectory($this->tmpRoot)) {
            File::deleteDirectory($this->tmpRoot);
        }
        parent::tearDown();
    }

    public function test_reindex_upserts_and_is_idempotent(): void {
        File::put($this->tmpRoot . '/de/sample.start.md', "---\ntitle: Start\n---\nBody A");

        $reindexer = new HelpTopicReindexer(new HelpTopicLoader($this->tmpRoot));

        $first = $reindexer->reindex();
        $this->assertSame(['upserted' => 1, 'deleted' => 0], $first);
        $this->assertSame(1, HelpTopic::query()->count());

        // Zweiter Lauf ohne Änderung → kein Wachstum, kein Delete.
        $second = $reindexer->reindex();
        $this->assertSame(['upserted' => 1, 'deleted' => 0], $second);
        $this->assertSame(1, HelpTopic::query()->count());
    }

    public function test_reindex_removes_dropped_topics(): void {
        File::put($this->tmpRoot . '/de/keep.md', "---\ntitle: Keep\n---\nKeep");
        File::put($this->tmpRoot . '/de/drop.md', "---\ntitle: Drop\n---\nDrop");

        $reindexer = new HelpTopicReindexer(new HelpTopicLoader($this->tmpRoot));
        $reindexer->reindex();
        $this->assertSame(2, HelpTopic::query()->count());

        File::delete($this->tmpRoot . '/de/drop.md');
        $result = $reindexer->reindex();

        $this->assertSame(1, $result['upserted']);
        $this->assertSame(1, $result['deleted']);
        $this->assertNull(HelpTopic::query()->where('topic', 'drop')->first());
        $this->assertNotNull(HelpTopic::query()->where('topic', 'keep')->first());
    }
}
