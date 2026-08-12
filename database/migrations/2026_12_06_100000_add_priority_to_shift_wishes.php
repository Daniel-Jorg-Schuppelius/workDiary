<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100000_add_priority_to_shift_wishes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-515 (Feature 103): Prioritäten an Verfügbarkeiten und Wunschdiensten —
 * 1 = hoch, 2 = mittel, 3 = niedrig, NULL = keine Angabe. Der neue
 * Freiwunsch-Typ `off` braucht kein DDL (preference ist string(16)).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('availability_windows', function (Blueprint $table): void {
            $table->unsignedTinyInteger('priority')->nullable()->after('kind');
        });
        Schema::table('desired_shifts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('priority')->nullable()->after('preference');
        });
    }

    public function down(): void {
        Schema::table('availability_windows', function (Blueprint $table): void {
            $table->dropColumn('priority');
        });
        Schema::table('desired_shifts', function (Blueprint $table): void {
            $table->dropColumn('priority');
        });
    }
};
