<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102100_add_follow_up_diary_entry_id_to_open_issues.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folgeauftrag aus offenem Punkt (Feature 139, MVP-704; Vollscan 2026-08-23,
 * G4): Rückverknüpfung auf den manuell angelegten Tagebuch-Eintrag —
 * Gegenstück zu procedure_deviations.follow_up_diary_entry_id.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('open_issues', function (Blueprint $table): void {
            $table->foreignId('follow_up_diary_entry_id')->nullable()->after('closed_reason')
                ->constrained('diary_entries', indexName: 'open_issues_follow_up_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('open_issues', function (Blueprint $table): void {
            $table->dropForeign('open_issues_follow_up_fk');
            $table->dropColumn('follow_up_diary_entry_id');
        });
    }
};
