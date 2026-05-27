<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_140000_create_cleaning_profiles_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cleaning_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('code', 64);
            $table->string('label', 200);
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->json('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'cleaning_profiles_idx_org_active');
            $table->unique(['organization_id', 'code'], 'cleaning_profiles_uniq_code_per_org');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cleaning_profiles');
    }
};
