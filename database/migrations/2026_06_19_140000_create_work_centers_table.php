<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_140000_create_work_centers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbeitsplätze / Maschinen für die Kapazitätsplanung (Feature 047/048, E7):
 * Tageskapazität in Minuten und Rüstzeit je Auftrag.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('work_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->unsignedInteger('capacity_minutes')->default(480); // Tageskapazität
            $table->unsignedInteger('setup_minutes')->default(0);      // Rüstzeit je Auftrag
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'work_centers_org_code_uq');
            $table->index('organization_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('work_centers');
    }
};
