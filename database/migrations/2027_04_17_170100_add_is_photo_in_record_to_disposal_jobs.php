<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_170100_add_is_photo_in_record_to_disposal_jobs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1578: Fotos der Gerätepositionen erscheinen nur auf Wunsch im Kundennachweis. */
return new class extends Migration {
    public function up(): void {
        Schema::table('disposal_jobs', function (Blueprint $table): void {
            $table->boolean('is_photo_in_record')->default(false)->after('notes');
        });
    }

    public function down(): void {
        Schema::table('disposal_jobs', function (Blueprint $table): void {
            $table->dropColumn('is_photo_in_record');
        });
    }
};
