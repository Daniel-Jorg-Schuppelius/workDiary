<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100600_create_time_dimension_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-514 P2 (Feature 103): frei definierbare Mandanten-Dimensionen für
 * die Zeitaufteilung — NUR für Dimensionen ohne vorhandenes Modell
 * (bestehende Entitäten werden weiterhin direkt referenziert).
 * `external_id` bereitet die Provider-Synchronisation (ERP) vor.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_dimension_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'tdt_org_code_uq');
        });

        Schema::create('time_dimension_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dimension_type_id')->constrained('time_dimension_types')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('external_id', 120)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dimension_type_id', 'external_id'], 'tdv_type_extid_uq');
            $table->index(['organization_id', 'dimension_type_id'], 'tdv_org_type_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_dimension_values');
        Schema::dropIfExists('time_dimension_types');
    }
};
