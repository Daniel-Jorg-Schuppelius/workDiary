<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_26_100300_create_appointment_requests_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quellenagnostischer Terminwunsch-Intake (Feature 095, minimaler 087-Kern):
 * ein extern gebuchter Termin (Quelle `calendly`) landet als `requested` und
 * wird ERST durch interne Bestätigung zum Dispositionseintrag (`diary_entry_id`).
 * Zweiphasig — kein Externer schreibt in den Dienstplan. `source_uri` (die
 * Calendly-Invitee-URI) ist der Idempotenz-Anker: unique(org, source, source_uri).
 * Reschedule-Verlinkung über URI-Strings (reihenfolge-unabhängig). `lead_id` und
 * `bookable_service_id` bleiben ohne FK, bis Feature 091/087 gebaut sind.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('appointment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'apreq_org_fk')->cascadeOnDelete();
            $table->string('source', 16)->default('calendly'); // calendly / portal
            $table->string('source_uri')->nullable();          // Calendly-Invitee-URI (Idempotenz-Anker)
            $table->string('status', 16)->default('requested'); // requested/confirmed/declined/canceled/superseded

            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'apreq_customer_fk')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();            // kein FK bis Feature 091
            $table->unsignedBigInteger('bookable_service_id')->nullable(); // kein FK bis Feature 087
            $table->foreignId('assigned_user_id')->nullable()->constrained('users', indexName: 'apreq_assignee_fk')->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries', indexName: 'apreq_diary_fk')->nullOnDelete();

            $table->timestamp('start_at')->nullable(); // UTC
            $table->timestamp('end_at')->nullable();   // UTC
            $table->string('invitee_timezone', 64)->nullable();
            $table->string('invitee_name')->nullable();
            $table->string('invitee_email')->nullable();
            $table->string('service_label')->nullable();
            $table->string('location_type', 64)->nullable();
            $table->string('location')->nullable();
            $table->string('join_url')->nullable();
            $table->string('cancel_url')->nullable();
            $table->string('reschedule_url')->nullable();
            $table->json('questions_and_answers')->nullable();
            $table->json('tracking')->nullable();
            $table->json('cancellation')->nullable();

            $table->boolean('is_reschedule')->default(false);
            $table->string('rescheduled_from_uri')->nullable();
            $table->string('rescheduled_to_uri')->nullable();

            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'apreq_decided_by_fk')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decline_reason')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'source', 'source_uri'], 'apreq_source_unique');
            $table->index(['organization_id', 'status'], 'apreq_org_status_idx');
            $table->index('rescheduled_from_uri', 'apreq_resched_from_idx');
            $table->index('rescheduled_to_uri', 'apreq_resched_to_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('appointment_requests');
    }
};
