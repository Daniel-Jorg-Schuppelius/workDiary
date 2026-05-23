<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_03_140000_create_assets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('asset_no', 60);
            $table->string('asset_class', 40);
            $table->string('category_code', 60)->nullable();
            $table->string('name', 180);
            $table->string('manufacturer', 180)->nullable();
            $table->string('model', 180)->nullable();
            $table->string('serial_no', 180)->nullable();
            $table->string('inventory_no', 180)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('owned_by', 20);
            $table->string('location_text', 255)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('status', 20);
            $table->string('health', 20)->default('ok');
            $table->date('commissioned_on')->nullable();
            $table->date('decommissioned_on')->nullable();
            $table->date('warranty_until')->nullable();
            $table->date('next_maintenance_on')->nullable();
            $table->date('next_inspection_on')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'asset_no'], 'assets_uniq_asset_no');
            $table->index(['organization_id', 'serial_no'], 'assets_idx_serial');
            $table->index(['customer_id', 'status'], 'assets_idx_customer');
            $table->index(['organization_id', 'status', 'health'], 'assets_idx_status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('assets');
    }
};
