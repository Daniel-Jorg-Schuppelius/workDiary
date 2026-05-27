<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_28_120100_create_meter_readings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('meter_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->decimal('value', 18, 4);
            $table->string('unit', 16);
            $table->decimal('previous_value', 18, 4)->nullable();
            $table->decimal('consumption', 18, 4)->nullable();
            $table->foreignId('read_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_estimated')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'asset_id', 'read_at'], 'meter_readings_asset_read_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('meter_readings');
    }
};
