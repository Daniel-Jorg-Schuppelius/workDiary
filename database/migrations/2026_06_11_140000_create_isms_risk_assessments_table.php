<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_140000_create_isms_risk_assessments_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Risiko-Bewertungshistorie (Feature 046, Inkrement D): freigegebene
 * historische Bewertungsstände statt Überschreiben alter Stände.
 * Je Risiko laufende Nummer (assessment_no) und Art der Bewertung:
 * gross = Bruttorisiko (ohne Maßnahmen), net = Nettorisiko (mit
 * bestehenden Maßnahmen), target = Zielrisiko (angestrebt).
 * score = likelihood * impact (berechnet im RiskService, persistiert).
 * Die Freigabe (draft → approved) setzt approved_by_user_id +
 * approved_at (046-Prinzip „Freigabe mit Person/Zeitpunkt/Gegenstand");
 * freigegebene Bewertungen sind UNVERÄNDERLICH (Model-Guard +
 * RiskService). valid_until trägt das Ablauf-/Reviewdatum akzeptierter
 * Restrisiken (Fristen-Scanner: isms.riskReviewDue).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_risk_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_risk_id')->constrained('isms_risks')->cascadeOnDelete();
            // Laufende Nummer je Risiko (B-1, B-2, ...) — vergeben im RiskService.
            $table->unsignedInteger('assessment_no');
            $table->string('kind', 16); // gross|net|target
            $table->unsignedTinyInteger('likelihood'); // 1-5
            $table->unsignedTinyInteger('impact');     // 1-5
            // Berechnet (likelihood * impact), persistiert für Listen/Vergleich.
            $table->unsignedSmallInteger('score');
            $table->text('rationale')->nullable();
            $table->string('status', 16)->default('draft'); // draft|approved
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            // Ablauf-/Reviewdatum für akzeptierte Restrisiken (net).
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['isms_risk_id', 'assessment_no'], 'isms_assessment_risk_no_uq');
            $table->index(['organization_id', 'isms_risk_id', 'kind', 'status'], 'isms_assessment_org_risk_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_risk_assessments');
    }
};
