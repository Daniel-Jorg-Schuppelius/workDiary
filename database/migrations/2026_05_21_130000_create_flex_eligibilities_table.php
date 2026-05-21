<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_130000_create_flex_eligibilities_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periodische Gleitzeit-Berechtigung pro Mitarbeiter, modelliert analog zu
 * `work_schedules`. Ein User ist an einem Stichtag gleitzeitberechtigt,
 * wenn ein Eintrag mit `valid_from <= Stichtag` und (`valid_to >= Stichtag`
 * oder `valid_to IS NULL`) existiert. Lücken zwischen Perioden bedeuten
 * explizit „nicht berechtigt".
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('flex_eligibilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'valid_from']);
            $table->index(['user_id', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('flex_eligibilities');
    }
};
