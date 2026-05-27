<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_120000_create_sites_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('name', 160);
            $table->string('code', 40)->nullable();

            $table->string('address_street', 180)->nullable();
            $table->string('address_zip', 20)->nullable();
            $table->string('address_city', 120)->nullable();
            $table->string('country', 2)->nullable();
            $table->decimal('geo_lat', 10, 7)->nullable();
            $table->decimal('geo_lng', 10, 7)->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'customer_id', 'is_active'], 'sites_idx_org_customer');
            $table->unique(['customer_id', 'code'], 'sites_uniq_code_per_customer');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sites');
    }
};
