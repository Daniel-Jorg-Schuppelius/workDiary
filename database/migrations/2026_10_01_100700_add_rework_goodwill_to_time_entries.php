<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100700_add_rework_goodwill_to_time_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachkalkulation (Feature 014, Rang 59a): Nacharbeit-/Kulanz-Kennzeichnung
 * je Zeiteintrag über die bestehenden Klassifikations-Domänen
 * rework_reason/goodwill_reason — die Domänen existierten, hatten aber
 * KEINE Persistenz an TimeEntry.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('rework_reason_classification_id')
                ->nullable()->after('activity_category_id')
                ->constrained('classifications', indexName: 'te_rework_fk')->nullOnDelete();
            $table->foreignId('goodwill_reason_classification_id')
                ->nullable()->after('rework_reason_classification_id')
                ->constrained('classifications', indexName: 'te_goodwill_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rework_reason_classification_id');
            $table->dropConstrainedForeignId('goodwill_reason_classification_id');
        });
    }
};
