<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_170700_create_firefly_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Firefly III (Feature 176, MVP-1333): geprüfte Verbindung je Organisation und
 * Spiegel der Kontobewegungen mit ihrer Zuordnung zu Rechnung bzw.
 * Eingangsrechnung. Der Zugriffstoken steht in den Plugin-Einstellungen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('firefly_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('ffc_org_unique')->constrained('organizations', indexName: 'ffc_org_fk')->cascadeOnDelete();
            $table->string('source_id', 64)->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->string('account_name')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_counters')->nullable();
            $table->boolean('is_last_run_complete')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'ffc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'ffc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('firefly_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'fft_org_fk')->cascadeOnDelete();
            $table->string('remote_journal_id', 64);
            $table->string('remote_group_id', 64)->nullable();
            $table->string('kind', 16);
            $table->date('booked_on');
            $table->decimal('booked_amount', 15, 2);
            $table->string('currency', 3)->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('account_name')->nullable();
            $table->json('references')->nullable();
            $table->string('match_status', 16)->default('unmatched');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices', indexName: 'fft_invoice_fk')->nullOnDelete();
            $table->foreignId('incoming_einvoice_id')->nullable()->constrained('incoming_einvoices', indexName: 'fft_incoming_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'remote_journal_id'], 'fft_org_journal_unique');
            $table->index(['organization_id', 'booked_on'], 'fft_org_booked_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('firefly_transactions');
        Schema::dropIfExists('firefly_connections');
    }
};
