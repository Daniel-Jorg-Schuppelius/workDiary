<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_130000_add_diary_entry_to_asset_inspection_schedules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-918: Prüftermin als Stopp einer Prüfertour (Auftrag). */
return new class extends Migration {
    public function up(): void {
        Schema::table('asset_inspection_schedules', function (Blueprint $table): void {
            $table->foreignId('diary_entry_id')->nullable()->after('asset_id')
                ->constrained('diary_entries', indexName: 'ac_schedules_diary_entry_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('asset_inspection_schedules', function (Blueprint $table): void {
            $table->dropForeign('ac_schedules_diary_entry_fk');
            $table->dropColumn('diary_entry_id');
        });
    }
};
