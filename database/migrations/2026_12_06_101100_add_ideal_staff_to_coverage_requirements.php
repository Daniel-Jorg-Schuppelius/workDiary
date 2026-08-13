<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101100_add_ideal_staff_to_coverage_requirements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature-103-Delta: Ideal-Besetzung zwischen Minimum und Maximum
 * (Q1-Kennlinien Min/Ideal/Max). Zellen exakt am Minimum werden künftig
 * als „gerade noch ausreichend" (gelb) ausgewiesen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('coverage_requirements', function (Blueprint $table): void {
            $table->unsignedTinyInteger('ideal_staff')->nullable()->after('max_staff');
        });
    }

    public function down(): void {
        Schema::table('coverage_requirements', function (Blueprint $table): void {
            $table->dropColumn('ideal_staff');
        });
    }
};
