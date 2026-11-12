<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_330000_add_requalify_run_id_to_procedure_deviations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1627: Folgeaktion „Erneut durchlaufen“ verweist auf den Wiederholungslauf. */
return new class extends Migration {
    public function up(): void {
        Schema::table('procedure_deviations', function (Blueprint $table): void {
            $table->foreignId('requalify_run_id')->nullable()->after('follow_up_diary_entry_id')->constrained('procedure_runs')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('procedure_deviations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requalify_run_id');
        });
    }
};
