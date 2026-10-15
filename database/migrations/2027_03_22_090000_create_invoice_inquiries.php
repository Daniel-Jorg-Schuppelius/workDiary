<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_22_090000_create_invoice_inquiries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rechnungsnachfragen aus E-Mails (Feature 166, MVP-1199): eine Nachricht
// je Organisation genau einmal; Ziel ist eine lokale Rechnung oder ein
// Beleg des Buchhaltungsprogramms (external_source + external_key).
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'iiq_org_fk')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices', indexName: 'iiq_invoice_fk')->nullOnDelete();
            $table->string('external_source', 40)->nullable();
            $table->string('external_key', 64)->nullable();
            $table->string('invoice_number', 64);
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'iiq_customer_fk')->nullOnDelete();
            $table->string('message_id', 255);
            $table->string('in_reply_to', 255)->nullable();
            $table->string('from_email', 255);
            $table->string('from_name', 160)->nullable();
            $table->string('subject', 255)->nullable();
            $table->dateTime('received_at');
            $table->string('match_kind', 16);
            $table->foreignId('communication_note_id')->nullable()->constrained('communication_notes', indexName: 'iiq_note_fk')->nullOnDelete();
            $table->string('status', 16)->default('open');
            $table->foreignId('answered_by')->nullable()->constrained('users', indexName: 'iiq_answered_by_fk')->nullOnDelete();
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'message_id'], 'iiq_org_message_unique');
            $table->index(['organization_id', 'status', 'received_at'], 'iiq_org_status_received_idx');
            $table->index(['organization_id', 'external_source', 'external_key'], 'iiq_org_external_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_inquiries');
    }
};
