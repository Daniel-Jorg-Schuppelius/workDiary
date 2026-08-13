<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101500_create_saved_report_views_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-529 (Q1 „Auswertungs-Modelle"): benannte, teilbare Report-Ansichten —
 * gespeicherte Kombination aus Report-Route und Filter-Parametern. Ergänzt
 * die persönlichen Filter-Presets um org-weit geteilte, benannte Einstiege
 * („eine Zahlenbasis für alle").
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('saved_report_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users', indexName: 'srv_created_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('route_name', 128);
            $table->json('params')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'is_shared'], 'srv_org_shared_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('saved_report_views');
    }
};
