<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_330300_move_diary_entry_notes_to_comments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1629: `diary_entries.notes` hat seit MVP-1619 keinen Schreiber und wurde
 * nirgends angezeigt; nur die Suche las sie. Bestandstexte (etwa aus der
 * Übernahme der Serviceaufträge) werden Kommentar am Auftrag, dann fällt die Spalte.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('diary_entries', 'notes')) {
            return;
        }

        DB::table('diary_entries')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderBy('id')
            ->select(['id', 'organization_id', 'notes', 'created_at', 'updated_at'])
            ->chunkById(500, function ($entries): void {
                $rows = [];
                foreach ($entries as $entry) {
                    $rows[] = [
                        'organization_id' => $entry->organization_id,
                        'commentable_type' => 'diary_entries',
                        'commentable_id' => $entry->id,
                        'user_id' => null,
                        'body' => trim((string) $entry->notes),
                        'created_at' => $entry->updated_at ?? $entry->created_at ?? now(),
                        'updated_at' => $entry->updated_at ?? $entry->created_at ?? now(),
                    ];
                }
                DB::table('comments')->insert($rows);
            });

        Schema::table('diary_entries', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }

    public function down(): void {
        // Kein Rückweg: die Texte sind Kommentare, eine leere Spalte brächte nichts zurück.
    }
};
