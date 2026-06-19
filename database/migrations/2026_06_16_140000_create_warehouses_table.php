<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_140000_create_warehouses_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokale Lagerorte (Feature 048, MVP-067): mindestens ein Lager je Organisation,
 * mit Aktiv-/Sperrstatus und optionalem Bezug zu Standort/Fahrzeug/Team
 * (für den ersten Kern als freie Referenz). Bestände/Bewegungen referenzieren
 * das Lager; Bereiche/Lagerplätze (warehouse_locations) folgen als Ausbau.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('blocked')->default(false);
            $table->string('location_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->unique(['organization_id', 'code'], 'warehouses_org_code_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('warehouses');
    }
};
