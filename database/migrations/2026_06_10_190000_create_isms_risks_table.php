<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_190000_create_isms_risks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISMS-Risikoregister (Feature 044, MVP 1): identifizierte
 * Informationssicherheitsrisiken mit 5x5-Bewertung
 * (Eintrittswahrscheinlichkeit x Auswirkung), Behandlung und Review-Termin.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_risks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Laufende Nummer je Organisation (R-1, R-2, ...) — vergeben im RiskService.
            $table->unsignedInteger('risk_no');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 24);
            // Freier Bezug auf System/Prozess/Standort (Asset-Register folgt in MVP 2+).
            $table->string('asset_ref', 180)->nullable();
            $table->text('threat')->nullable();
            $table->unsignedTinyInteger('likelihood'); // 1-5
            $table->unsignedTinyInteger('impact');     // 1-5
            // Berechnet (likelihood * impact), persistiert für Sortierung/Filter.
            $table->unsignedSmallInteger('score');
            $table->string('treatment', 16);
            $table->string('status', 16)->default('identified');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_due_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'risk_no'], 'isms_risks_org_no_uq');
            $table->index(['organization_id', 'status', 'score'], 'isms_risks_org_status_idx');
            $table->index(['organization_id', 'review_due_on'], 'isms_risks_org_review_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_risks');
    }
};
