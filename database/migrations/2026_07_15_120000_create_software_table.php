<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_15_120000_create_software_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('software', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('name', 200);
            $table->string('vendor', 200)->nullable();
            $table->string('kind', 32);
            $table->string('license_type', 32);
            $table->string('default_version', 64)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'kind'], 'software_idx_org_kind');
            $table->index(['organization_id', 'is_active'], 'software_idx_org_active');
            $table->unique(['organization_id', 'name', 'vendor'], 'software_uniq_name_vendor');
        });
    }

    public function down(): void {
        Schema::dropIfExists('software');
    }
};
