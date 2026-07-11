<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_11_100100_create_jtl_warehouse_mappings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JTL-Lager-Projektion + Zuordnung (Feature 078, MVP-319): jede aus JTL
 * gelesene Lagerstätte wird als Projektion gespeichert und von einem Admin
 * explizit einem WorkDiary-Lager zugeordnet. Nicht zugeordnete Lager
 * blockieren den Schreibpfad sichtbar (kein stilles Raten). Kurze,
 * explizite Index-Namen (MySQL-64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('jtl_warehouse_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'jtlwm_org_fk')->cascadeOnDelete();
            $table->string('jtl_warehouse_id', 64);
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('warehouse_type', 64)->nullable();
            $table->boolean('jtl_is_active')->default(true);
            $table->boolean('lock_for_shipment')->default(false);
            $table->boolean('lock_for_availability')->default(false);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses', indexName: 'jtlwm_wh_fk')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'jtl_warehouse_id'], 'jtlwm_org_ext_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('jtl_warehouse_mappings');
    }
};
