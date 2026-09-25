<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_180000_add_blocked_state_to_procedure_runs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-897: Sperrgrund und -beginn eines blockierten Prozedurlaufs. */
return new class extends Migration {
    public function up(): void {
        Schema::table('procedure_runs', function (Blueprint $table): void {
            $table->string('blocked_reason', 40)->nullable()->after('status');
            $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
        });
    }

    public function down(): void {
        Schema::table('procedure_runs', function (Blueprint $table): void {
            $table->dropColumn(['blocked_reason', 'blocked_at']);
        });
    }
};
