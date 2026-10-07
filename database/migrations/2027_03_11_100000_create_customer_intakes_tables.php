<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_11_100000_create_customer_intakes_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1074/1075: Kundeneingang aus dem Portal — schmale Akte vor Angebot und
 * Fachakte, Nachrichtenstrang (Rückfrage, Antwort, interne Notiz) und Journal.
 * `submission_key` macht wiederholtes Absenden idempotent, die eindeutigen
 * Angebots- und Zielbezüge verhindern doppelte Übernahmen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_intakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cin_org_fk')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers', indexName: 'cin_customer_fk')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users', indexName: 'cin_submitter_fk')->nullOnDelete();
            $table->string('number', 40);
            $table->string('kind', 20);
            $table->string('status', 30)->default('submitted');
            $table->string('subject', 200);
            $table->text('description')->nullable();
            $table->date('desired_date')->nullable();
            $table->json('form')->nullable();
            $table->json('catalog_form')->nullable();
            $table->foreignId('request_item_id')->nullable()->constrained('request_items', indexName: 'cin_request_item_fk')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets', indexName: 'cin_asset_fk')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users', indexName: 'cin_assignee_fk')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->unique('cin_quote_unique')->constrained('quotes', indexName: 'cin_quote_fk')->nullOnDelete();
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->foreignId('handover_user_id')->nullable()->constrained('users', indexName: 'cin_handover_fk')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('is_upload_open')->default(false);
            $table->string('submission_key', 64);
            $table->timestamp('mail_failed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number'], 'cin_number_unique');
            $table->unique(['organization_id', 'submission_key'], 'cin_submission_unique');
            $table->unique(['target_type', 'target_id'], 'cin_target_unique');
            $table->index(['organization_id', 'status'], 'cin_status_idx');
            $table->index(['organization_id', 'customer_id'], 'cin_customer_idx');
        });

        Schema::create('customer_intake_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cim_org_fk')->cascadeOnDelete();
            $table->foreignId('customer_intake_id')->constrained('customer_intakes', indexName: 'cim_intake_fk')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users', indexName: 'cim_author_fk')->nullOnDelete();
            $table->string('kind', 20);
            $table->text('body');
            $table->timestamps();

            $table->index(['customer_intake_id', 'created_at'], 'cim_chrono_idx');
        });

        Schema::create('customer_intake_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_intake_id')->constrained('customer_intakes', indexName: 'cie_intake_fk')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users', indexName: 'cie_actor_fk')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['customer_intake_id', 'occurred_at'], 'cie_chrono_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_intake_events');
        Schema::dropIfExists('customer_intake_messages');
        Schema::dropIfExists('customer_intakes');
    }
};
