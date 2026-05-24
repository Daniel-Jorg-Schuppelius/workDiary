<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_120000_make_comments_polymorphic.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\DiaryEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        Schema::table('comments', function (Blueprint $table): void {
            $table->nullableMorphs('commentable');
        });

        // Backfill: every existing comment was attached to a DiaryEntry.
        DB::table('comments')
            ->whereNotNull('diary_entry_id')
            ->update([
                'commentable_type' => DiaryEntry::class,
                'commentable_id' => DB::raw('diary_entry_id'),
            ]);

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropForeign(['diary_entry_id']);
            $table->dropIndex(['diary_entry_id', 'created_at']);
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('diary_entry_id');
        });
    }

    public function down(): void {
        Schema::table('comments', function (Blueprint $table): void {
            $table->foreignId('diary_entry_id')
                ->nullable()
                ->after('id')
                ->constrained('diary_entries')
                ->cascadeOnDelete();
            $table->index(['diary_entry_id', 'created_at']);
        });

        DB::table('comments')
            ->where('commentable_type', DiaryEntry::class)
            ->update(['diary_entry_id' => DB::raw('commentable_id')]);

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropMorphs('commentable');
        });
    }
};
