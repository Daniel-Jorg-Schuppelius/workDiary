<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_12_130000_create_survey_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umfrage-Engine (Feature 090, MVP-660/661): wiederverwendbare Fragebögen mit
 * signierten Einmal-Links — keine Marketing-Automation.
 *
 * Anonymität ist eine Speicher-, keine Anzeigeeigenschaft: Bei anonymen
 * Umfragen trägt die Antwort KEINEN Einladungsbezug (invitation_id NULL) und
 * die Einladung keinen Antwortzeitpunkt — ein Re-Identifikations-Join hat
 * keine Felder, über die er laufen könnte.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('surveys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('purpose', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('anonymous')->default(false);
            // Trigger am Fragebogen selbst: nach Ticketabschluss einladen.
            $table->boolean('trigger_on_ticket_close')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('type', 16); // nps | scale | choice | text
            $table->string('label', 500);
            $table->json('options')->nullable(); // choice-Antworten
            $table->boolean('required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('survey_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('email', 190);
            // Anlassbezug nur als Aggregat-Schlüssel (ticket/diary/manual) -
            // bewusst kein FK: der Anlass dient der Auswertung, nicht der Akte.
            $table->string('context_kind', 24)->default('manual');
            $table->string('token_hash', 128);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            // Bei anonymen Umfragen bleibt der Zeitpunkt leer - nur der Status
            // wechselt (kein Join-Feld zur Antwort).
            $table->string('status', 16)->default('created'); // created|sent|responded|expired
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique('token_hash', 'survey_inv_token_uq');
            $table->index(['organization_id', 'email', 'sent_at'], 'survey_inv_org_email_idx');
        });

        Schema::create('survey_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            // NULL bei anonymen Umfragen - der einzige Personenbezug fällt weg.
            $table->foreignId('survey_invitation_id')->nullable()->constrained('survey_invitations')->nullOnDelete();
            $table->string('context_kind', 24)->default('manual');
            $table->timestamps();
        });

        Schema::create('survey_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('survey_response_id')->constrained('survey_responses')->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->integer('value_int')->nullable();
            $table->text('value_text')->nullable();
            $table->timestamps();
        });

        // Opt-out je Kunde (DSGVO-Leitplanke): keine Einladungen mehr.
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('survey_opt_out')->default(false)->after('exclude_from_reports');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('survey_opt_out');
        });
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_invitations');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
