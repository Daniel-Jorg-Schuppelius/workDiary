<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101200_add_on_call_times_to_shift_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature-103-Delta: Kombi-Dienste (Q1 „Spätdienst mit Rufbereitschaft") —
 * ein Schichttyp kann eine anschließende Rufbereitschaftszeit tragen; die
 * Rollplan-Fortschreibung legt dann zusätzlich einen OnCallShift an.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('shift_types', function (Blueprint $table): void {
            $table->string('on_call_start_time', 5)->nullable();
            $table->string('on_call_end_time', 5)->nullable();
        });
    }

    public function down(): void {
        Schema::table('shift_types', function (Blueprint $table): void {
            $table->dropColumn(['on_call_start_time', 'on_call_end_time']);
        });
    }
};
