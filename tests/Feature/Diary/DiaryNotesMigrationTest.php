<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryNotesMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diary;

use App\Models\Communication\Comment;
use App\Models\Diary\DiaryEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1629: `diary_entries.notes` hatte keinen Schreiber und keine Anzeige mehr.
 * Bestandstexte werden Kommentar am Auftrag — sichtbar und weiter durchsuchbar —,
 * dann fällt die Spalte.
 */
final class DiaryNotesMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function migration(): Migration {
        return require database_path('migrations/2027_04_17_330300_move_diary_entry_notes_to_comments.php');
    }

    public function test_legacy_notes_become_comments_and_the_column_goes(): void {
        $this->setUpOrganization();
        $this->assertFalse(Schema::hasColumn('diary_entries', 'notes'));
        $migration = $this->migration();
        // Altstand: die Spalte, wie sie vor MVP-1629 bestand (die Migration hat keinen Rückweg).
        Schema::table('diary_entries', fn (Blueprint $table) => $table->text('notes')->nullable());

        $user = $this->orgUser();
        $legacy = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id]);
        $empty = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id]);
        DB::table('diary_entries')->where('id', $legacy->id)->update(['notes' => "  Schlüssel liegt beim Hausmeister.\n"]);
        DB::table('diary_entries')->where('id', $empty->id)->update(['notes' => '']);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('diary_entries', 'notes'));
        $comment = Comment::query()->withoutGlobalScopes()->where('commentable_id', $legacy->id)->sole();
        $this->assertSame('Schlüssel liegt beim Hausmeister.', $comment->body);
        $this->assertSame($legacy->getMorphClass(), $comment->commentable_type);
        $this->assertSame((int) $this->organization->id, (int) $comment->organization_id);
        $this->assertNull($comment->user_id);
        $this->assertSame(0, Comment::query()->withoutGlobalScopes()->where('commentable_id', $empty->id)->count());
        $this->assertSame(1, $legacy->fresh()->comments()->count());

        // Wiederholt ausgeführt ändert nichts.
        $migration->up();
        $this->assertSame(1, Comment::query()->withoutGlobalScopes()->count());
    }
}
