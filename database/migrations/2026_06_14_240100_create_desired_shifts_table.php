<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_240100_create_desired_shifts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wunschdienste (Feature 007): Wunsch (`want`) oder Abneigung (`avoid`) für
 * eine konkrete Schicht an einem Datum, optional auf einen Schichttyp bezogen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('desired_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_type_id')->nullable()->constrained('shift_types')->cascadeOnDelete();
            $table->string('preference', 16)->default('want')
                ->comment('want | avoid');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id'], 'desired_shift_org_user_idx');
            $table->index(['user_id', 'date'], 'desired_shift_user_date_idx');
            $table->index(['date', 'shift_type_id'], 'desired_shift_date_type_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('desired_shifts');
    }
};
