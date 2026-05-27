<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_120200_create_floors_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('floors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();

            $table->smallInteger('level');
            $table->string('label', 40);
            $table->decimal('gross_area_m2', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'building_id'], 'floors_idx_org_building');
            $table->unique(['building_id', 'level'], 'floors_uniq_level_per_building');
        });
    }

    public function down(): void {
        Schema::dropIfExists('floors');
    }
};
