<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_15_320000_create_customer_queries_and_token_decisions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 012 (Kundenportal & Freigaben), offene MVP-Punkte:
 *
 * 1. Ablehnungs-Pfad für den bestehenden Protokoll-Signaturlink: der Kunde
 *    kann statt zu unterschreiben mit Begründung ablehnen. Dafür erhält
 *    `protocol_signature_tokens` eine Entscheidung (approved/rejected) inkl.
 *    Begründung und Zeitstempel. Die Token-Einmal-/Ablauf-Mechanik bleibt
 *    unverändert (kein paralleles Token-System).
 * 2. Leichtgewichtige Kunden-Rückfragen (`customer_queries`) mit polymorphem
 *    Subjekt (Protokoll, Auftrag/DiaryEntry …), Frage, interner Antwort und
 *    Status.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('protocol_signature_tokens', function (Blueprint $table): void {
            // approved | rejected (null = noch keine Entscheidung getroffen).
            $table->string('decision', 20)->nullable()->after('used_at');
            $table->text('decision_reason')->nullable()->after('decision');
            $table->timestamp('decided_at')->nullable()->after('decision_reason');
        });

        Schema::create('customer_queries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // Optionaler Bezug auf den Signaturlink, über den die Rückfrage
            // gestellt wurde (Token-Zugriff ohne Login).
            $table->foreignId('signature_token_id')->nullable()
                ->constrained('protocol_signature_tokens')->nullOnDelete();
            $table->string('asker_name', 120)->nullable();
            $table->string('asker_email', 180)->nullable();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'customer_queries_org_status_idx');
            $table->index(['subject_type', 'subject_id'], 'customer_queries_subject_idx');
            $table->index('customer_id', 'customer_queries_customer_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_queries');

        Schema::table('protocol_signature_tokens', function (Blueprint $table): void {
            $table->dropColumn(['decision', 'decision_reason', 'decided_at']);
        });
    }
};
