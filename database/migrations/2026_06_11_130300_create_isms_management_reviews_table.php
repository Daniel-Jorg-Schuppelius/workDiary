<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_130300_create_isms_management_reviews_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managementbewertung (Feature 046, Inkrement C): Protokoll je
 * Geltungsbereich mit Eingaben (Auditergebnisse, Kennzahlen, Risiken …),
 * Entscheidungen und Folgemaßnahmen; laufende Nummer je Organisation
 * (review_no). Freigabe gemäß 046-Architekturprinzip „Jede Freigabe
 * enthält Person, Zeitpunkt und Gegenstand": approve setzt
 * approved_by_user_id + approved_at; freigegebene Bewertungen sind danach
 * NICHT mehr editierbar (update/delete auf approved ⇒ ValidationException
 * im AuditService — Historisierung statt Korrektur).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('isms_management_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('isms_scope_id')->constrained('isms_scopes')->cascadeOnDelete();
            $table->unsignedInteger('review_no');
            $table->date('held_on');
            $table->text('participants');
            $table->text('inputs');
            $table->text('decisions');
            $table->text('follow_ups')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Kurze explizite Namen (MySQL-64-Zeichen-Limit).
            $table->unique(['organization_id', 'review_no'], 'isms_review_org_no_uq');
            $table->index(['organization_id', 'status'], 'isms_review_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('isms_management_reviews');
    }
};
