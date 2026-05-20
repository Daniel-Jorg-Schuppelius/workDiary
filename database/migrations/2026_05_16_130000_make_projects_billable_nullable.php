<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_16_130000_make_projects_billable_nullable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('billable')->nullable()->default(null)->change();
        });
    }

    public function down(): void {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('billable')->default(true)->nullable(false)->change();
        });
    }
};
